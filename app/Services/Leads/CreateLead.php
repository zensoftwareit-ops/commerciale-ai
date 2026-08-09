<?php

namespace App\Services\Leads;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateLead
{
    public function handle(array $data, ?int $actorId = null): Lead
    {
        return DB::transaction(function () use ($data, $actorId): Lead {
            $organizationId = app(TenantContext::class)->requireOrganization()->id;
            $stage = PipelineStage::query()->where('slug', 'new')->firstOrFail();
            $lead = Lead::create([
                ...Arr::only($data, ['inbound_source_id', 'external_id', 'source_label', 'name', 'email', 'phone', 'company', 'requested_service', 'request_data', 'consent_data']),
                'organization_id' => $organizationId,
                'email_normalized' => LeadData::normalizeEmail($data['email'] ?? null),
                'phone_normalized' => LeadData::normalizePhone($data['phone'] ?? null),
                'pipeline_stage_id' => $stage->id,
                'operational_status' => 'needs_action',
                'last_activity_at' => now(),
            ]);

            $lead->contacts()->create([
                'organization_id' => $organizationId,
                'name' => $lead->name,
                'email' => $lead->email,
                'email_normalized' => $lead->email_normalized,
                'phone' => $lead->phone,
                'phone_normalized' => $lead->phone_normalized,
                'company' => $lead->company,
                'is_primary' => true,
            ]);

            Activity::create([
                'organization_id' => $organizationId,
                'lead_id' => $lead->id,
                'actor_id' => $actorId,
                'type' => 'lead_created',
                'title' => 'Lead acquisito',
                'data' => ['source' => $lead->source_label],
                'occurred_at' => now(),
            ]);

            return $lead;
        });
    }
}
