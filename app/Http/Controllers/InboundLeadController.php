<?php

namespace App\Http\Controllers;

use App\Models\InboundSource;
use App\Models\Lead;
use App\Models\WebhookReceipt;
use App\Services\Leads\CreateLead;
use App\Services\Leads\LeadData;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InboundLeadController extends Controller
{
    public function __invoke(Request $request, CreateLead $creator): JsonResponse
    {
        $source = InboundSource::withoutGlobalScopes()->where('key', $request->header('X-Webhook-Source'))->where('is_active', true)->firstOrFail();
        $timestamp = $request->header('X-Webhook-Timestamp');
        $signature = $request->header('X-Webhook-Signature');
        $idempotencyKey = $request->header('Idempotency-Key');
        abort_unless($timestamp && ctype_digit($timestamp) && abs(now()->timestamp - (int) $timestamp) <= config('commerciale-ai.webhook_replay_window_seconds'), 401, 'Timestamp non valido.');
        abort_unless($idempotencyKey && strlen($idempotencyKey) <= 255, 422, 'Idempotency-Key mancante.');
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $source->secret);
        abort_unless($signature && hash_equals($expected, $signature), 401, 'Firma non valida.');

        app(TenantContext::class)->set($source->organization()->firstOrFail());
        try {
            if ($receipt = WebhookReceipt::query()->where('inbound_source_id', $source->id)->where('idempotency_key', $idempotencyKey)->first()) {
                return response()->json(['status' => 'already_processed', 'lead_id' => $receipt->lead_id], 200);
            }

            $payload = $request->validate([
                'external_id' => ['required', 'string', 'max:255'], 'source' => ['required', 'string', 'max:255'],
                'received_at' => ['required', 'date'], 'contact.name' => ['required', 'string', 'max:255'],
                'contact.email' => ['nullable', 'email', 'max:255'], 'contact.phone' => ['nullable', 'string', 'max:50'],
                'contact.company' => ['nullable', 'string', 'max:255'], 'request' => ['required', 'array'],
                'request.project_type' => ['nullable', 'string', 'max:255'], 'consent' => ['required', 'array'],
                'consent.privacy_accepted' => ['required', 'boolean'], 'consent.marketing_accepted' => ['nullable', 'boolean'],
            ]);
            if (! data_get($payload, 'consent.privacy_accepted')) {
                throw ValidationException::withMessages(['consent.privacy_accepted' => 'Il consenso privacy è obbligatorio.']);
            }

            $lead = DB::transaction(function () use ($payload, $source, $idempotencyKey, $request, $creator): Lead {
                $email = LeadData::normalizeEmail(data_get($payload, 'contact.email'));
                $phone = LeadData::normalizePhone(data_get($payload, 'contact.phone'));
                $lead = Lead::query()->where(fn ($q) => $q
                    ->where(fn ($q) => $q->where('inbound_source_id', $source->id)->where('external_id', $payload['external_id']))
                    ->orWhere(fn ($q) => $email ? $q->where('email_normalized', $email) : $q->whereRaw('1 = 0'))
                    ->orWhere(fn ($q) => $phone ? $q->where('phone_normalized', $phone) : $q->whereRaw('1 = 0'))
                )->first();
                $lead ??= $creator->handle([
                    'inbound_source_id' => $source->id, 'external_id' => $payload['external_id'], 'source_label' => $payload['source'],
                    'name' => data_get($payload, 'contact.name'), 'email' => data_get($payload, 'contact.email'),
                    'phone' => data_get($payload, 'contact.phone'), 'company' => data_get($payload, 'contact.company'),
                    'requested_service' => data_get($payload, 'request.project_type'), 'request_data' => $payload['request'], 'consent_data' => $payload['consent'],
                ]);
                WebhookReceipt::create(['inbound_source_id' => $source->id, 'idempotency_key' => $idempotencyKey, 'payload_hash' => hash('sha256', $request->getContent()), 'status' => $lead->wasRecentlyCreated ? 'created' : 'deduplicated', 'lead_id' => $lead->id, 'processed_at' => now()]);

                return $lead;
            });

            return response()->json(['status' => $lead->wasRecentlyCreated ? 'created' : 'deduplicated', 'lead_id' => $lead->id], $lead->wasRecentlyCreated ? 201 : 200);
        } finally {
            app(TenantContext::class)->clear();
        }
    }
}
