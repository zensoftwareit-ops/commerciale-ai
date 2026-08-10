<?php

namespace App\Services\Mail;

use App\Contracts\InboundMailbox;
use App\Data\InboundEmailMessage;
use App\Models\Activity;
use App\Models\InboundEmail;
use App\Models\Lead;
use App\Models\LeadReply;
use App\Models\Organization;
use App\Services\Ai\GenerateLeadReply;
use App\Services\Leads\LeadData;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncInboundEmailReplies
{
    public function __construct(
        private readonly InboundMailbox $mailbox,
        private readonly GenerateLeadReply $replyGenerator,
    ) {}

    /** @return array{scanned:int, imported:int, duplicates:int, unmatched:int, automated:int, drafts:int, draft_errors:int} */
    public function handle(?int $limit = null): array
    {
        $stats = ['scanned' => 0, 'imported' => 0, 'duplicates' => 0, 'unmatched' => 0, 'automated' => 0, 'drafts' => 0, 'draft_errors' => 0];
        $limit ??= (int) config('commerciale-ai.imap.max_messages', 50);

        try {
            foreach ($this->mailbox->recent(max(1, min($limit, 200))) as $message) {
                $stats['scanned']++;
                if ($message->automated || ! filter_var($message->fromAddress, FILTER_VALIDATE_EMAIL)) {
                    $stats['automated']++;

                    continue;
                }

                $messageHash = $this->messageHash($message);
                if (InboundEmail::withoutGlobalScopes()->where('message_hash', $messageHash)->exists()) {
                    $this->mailbox->markSeen($message->identifier);
                    $stats['duplicates']++;

                    continue;
                }

                [$lead, $sentReply] = $this->match($message);
                if (! $lead) {
                    $stats['unmatched']++;

                    continue;
                }

                $organization = Organization::query()->findOrFail($lead->organization_id);
                app(TenantContext::class)->set($organization);
                try {
                    $inbound = $this->store($message, $messageHash, $lead, $sentReply);
                    $stats['imported']++;
                    $analysis = $lead->analyses()->first();
                    if ($analysis) {
                        try {
                            $this->replyGenerator->handle($lead->fresh(), $analysis, null, [
                                'incoming_email' => [
                                    'message_id' => $message->messageId,
                                    'from' => $message->fromAddress,
                                    'subject' => $message->subject,
                                    'body' => mb_substr($message->body, 0, 12000),
                                    'received_at' => $message->receivedAt->toIso8601String(),
                                ],
                            ]);
                            $stats['drafts']++;
                        } catch (Throwable $exception) {
                            report($exception);
                            $stats['draft_errors']++;
                            Activity::create([
                                'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
                                'type' => 'reply_draft_failed', 'title' => 'Bozza di risposta non generata',
                                'data' => ['inbound_email_id' => $inbound->id], 'occurred_at' => now(),
                            ]);
                        }
                    }
                    $this->mailbox->markSeen($message->identifier);
                } finally {
                    app(TenantContext::class)->clear();
                }
            }
        } finally {
            $this->mailbox->close();
        }

        return $stats;
    }

    /** @return array{0: Lead|null, 1: LeadReply|null} */
    private function match(InboundEmailMessage $message): array
    {
        $from = LeadData::normalizeEmail($message->fromAddress);
        $threadIds = array_values(array_unique(array_filter([$message->inReplyTo, ...$message->references])));
        if ($threadIds !== []) {
            $reply = LeadReply::withoutGlobalScopes()
                ->where('status', 'sent')->whereIn('outbound_message_id', $threadIds)
                ->latest('sent_at')->first();
            if ($reply) {
                $lead = Lead::withoutGlobalScopes()->find($reply->lead_id);
                if ($lead && hash_equals((string) $lead->email_normalized, (string) $from)) {
                    return [$lead, $reply];
                }
            }
        }

        $leads = Lead::withoutGlobalScopes()
            ->where('email_normalized', $from)
            ->whereHas('replies', fn ($query) => $query->where('status', 'sent'))
            ->limit(2)->get();
        if ($leads->count() !== 1) {
            return [null, null];
        }

        $lead = $leads->first();
        $reply = LeadReply::withoutGlobalScopes()->where('lead_id', $lead->id)->where('status', 'sent')->latest('sent_at')->first();

        return [$lead, $reply];
    }

    private function store(InboundEmailMessage $message, string $messageHash, Lead $lead, ?LeadReply $sentReply): InboundEmail
    {
        return DB::transaction(function () use ($message, $messageHash, $lead, $sentReply): InboundEmail {
            $hadFollowUp = $lead->next_action_at !== null || ($sentReply?->follow_up_at !== null && $sentReply?->follow_up_cancelled_at === null);
            $inbound = InboundEmail::create([
                'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
                'lead_reply_id' => $sentReply?->id, 'message_hash' => $messageHash,
                'message_id' => $message->messageId, 'in_reply_to' => $message->inReplyTo,
                'imap_uid' => $message->identifier, 'from_address' => $message->fromAddress,
                'from_name' => $message->fromName, 'subject' => $message->subject ?: '(senza oggetto)',
                'body' => $message->body, 'received_at' => $message->receivedAt,
            ]);
            if ($sentReply && $hadFollowUp) {
                $sentReply->update(['follow_up_cancelled_at' => now()]);
            }
            $lead->update(['operational_status' => 'needs_action', 'next_action_at' => null, 'last_activity_at' => now()]);
            Activity::create([
                'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
                'type' => 'email_received', 'title' => 'Risposta email ricevuta',
                'data' => ['inbound_email_id' => $inbound->id, 'from' => $message->fromAddress, 'subject' => $message->subject],
                'occurred_at' => $message->receivedAt,
            ]);
            if ($hadFollowUp) {
                Activity::create([
                    'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
                    'type' => 'follow_up_cancelled', 'title' => 'Follow-up annullato dopo la risposta',
                    'data' => ['inbound_email_id' => $inbound->id], 'occurred_at' => now(),
                ]);
            }

            return $inbound;
        });
    }

    private function messageHash(InboundEmailMessage $message): string
    {
        $identity = $message->messageId ?: implode('|', [
            config('commerciale-ai.imap.username'), $message->identifier, $message->fromAddress,
            $message->receivedAt->toIso8601String(), $message->subject,
        ]);

        return hash('sha256', mb_strtolower(trim($identity)));
    }
}
