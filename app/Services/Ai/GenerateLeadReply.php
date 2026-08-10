<?php

namespace App\Services\Ai;

use App\Contracts\LeadReplyGenerator;
use App\Models\Activity;
use App\Models\AiAnalysis;
use App\Models\AiRun;
use App\Models\Lead;
use App\Models\LeadReply;
use App\Models\OrganizationSetting;
use App\Models\UsageRecord;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class GenerateLeadReply
{
    public function __construct(private readonly LeadReplyGenerator $generator) {}

    public function handle(Lead $lead, AiAnalysis $analysis, ?int $actorId = null): LeadReply
    {
        $organizationId = app(TenantContext::class)->requireOrganization()->id;
        if (! filled($lead->email)) {
            throw ValidationException::withMessages(['reply' => 'Il lead non ha un indirizzo email valido.']);
        }

        $settings = OrganizationSetting::query()->first();
        $context = ['organization' => $settings?->only([
            'commercial_name', 'business_description', 'products_services', 'tone_of_voice',
            'email_signature', 'appointment_details', 'promised_response_minutes',
        ])];
        $run = AiRun::create([
            'organization_id' => $organizationId,
            'lead_id' => $lead->id,
            'operation' => 'reply_draft',
            'status' => 'running',
            'input_context' => ['analysis_id' => $analysis->id, ...$context],
            'started_at' => now(),
        ]);

        try {
            $result = $this->generator->generate($lead, $analysis, $context);
            validator($result, [
                'subject' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:10000'],
            ])->validate();
            $meta = $result['_meta'] ?? [];

            return DB::transaction(function () use ($organizationId, $lead, $analysis, $actorId, $run, $result, $meta): LeadReply {
                $run->update([
                    'status' => 'completed', 'provider' => $meta['provider'] ?? 'unknown',
                    'model' => $meta['model'] ?? 'unknown', 'policy_version' => $meta['policy_version'] ?? 'reply-draft-v1',
                    'output' => ['subject' => $result['subject'], 'body' => $result['body']],
                    'input_units' => $meta['input_units'] ?? 0, 'output_units' => $meta['output_units'] ?? 0,
                    'estimated_cost' => $meta['estimated_cost'] ?? 0, 'completed_at' => now(),
                ]);
                $reply = LeadReply::create([
                    'organization_id' => $organizationId, 'lead_id' => $lead->id,
                    'ai_analysis_id' => $analysis->id, 'ai_run_id' => $run->id,
                    'status' => 'draft', 'recipient' => $lead->email,
                    'subject' => $result['subject'], 'body' => $result['body'],
                ]);
                UsageRecord::create([
                    'organization_id' => $organizationId, 'ai_run_id' => $run->id,
                    'operation' => 'reply_draft', 'provider' => $run->provider, 'model' => $run->model,
                    'input_units' => $run->input_units, 'output_units' => $run->output_units,
                    'estimated_cost' => $run->estimated_cost, 'occurred_at' => now(),
                ]);
                $lead->update(['operational_status' => 'awaiting_approval', 'last_activity_at' => now()]);
                Activity::create([
                    'organization_id' => $organizationId, 'lead_id' => $lead->id, 'actor_id' => $actorId,
                    'type' => 'reply_draft_created', 'title' => 'Bozza email preparata',
                    'data' => ['reply_id' => $reply->id, 'analysis_id' => $analysis->id], 'occurred_at' => now(),
                ]);

                return $reply;
            });
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed', 'error_code' => 'reply_generation_failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000), 'completed_at' => now(),
            ]);
            throw $exception;
        }
    }
}
