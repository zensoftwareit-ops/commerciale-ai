<?php

namespace App\Services\Mail;

use App\Mail\LeadReplyMail;
use App\Models\Activity;
use App\Models\LeadReply;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendLeadReply
{
    public function handle(LeadReply $reply, ?int $actorId = null, bool $automatic = false): void
    {
        if ($reply->status === 'sent') throw new RuntimeException('Questa email è già stata inviata.');
        if ($automatic && ! $reply->automation_eligible) throw new RuntimeException('La bozza non supera i controlli per l’invio automatico.');
        if (in_array(config('mail.default'), ['log', 'array'], true)) throw new RuntimeException('Configura un servizio SMTP reale prima dell’invio.');

        $claimed = LeadReply::query()->whereKey($reply->id)->where('status', 'draft')->update(['status' => 'sending']);
        if ($claimed !== 1) throw new RuntimeException('La bozza è già in elaborazione o non è più inviabile.');
        $reply->status = 'sending';

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
}
