<?php

namespace App\Services\Notifications;

use App\Mail\ConversationHandoffMail;
use App\Models\Activity;
use App\Models\CommercialNotification;
use App\Models\Lead;
use App\Services\Mail\MailIdentity;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyAutomationFailure
{
    public function __construct(private readonly MailIdentity $identities) {}

    public function handle(Lead $lead, string $reason): void
    {
        if (CommercialNotification::query()->where('lead_id', $lead->id)->where('type', 'automation_failed')->exists()) {
            return;
        }

        $organization = $lead->organization()->firstOrFail();
        $recipients = $organization->users()->wherePivotIn('role', ['owner', 'sales'])->get();
        foreach ($recipients as $recipient) {
            $notification = CommercialNotification::create([
                'organization_id' => $organization->id,
                'user_id' => $recipient->id,
                'lead_id' => $lead->id,
                'type' => 'automation_failed',
                'title' => 'Automazione sospesa',
                'message' => 'Daria ha esaurito i tentativi automatici per '.$lead->name.'. È necessario l’intervento di un commerciale.',
                'data' => ['reason' => $reason],
            ]);

            try {
                $identity = $this->identities->forPlatform();
                Mail::to($recipient->email)->send(new ConversationHandoffMail($notification, $identity->address, $identity->name));
                $notification->update(['notified_by_email_at' => now()]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        Activity::create([
            'organization_id' => $lead->organization_id,
            'lead_id' => $lead->id,
            'type' => 'automation_handoff',
            'title' => 'Automazione sospesa e assegnata a un commerciale',
            'data' => ['reason' => $reason],
            'occurred_at' => now(),
        ]);
        $lead->update(['operational_status' => 'needs_action', 'next_action_at' => now()]);
    }
}
