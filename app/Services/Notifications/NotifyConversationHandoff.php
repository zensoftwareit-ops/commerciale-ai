<?php

namespace App\Services\Notifications;

use App\Mail\ConversationHandoffMail;
use App\Models\CommercialNotification;
use App\Models\InboundEmail;
use App\Models\Lead;
use App\Models\WhatsappMessage;
use App\Services\Mail\MailIdentity;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyConversationHandoff
{
    public function __construct(private readonly MailIdentity $identities) {}

    public function handle(Lead $lead, InboundEmail|WhatsappMessage $inbound, string $reason): void
    {
        $organization = $lead->organization()->firstOrFail();
        $recipients = $organization->users()->wherePivotIn('role', ['owner', 'sales'])->get();
        $identity = $this->identities->forPlatform();

        foreach ($recipients as $recipient) {
            $notification = CommercialNotification::create([
                'organization_id' => $organization->id,
                'user_id' => $recipient->id,
                'lead_id' => $lead->id,
                'type' => 'conversation_handoff',
                'title' => 'Intervento commerciale richiesto',
                'message' => $lead->name.' ha risposto via '.($inbound instanceof WhatsappMessage ? 'WhatsApp' : 'email').', ma l’automazione non può proseguire in modo affidabile. Motivo: '.$this->reasonLabel($reason),
                'data' => [
                    'reason' => $reason,
                    'inbound_email_id' => $inbound instanceof InboundEmail ? $inbound->id : null,
                    'whatsapp_message_id' => $inbound instanceof WhatsappMessage ? $inbound->id : null,
                    'from' => $inbound instanceof WhatsappMessage ? $inbound->from_number : $inbound->from_address,
                    'subject' => $inbound instanceof WhatsappMessage ? 'WhatsApp' : $inbound->subject,
                ],
            ]);

            try {
                Mail::to($recipient->email)->send(new ConversationHandoffMail($notification, $identity->address, $identity->name));
                $notification->update(['notified_by_email_at' => now()]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'no_pricing_rule_after_conversation_turn' => 'ha richiesto un prezzo, ma non esiste un listino applicabile.',
            'qualification_limit_reached' => 'mancano dati essenziali dopo il tentativo di qualificazione.',
            'unsupported_whatsapp_message_type' => 'il messaggio WhatsApp ricevuto non è testuale.',
            default => 'è necessaria una valutazione umana.',
        };
    }
}
