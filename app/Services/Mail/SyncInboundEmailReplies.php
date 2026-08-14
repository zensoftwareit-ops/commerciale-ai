<?php

namespace App\Services\Mail;

use App\Contracts\InboundMailbox;
use App\Data\InboundEmailMessage;
use App\Exceptions\ConversationHandoffRequired;
use App\Models\Activity;
use App\Models\InboundEmail;
use App\Models\Lead;
use App\Models\LeadContact;
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

    /** @return array{scanned:int, imported:int, duplicates:int, unmatched:int, automated:int, drafts:int, handoffs:int, draft_errors:int} */
    public function handle(?int $limit = null): array
    {
        $stats = ['scanned' => 0, 'imported' => 0, 'duplicates' => 0, 'unmatched' => 0, 'automated' => 0, 'drafts' => 0, 'handoffs' => 0, 'draft_errors' => 0];
        $limit ??= (int) config('commerciale-ai.imap.max_messages', 50);

        try {
            foreach ($this->mailbox->recent(max(1, min($limit, 200))) as $message) {
                $stats['scanned']++;
                if ($message->automated || ! filter_var($message->fromAddress, FILTER_VALIDATE_EMAIL)) {
                    $this->mailbox->markSeen($message->identifier);
                    $stats['automated']++;

                    continue;
                }

                $messageHash = $this->messageHash($message);
                if (InboundEmail::withoutGlobalScopes()->where('message_hash', $messageHash)->exists()) {
                    $this->mailbox->markSeen($message->identifier);
                    $stats['duplicates']++;

                    continue;
                }

                $match = $this->match($message);
                [$lead, $sentReply] = [$match['lead'], $match['reply']];
                if (! $lead) {
                    if ($organization = $this->organizationForPending($match['thread_reply'])) {
                        app(TenantContext::class)->set($organization);
                        try {
                            $this->storePending($message, $messageHash);
                        } finally {
                            app(TenantContext::class)->clear();
                        }
                    }
                    $this->mailbox->markSeen($message->identifier);
                    $stats['unmatched']++;

                    continue;
                }

                $organization = Organization::query()->findOrFail($lead->organization_id);
                app(TenantContext::class)->set($organization);
                try {
                    $inbound = $this->store($message, $messageHash, $lead, $sentReply, $match);
                    $stats['imported']++;
                    $this->prepareDraft($inbound, $lead, $stats);
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

    /** @return array{lead:Lead|null,reply:LeadReply|null,thread_reply:LeadReply|null,confidence:?string,reason:?string,sender_differs:bool} */
    private function match(InboundEmailMessage $message): array
    {
        $from = LeadData::normalizeEmail($message->fromAddress);
        $threadIds = array_values(array_unique(array_filter([$message->inReplyTo, ...$message->references])));
        $threadReply = null;
        if ($threadIds !== []) {
            $threadReply = LeadReply::withoutGlobalScopes()
                ->where('status', 'sent')->whereIn('outbound_message_id', $threadIds)
                ->latest('sent_at')->first();
            if ($threadReply) {
                $lead = Lead::withoutGlobalScopes()->find($threadReply->lead_id);
                if ($lead) {
                    $senderDiffers = ! hash_equals((string) $lead->email_normalized, (string) $from);

                    return [
                        'lead' => $lead, 'reply' => $threadReply, 'thread_reply' => $threadReply,
                        'confidence' => 'high', 'reason' => 'thread_id', 'sender_differs' => $senderDiffers,
                    ];
                }
            }
        }

        $leads = Lead::withoutGlobalScopes()
            ->where('email_normalized', $from)
            ->whereHas('replies', fn ($query) => $query->where('status', 'sent'))
            ->limit(2)->get();
        if ($leads->count() !== 1) {
            $contactLeadIds = LeadContact::withoutGlobalScopes()
                ->where('email_normalized', $from)
                ->limit(2)
                ->pluck('lead_id')
                ->unique();
            if ($contactLeadIds->count() === 1) {
                $contactLead = Lead::withoutGlobalScopes()
                    ->whereKey($contactLeadIds->first())
                    ->whereHas('replies', fn ($query) => $query->where('status', 'sent'))
                    ->first();
                if ($contactLead) {
                    $contactReply = LeadReply::withoutGlobalScopes()
                        ->where('lead_id', $contactLead->id)
                        ->where('status', 'sent')
                        ->latest('sent_at')
                        ->first();

                    return [
                        'lead' => $contactLead, 'reply' => $contactReply, 'thread_reply' => $threadReply,
                        'confidence' => 'high', 'reason' => 'known_contact', 'sender_differs' => true,
                    ];
                }
            }

            return ['lead' => null, 'reply' => null, 'thread_reply' => $threadReply, 'confidence' => null, 'reason' => null, 'sender_differs' => false];
        }

        $lead = $leads->first();
        $reply = LeadReply::withoutGlobalScopes()->where('lead_id', $lead->id)->where('status', 'sent')->latest('sent_at')->first();

        return [
            'lead' => $lead, 'reply' => $reply, 'thread_reply' => $threadReply,
            'confidence' => 'high', 'reason' => 'unique_sender', 'sender_differs' => false,
        ];
    }

    private function store(InboundEmailMessage $message, string $messageHash, Lead $lead, ?LeadReply $sentReply, array $match): InboundEmail
    {
        return DB::transaction(function () use ($message, $messageHash, $lead, $sentReply, $match): InboundEmail {
            $hadFollowUp = $lead->next_action_at !== null || ($sentReply?->follow_up_at !== null && $sentReply?->follow_up_cancelled_at === null);
            $inbound = InboundEmail::create([
                'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
                'lead_reply_id' => $sentReply?->id, 'status' => 'linked',
                'match_confidence' => $match['confidence'], 'match_reason' => $match['reason'],
                'sender_differs' => $match['sender_differs'], 'message_hash' => $messageHash,
                'message_id' => $message->messageId, 'in_reply_to' => $message->inReplyTo,
                'imap_uid' => $message->identifier, 'from_address' => $message->fromAddress,
                'from_name' => $message->fromName, 'subject' => $message->subject ?: '(senza oggetto)',
                'body' => $message->body, 'received_at' => $message->receivedAt, 'linked_at' => now(),
            ]);
            if ($sentReply && $hadFollowUp) {
                $sentReply->update(['follow_up_cancelled_at' => now()]);
            }
            $lead->update(['operational_status' => 'needs_action', 'next_action_at' => null, 'last_activity_at' => now()]);
            Activity::create([
                'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
                'type' => 'email_received', 'title' => 'Risposta email ricevuta',
                'data' => [
                    'inbound_email_id' => $inbound->id, 'from' => $message->fromAddress,
                    'subject' => $message->subject, 'match_reason' => $match['reason'],
                    'sender_differs' => $match['sender_differs'],
                ],
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

    private function storePending(InboundEmailMessage $message, string $messageHash): InboundEmail
    {
        return InboundEmail::create([
            'organization_id' => app(TenantContext::class)->requireOrganization()->id,
            'status' => 'pending', 'message_hash' => $messageHash, 'message_id' => $message->messageId,
            'in_reply_to' => $message->inReplyTo, 'imap_uid' => $message->identifier,
            'from_address' => $message->fromAddress, 'from_name' => $message->fromName,
            'subject' => $message->subject ?: '(senza oggetto)', 'body' => $message->body,
            'received_at' => $message->receivedAt,
        ]);
    }

    /** @param array<string, int> $stats */
    private function prepareDraft(InboundEmail $inbound, Lead $lead, array &$stats): void
    {
        $analysis = $lead->analyses()->first();
        if (! $analysis) {
            return;
        }

        try {
            $this->replyGenerator->handle($lead->fresh(), $analysis, null, [
                'incoming_email' => [
                    'message_id' => $inbound->message_id, 'from' => $inbound->from_address,
                    'subject' => $inbound->subject, 'body' => mb_substr($inbound->body, 0, 12000),
                    'received_at' => $inbound->received_at->toIso8601String(),
                ],
            ]);
            $stats['drafts']++;
        } catch (ConversationHandoffRequired $exception) {
            $lead->update(['operational_status' => 'needs_action', 'next_action_at' => now(), 'last_activity_at' => now()]);
            $lead->replies()->where('status', 'draft')->update([
                'automation_eligible' => false,
                'automation_blockers' => json_encode(['human_handoff_required'], JSON_THROW_ON_ERROR),
            ]);
            Activity::create([
                'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
                'type' => 'conversation_handoff', 'title' => 'Conversazione passata al commerciale',
                'data' => ['inbound_email_id' => $inbound->id, 'reason' => $exception->reason], 'occurred_at' => now(),
            ]);
            $stats['handoffs']++;
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

    private function organizationForPending(?LeadReply $threadReply): ?Organization
    {
        if ($threadReply) {
            return Organization::query()->find($threadReply->organization_id);
        }

        $organizations = Organization::query()->limit(2)->get();

        return $organizations->count() === 1 ? $organizations->first() : null;
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

