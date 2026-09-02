<?php

namespace App\Services\Whatsapp;

use App\Models\LeadReply;
use App\Models\WhatsappAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsappCloudApi
{
    /** @return array<string, mixed> */
    public function inspect(WhatsappAccount $account): array
    {
        return (array) $this->successful($this->client($account)->get('/'.$account->phone_number_id, [
            'fields' => 'id,display_phone_number,verified_name,quality_rating,platform_type',
        ]))->json();
    }

    public function sendText(WhatsappAccount $account, LeadReply $reply): string
    {
        $recipient = preg_replace('/\D+/', '', $reply->recipient);
        if (blank($recipient)) throw new RuntimeException('Il destinatario WhatsApp non è valido.');

        $response = $this->successful($this->client($account)->post('/'.$account->phone_number_id.'/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $reply->body],
        ]));
        $messageId = (string) $response->json('messages.0.id');
        if ($messageId === '') throw new RuntimeException('Meta non ha restituito l’identificativo del messaggio WhatsApp.');

        return $messageId;
    }

    private function client(WhatsappAccount $account): PendingRequest
    {
        $base = rtrim((string) config('services.whatsapp.graph_url'), '/').'/'.trim((string) config('services.whatsapp.graph_version'), '/');

        return Http::baseUrl($base)->withToken($account->access_token)->acceptJson()->asJson()
            ->timeout((int) config('services.whatsapp.api_timeout', 20));
    }

    private function successful(Response $response): Response
    {
        if ($response->successful()) return $response;
        $message = (string) ($response->json('error.message') ?: $response->body());
        throw new RuntimeException('WhatsApp Cloud API ha rifiutato la richiesta: '.mb_substr(strip_tags($message), 0, 1000).' (HTTP '.$response->status().')');
    }
}
