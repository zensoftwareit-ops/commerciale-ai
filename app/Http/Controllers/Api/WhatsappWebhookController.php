<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappAccount;
use App\Models\WhatsappMessage;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsappWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $expected = (string) config('services.whatsapp.webhook_verify_token');
        $provided = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));
        abort_if($expected === '' || $mode !== 'subscribe' || ! hash_equals($expected, $provided), 403);

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $secret = (string) config('services.whatsapp.app_secret');
        abort_if($secret === '', 503, 'WHATSAPP_APP_SECRET non configurato.');
        $signature = (string) $request->header('X-Hub-Signature-256');
        abort_unless($signature !== '' && hash_equals('sha256='.hash_hmac('sha256', $raw, $secret), $signature), 401);

        $stored = 0;
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                $value = (array) ($change['value'] ?? []);
                $phoneNumberId = (string) data_get($value, 'metadata.phone_number_id', '');
                $account = WhatsappAccount::withoutGlobalScopes()->where('phone_number_id', $phoneNumberId)->where('is_active', true)->first();
                if (! $account) continue;
                app(TenantContext::class)->set($account->organization);
                try {
                    foreach ((array) ($value['messages'] ?? []) as $message) {
                        $externalId = (string) ($message['id'] ?? '');
                        if ($externalId === '') continue;
                        $type = (string) ($message['type'] ?? 'unknown');
                        $body = $type === 'text' ? (string) data_get($message, 'text.body', '') : '[Messaggio '.$type.' non ancora supportato]';
                        $contactName = collect((array) ($value['contacts'] ?? []))->firstWhere('wa_id', $message['from'] ?? null);
                        $message['_contact_name'] = data_get($contactName, 'profile.name');
                        $record = WhatsappMessage::query()->firstOrCreate(['external_message_id' => $externalId], [
                            'whatsapp_account_id' => $account->id, 'direction' => 'inbound', 'type' => $type,
                            'status' => 'pending', 'from_number' => (string) ($message['from'] ?? ''),
                            'to_number' => $account->display_phone_number, 'body' => $body, 'payload' => $message,
                            'received_at' => isset($message['timestamp']) ? now()->setTimestamp((int) $message['timestamp']) : now(),
                        ]);
                        if ($record->wasRecentlyCreated) $stored++;
                    }
                    foreach ((array) ($value['statuses'] ?? []) as $status) {
                        WhatsappMessage::query()->where('external_message_id', (string) ($status['id'] ?? ''))->update([
                            'status' => (string) ($status['status'] ?? 'unknown'),
                            'failed_at' => ($status['status'] ?? null) === 'failed' ? now() : null,
                            'last_error' => data_get($status, 'errors.0.title'),
                        ]);
                    }
                } finally {
                    app(TenantContext::class)->clear();
                }
            }
        }

        return response()->json(['received' => true, 'stored' => $stored]);
    }
}
