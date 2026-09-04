<?php

namespace App\Services\Mail;

use App\Mail\LeadReplyMail;
use App\Models\Activity;
use App\Models\LeadReply;
use App\Models\MailboxAccount;
use App\Models\OrganizationSetting;
use App\Models\Quotation;
use App\Models\WhatsappAccount;
use App\Models\WhatsappMessage;
use App\Services\Whatsapp\WhatsappCloudApi;
use App\Services\Quotations\QuotationPdfGenerator;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendLeadReply
{
    public function __construct(
        private readonly MailIdentity $identities,
        private readonly WhatsappCloudApi $whatsapp,
        private readonly QuotationPdfGenerator $quotationPdfs,
    ) {}

    public function handle(LeadReply $reply, ?int $actorId = null, bool $automatic = false): void
    {
        if ($reply->status === 'sent') throw new RuntimeException('Questa risposta è già stata inviata.');
        $isWhatsapp = $reply->channel === 'whatsapp';
        $identity = null;
        $whatsappAccount = null;
        if ($isWhatsapp) {
            $whatsappAccount = WhatsappAccount::query()->where('is_active', true)->first();
            if (! $whatsappAccount) throw new RuntimeException('Configura e attiva WhatsApp Daria prima dell’invio.');
        } else {
            if (in_array(config('mail.default'), ['log', 'array'], true)) throw new RuntimeException('Configura un servizio SMTP reale prima dell’invio.');
            $identity = $this->identities->commercialForOrganization($reply->organization_id);
        }
        if ($automatic) $this->guardAutomaticSend($reply, $identity['mailbox'] ?? null, $whatsappAccount);

        $claimed = LeadReply::query()->whereKey($reply->id)->where('status', 'draft')->update(['status' => 'sending']);
        if ($claimed !== 1) throw new RuntimeException('La bozza è già in elaborazione o non è più inviabile.');
        $reply->status = 'sending';
        $reply->sender_address = $isWhatsapp ? $whatsappAccount->display_phone_number : $identity['from']->address;
        $reply->sender_name = $isWhatsapp ? $whatsappAccount->name : $identity['from']->name;
        $reply->reply_to_address = $isWhatsapp ? null : $identity['reply_to']->address;
        $reply->saveQuietly();

        try {
            if ($isWhatsapp) {
                $messageId = $reply->outbound_message_id ?: $this->whatsapp->sendText($whatsappAccount, $reply);
                if (! $reply->outbound_message_id) {
                    $reply->outbound_message_id = $messageId;
                    $reply->saveQuietly();
                }
                WhatsappMessage::firstOrCreate(['external_message_id' => $messageId], [
                    'whatsapp_account_id' => $whatsappAccount->id, 'lead_id' => $reply->lead_id, 'lead_reply_id' => $reply->id,
                    'direction' => 'outbound', 'type' => 'text', 'status' => 'sent',
                    'from_number' => $whatsappAccount->display_phone_number, 'to_number' => $reply->recipient,
                    'body' => $reply->body, 'sent_at' => now(), 'processed_at' => now(),
                ]);
            } else {
                $reply->ensureOutboundMessageId();
                $quotation = str_contains($reply->reply_kind, 'quotation') ? $reply->quotation : null;
                $pdfPath = $quotation ? $this->quotationPdfs->ensure($quotation) : null;
                $pdfFilename = $quotation ? $this->quotationPdfs->filename($quotation) : null;
                Mail::to($reply->recipient)->send(new LeadReplyMail($reply, $pdfPath, $pdfFilename));
            }
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
            'type' => $isWhatsapp ? 'whatsapp_sent' : 'email_sent',
            'title' => $automatic ? 'Risposta inviata automaticamente via '.($isWhatsapp ? 'WhatsApp' : 'email') : ($isWhatsapp ? 'Messaggio WhatsApp approvato e inviato' : 'Email approvata e inviata'),
            'data' => ['reply_id' => $reply->id, 'recipient' => $reply->recipient, 'subject' => $reply->subject, 'channel' => $reply->channel, 'delivery_mode' => $automatic ? 'automatic' : 'manual'],
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

    private function guardAutomaticSend(LeadReply $reply, ?MailboxAccount $mailbox, ?WhatsappAccount $whatsappAccount): void
    {
        if (! $reply->automation_eligible) throw new RuntimeException('La bozza non supera i controlli per l’invio automatico.');
        $settings = OrganizationSetting::query()->first();
        $isInitial = str_starts_with($reply->reply_kind, 'initial');
        if (! $isInitial && ! $settings?->conversation_automation_enabled) throw new RuntimeException('Automazione conversazioni disabilitata.');
        $lead = $reply->lead;
        if ($reply->channel === 'whatsapp') {
            if (! $whatsappAccount?->auto_reply_enabled) throw new RuntimeException('Risposte automatiche WhatsApp disabilitate.');
            $internalOnly = $whatsappAccount->internal_test_only || ! config('services.whatsapp.beta_external_send_enabled');
            $allowed = collect($whatsappAccount->allowed_recipients ?? [])->map(fn ($number) => preg_replace('/\D+/', '', (string) $number));
            $recipient = preg_replace('/\D+/', '', $reply->recipient);
            if ($internalOnly && ! $allowed->contains($recipient)) throw new RuntimeException('Numero non incluso nella lista test WhatsApp.');
        } else {
        $allowed = collect($settings->automation_allowed_recipients ?? [])->map(fn ($email) => mb_strtolower(trim((string) $email)));
        $internalOnly = $settings->internal_test_only || ! config('commerciale-ai.automation.external_send_enabled');
        if ($internalOnly && ! $allowed->contains($lead->email_normalized)) throw new RuntimeException('Destinatario non incluso nella lista interna.');
        if (! $internalOnly && $mailbox?->domain_verification_status !== 'verified') throw new RuntimeException('Il dominio mittente non è ancora verificato per gli invii automatici esterni.');
        }
        if ($lead->replies()->where('delivery_mode', 'automatic')->where('status', 'sent')->count() >= $settings->max_automatic_replies) throw new RuntimeException('Limite di risposte automatiche raggiunto.');

        if ($isInitial && ! $settings->auto_send_initial_email) throw new RuntimeException('Invio automatico della prima email disabilitato.');
        if ($isInitial && ! $settings->auto_analyze_new_leads) throw new RuntimeException('Automazione dei nuovi lead disabilitata.');
        if (str_contains($reply->reply_kind, 'quotation')) {
            if (! $settings->auto_send_quotes_enabled) throw new RuntimeException('Invio automatico preventivi disabilitato.');
            $quotation = Quotation::query()->where('lead_reply_id', $reply->id)->first();
            if (! $quotation || ! $quotation->auto_send_eligible) throw new RuntimeException('Preventivo non idoneo all’invio automatico.');
            if ($settings->max_auto_quote_amount === null || (float) ($quotation->estimated_price ?? $quotation->maximum_price) > (float) $settings->max_auto_quote_amount) throw new RuntimeException('Preventivo oltre la soglia automatica corrente.');
        }
    }
}
