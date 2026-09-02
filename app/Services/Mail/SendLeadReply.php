<?php

namespace App\Services\Mail;

use App\Mail\LeadReplyMail;
use App\Models\Activity;
use App\Models\LeadReply;
use App\Models\MailboxAccount;
use App\Models\OrganizationSetting;
use App\Models\Quotation;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendLeadReply
{
    public function __construct(private readonly MailIdentity $identities) {}

    public function handle(LeadReply $reply, ?int $actorId = null, bool $automatic = false): void
    {
        if ($reply->status === 'sent') throw new RuntimeException('Questa email è già stata inviata.');
        if (in_array(config('mail.default'), ['log', 'array'], true)) throw new RuntimeException('Configura un servizio SMTP reale prima dell’invio.');

        $identity = $this->identities->commercialForOrganization($reply->organization_id);
        if ($automatic) $this->guardAutomaticSend($reply, $identity['mailbox']);

        $claimed = LeadReply::query()->whereKey($reply->id)->where('status', 'draft')->update(['status' => 'sending']);
        if ($claimed !== 1) throw new RuntimeException('La bozza è già in elaborazione o non è più inviabile.');
        $reply->status = 'sending';
        $reply->sender_address = $identity['from']->address;
        $reply->sender_name = $identity['from']->name;
        $reply->reply_to_address = $identity['reply_to']->address;
        $reply->saveQuietly();

        $reply->ensureOutboundMessageId();
        try {
            Mail::to($reply->recipient)->send(new LeadReplyMail($reply));
        } catch (Throwable $exception) {
            $reply->update(['status' => 'draft', 'last_error' => mb_substr($exception->getMessage(), 0, 2000)]);
            throw $exception;
        }

        $reply->update([
            'status' => 'sent', 'delivery_mode' => $automatic ? 'automatic' : 'manual',
            'approved_by' => $actorId, 'approved_at' => now(), 'sent_at' => now(), 'last_error' => null,
        ]);
        $lead = $reply->lead;
        $lead->update([
            'operational_status' => $reply->follow_up_at ? 'follow_up_scheduled' : 'awaiting_customer',
            'next_action_at' => $reply->follow_up_at, 'last_activity_at' => now(),
        ]);
        Activity::create([
            'organization_id' => $lead->organization_id, 'lead_id' => $lead->id, 'actor_id' => $actorId,
            'type' => 'email_sent', 'title' => $automatic ? 'Risposta inviata automaticamente' : 'Email approvata e inviata',
            'data' => ['reply_id' => $reply->id, 'recipient' => $reply->recipient, 'subject' => $reply->subject, 'delivery_mode' => $automatic ? 'automatic' : 'manual'],
            'occurred_at' => now(),
        ]);
        if ($reply->follow_up_at) {
            Activity::create([
                'organization_id' => $lead->organization_id, 'lead_id' => $lead->id, 'actor_id' => $actorId,
                'type' => 'follow_up_scheduled', 'title' => 'Follow-up pianificato',
                'data' => ['reply_id' => $reply->id, 'follow_up_at' => $reply->follow_up_at->toIso8601String()], 'occurred_at' => now(),
            ]);
        }
    }

    private function guardAutomaticSend(LeadReply $reply, MailboxAccount $mailbox): void
    {
        if (! $reply->automation_eligible) throw new RuntimeException('La bozza non supera i controlli per l’invio automatico.');
        $settings = OrganizationSetting::query()->first();
        $isInitial = str_starts_with($reply->reply_kind, 'initial');
        if (! $isInitial && ! $settings?->conversation_automation_enabled) throw new RuntimeException('Automazione conversazioni disabilitata.');
        $lead = $reply->lead;
        $allowed = collect($settings->automation_allowed_recipients ?? [])->map(fn ($email) => mb_strtolower(trim((string) $email)));
        $internalOnly = $settings->internal_test_only || ! config('commerciale-ai.automation.external_send_enabled');
        if ($internalOnly && ! $allowed->contains($lead->email_normalized)) throw new RuntimeException('Destinatario non incluso nella lista interna.');
        if (! $internalOnly && $mailbox->domain_verification_status !== 'verified') throw new RuntimeException('Il dominio mittente non è ancora verificato per gli invii automatici esterni.');
        if ($lead->replies()->where('delivery_mode', 'automatic')->where('status', 'sent')->count() >= $settings->max_automatic_replies) throw new RuntimeException('Limite di risposte automatiche raggiunto.');

        if ($isInitial && ! $settings->auto_send_initial_email) throw new RuntimeException('Invio automatico della prima email disabilitato.');
        if ($isInitial && ! $settings->auto_analyze_new_leads) throw new RuntimeException('Automazione dei nuovi lead disabilitata.');
        if (str_contains($reply->reply_kind, 'quotation')) {
            if (! $settings->auto_send_quotes_enabled) throw new RuntimeException('Invio automatico preventivi disabilitato.');
            $quotation = Quotation::query()->where('lead_reply_id', $reply->id)->first();
            if (! $quotation || ! $quotation->auto_send_eligible) throw new RuntimeException('Preventivo non idoneo all’invio automatico.');
            if ($settings->max_auto_quote_amount === null || (float) $quotation->maximum_price > (float) $settings->max_auto_quote_amount) throw new RuntimeException('Preventivo oltre la soglia automatica corrente.');
        }
    }
}
