<?php

namespace App\Services\Leads;

use App\Models\InboundSource;
use App\Models\Lead;
use App\Models\WebhookReceipt;
use Illuminate\Support\Facades\DB;

class IngestInboundLead
{
    public function __construct(private readonly CreateLead $creator) {}

    /** @return array{status: string, lead: Lead, already_processed: bool} */
    public function handle(InboundSource $source, array $data, string $idempotencyKey, string $payloadHash, array $validation = []): array
    {
        return DB::transaction(function () use ($source, $data, $idempotencyKey, $payloadHash, $validation): array {
            if ($receipt = WebhookReceipt::query()->where('inbound_source_id', $source->id)->where('idempotency_key', $idempotencyKey)->first()) {
                return [
                    'status' => 'already_processed',
                    'lead' => Lead::query()->findOrFail($receipt->lead_id),
                    'already_processed' => true,
                ];
            }

            $email = LeadData::normalizeEmail($data['email'] ?? null);
            $phone = LeadData::normalizePhone($data['phone'] ?? null);
            $externalId = $data['external_id'] ?? null;
            $lead = null;
            if ($externalId || $email || $phone) {
                $lead = Lead::query()->where(function ($query) use ($source, $externalId, $email, $phone): void {
                    if ($externalId) {
                        $query->orWhere(fn ($query) => $query->where('inbound_source_id', $source->id)->where('external_id', $externalId));
                    }
                    if ($email) {
                        $query->orWhere('email_normalized', $email);
                    }
                    if ($phone) {
                        $query->orWhere('phone_normalized', $phone);
                    }
                })->first();
            }

            $lead ??= $this->creator->handle([
                ...$data,
                'inbound_source_id' => $source->id,
                'source_label' => $data['source_label'] ?? $source->name,
            ]);
            $status = $lead->wasRecentlyCreated ? 'created' : 'deduplicated';

            WebhookReceipt::create([
                'inbound_source_id' => $source->id,
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'source_domain' => $validation['domain'] ?? null,
                'validation_mode' => $validation['mode'] ?? null,
                'status' => $status,
                'lead_id' => $lead->id,
                'processed_at' => now(),
            ]);

            return ['status' => $status, 'lead' => $lead, 'already_processed' => false];
        });
    }
}
