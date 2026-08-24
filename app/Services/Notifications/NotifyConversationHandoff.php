<?php

namespace App\Services\Notifications;

use App\Mail\ConversationHandoffMail;
use App\Models\CommercialNotification;
use App\Models\InboundEmail;
use App\Models\Lead;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyConversationHandoff
{
    public function handle(Lead $lead, InboundEmail $inbound, string $reason): void
    {
        $organization = $lead->organization()->firstOrFail();
        $recipients = $organization->users()->wherePivotIn('role', ['owner', 'sales'])->get();

        foreach ($recipients as $recipient) {
            $notification = CommercialNotification::create([
                'organization_id' => $organization->id,
                'user_id' => $recipient->id,
                'lead_id' => $lead->id,
                'type' => 'conversation_handoff',
                'title' => 'Intervento commerciale richiesto',
                'message' => $lead->name.' ha risposto, ma l’automazione non può proseguire in modo affidabile.',
                'data' => [
                    'reason' => $reason, 'inbound_email_id' => $inbound->id,
                    'from' => $inbound->from_address, 'subject' => $inbound->subject,
                ],
            ]);

            try {
                Mail::to($recipient->email)->send(new ConversationHandoffMail($notification));
                $notification->update(['notified_by_email_at' => now()]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}

