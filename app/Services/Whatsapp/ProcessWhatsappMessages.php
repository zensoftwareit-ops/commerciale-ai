<?php

namespace App\Services\Whatsapp;

use App\Exceptions\ConversationHandoffRequired;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\LeadReply;
use App\Models\Organization;
use App\Models\WhatsappAccount;
use App\Models\WhatsappMessage;
use App\Services\Ai\AnalyzeLead;
use App\Services\Ai\GenerateLeadReply;
use App\Services\Leads\CreateLead;
use App\Services\Notifications\NotifyConversationHandoff;
use App\Support\Tenancy\TenantContext;
use Throwable;

class ProcessWhatsappMessages
{
    public function __construct(
        private readonly CreateLead $leadCreator,
        private readonly AnalyzeLead $analyzer,
        private readonly GenerateLeadReply $replyGenerator,
        private readonly NotifyConversationHandoff $handoffNotifier,
    ) {}

    /** @return array{accounts:int,pending:int,processed:int,drafts:int,handoffs:int,failed:int} */
    public function handle(int $limit = 25): array
    {
        $stats = ['accounts' => 0, 'pending' => 0, 'processed' => 0, 'drafts' => 0, 'handoffs' => 0, 'failed' => 0];
        foreach (Organization::query()->cursor() as $organization) {
            app(TenantContext::class)->set($organization);
            try {
                $account = WhatsappAccount::query()->where('is_active', true)->first();
                if (! $account) continue;
                $stats['accounts']++;
                $messages = WhatsappMessage::query()->where('direction', 'inbound')->where('status', 'pending')
                    ->oldest('received_at')->limit(max(1, min($limit, 100)))->get();
                foreach ($messages as $message) {
                    $stats['pending']++;
                    $claimed = WhatsappMessage::query()->whereKey($message->id)->where('status', 'pending')->update(['status' => 'processing']);
                    if ($claimed !== 1) continue;
                    try {
                        $lead = $this->leadFor($message);
                        $message->update(['lead_id' => $lead->id]);
                        $lead->update(['operational_status' => 'needs_action', 'next_action_at' => null, 'last_activity_at' => now()]);
                        Activity::create([
                            'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
                            'type' => 'whatsapp_received', 'title' => 'Messaggio WhatsApp ricevuto',
                            'data' => ['whatsapp_message_id' => $message->id, 'from' => $message->from_number],
                            'occurred_at' => $message->received_at ?? now(),
                        ]);
                        if ($message->type !== 'text') {
                            throw new ConversationHandoffRequired('unsupported_whatsapp_message_type');
                        }

                        $analysis = $lead->analyses()->first() ?: $this->analyzer->handle($lead->fresh(), [
                            'channel' => 'whatsapp', 'message' => $message->body,
                        ]);
                        $reply = $this->replyGenerator->handle($lead->fresh(), $analysis, null, [
                            'channel' => 'whatsapp',
                            'incoming_email' => [
                                'message_id' => $message->external_message_id, 'from' => $message->from_number,
                                'subject' => 'WhatsApp', 'body' => mb_substr((string) $message->body, 0, 12000),
                                'received_at' => ($message->received_at ?? now())->toIso8601String(),
                            ],
                        ]);
                        $this->applyBetaControls($reply, $account, $message->from_number);
                        $message->update(['lead_reply_id' => $reply->id, 'status' => 'processed', 'processed_at' => now(), 'last_error' => null]);
                        $stats['processed']++;
                        $stats['drafts']++;
                    } catch (ConversationHandoffRequired $exception) {
                        $lead = $message->lead;
                        if ($lead) {
                            $lead->update(['operational_status' => 'needs_action', 'next_action_at' => now(), 'last_activity_at' => now()]);
                            Activity::create([
                                'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
                                'type' => 'conversation_handoff', 'title' => 'Conversazione WhatsApp passata al commerciale',
                                'data' => ['whatsapp_message_id' => $message->id, 'reason' => $exception->reason], 'occurred_at' => now(),
                            ]);
                            $this->handoffNotifier->handle($lead, $message, $exception->reason);
                        }
                        $message->update(['status' => 'handoff', 'processed_at' => now()]);
                        $stats['handoffs']++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $message->update(['status' => 'failed', 'failed_at' => now(), 'last_error' => mb_substr($exception->getMessage(), 0, 2000)]);
                        $stats['failed']++;
                    }
                }
            } finally {
                app(TenantContext::class)->clear();
            }
        }

        return $stats;
    }

    private function leadFor(WhatsappMessage $message): Lead
    {
        $digits = $this->digits($message->from_number);
        $lead = Lead::query()->whereNotNull('phone_normalized')->get()
            ->first(fn (Lead $candidate) => $this->digits($candidate->phone_normalized) === $digits);
        if ($lead) return $lead;

        return $this->leadCreator->handle([
            'name' => (string) data_get($message->payload, '_contact_name', 'Contatto WhatsApp'),
            'phone' => '+'.$digits, 'source_label' => 'whatsapp',
            'request_data' => ['message' => $message->body],
            'consent_data' => ['whatsapp_user_initiated' => true, 'received_at' => ($message->received_at ?? now())->toIso8601String()],
        ]);
    }

    private function applyBetaControls(LeadReply $reply, WhatsappAccount $account, string $recipient): void
    {
        $blockers = collect($reply->automation_blockers ?? [])->reject(fn ($blocker) => in_array($blocker, [
            'recipient_not_in_internal_allowlist', 'external_send_disabled_on_server', 'sender_domain_not_verified',
        ], true));
        if (! $account->auto_reply_enabled) $blockers->push('whatsapp_auto_reply_disabled');
        $internalOnly = $account->internal_test_only || ! config('services.whatsapp.beta_external_send_enabled');
        $allowed = collect($account->allowed_recipients ?? [])->map(fn ($number) => $this->digits((string) $number));
        if ($internalOnly && ! $allowed->contains($this->digits($recipient))) $blockers->push('whatsapp_recipient_not_allowed');
        $blockers = $blockers->unique()->values();
        $reply->update(['automation_blockers' => $blockers->all(), 'automation_eligible' => $blockers->isEmpty()]);
    }

    private function digits(string $number): string
    {
        return (string) preg_replace('/\D+/', '', $number);
    }
}
