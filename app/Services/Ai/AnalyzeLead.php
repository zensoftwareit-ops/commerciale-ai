<?php

namespace App\Services\Ai;

use App\Contracts\LeadAnalyzer;
use App\Models\Activity;
use App\Models\AiAnalysis;
use App\Models\AiRun;
use App\Models\KnowledgeDocument;
use App\Models\Lead;
use App\Models\OrganizationSetting;
use App\Models\PipelineStage;
use App\Models\PromptPolicy;
use App\Models\QualificationProfile;
use App\Models\UsageRecord;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Throwable;

class AnalyzeLead
{
    public function __construct(
        private readonly LeadAnalyzer $analyzer,
        private readonly AnalysisOutputValidator $validator,
        private readonly RuleScorer $ruleScorer,
    ) {}

    public function handle(Lead $lead, ?int $actorId = null): AiAnalysis
    {
        $organizationId = app(TenantContext::class)->requireOrganization()->id;
        $settings = OrganizationSetting::query()->first();
        $profile = QualificationProfile::query()->where('is_active', true)->first();
        $policy = PromptPolicy::query()->where('operation', 'lead_analysis')->where('is_active', true)->latest()->first();
        $knowledge = KnowledgeDocument::query()->where('status', 'active')->latest('updated_at')->limit(20)->get(['id', 'title', 'type', 'content', 'updated_at']);
        $context = [
            'organization' => $settings?->only(['industry', 'business_description', 'products_services', 'ideal_customer', 'pricing_rules', 'exclusion_criteria', 'tone_of_voice']),
            'knowledge' => $knowledge->map->only(['id', 'title', 'type', 'content', 'updated_at'])->all(),
            'policy' => $policy?->only(['version', 'instructions']) ?? ['version' => 'lead-analysis-v1'],
        ];
        $run = AiRun::create([
            'organization_id' => $organizationId,
            'lead_id' => $lead->id,
            'operation' => 'lead_analysis',
            'status' => 'running',
            'input_context' => ['lead' => $lead->only(['id', 'source_label', 'requested_service', 'request_data']), ...$context],
            'started_at' => now(),
        ]);

        try {
            $raw = $this->analyzer->analyze($lead, $context);
            try {
                $output = $this->validator->validate($raw);
            } catch (Throwable) {
                $output = $this->validator->validate($this->validator->repair($raw));
            }
            if (($settings?->completeness ?? 0) < 100) {
                $output['missing_information'][] = 'configurazione_aziendale_incompleta';
                $output['risk_flags'][] = 'organization_profile_incomplete';
            }
            if ($knowledge->isEmpty()) {
                $output['risk_flags'][] = 'knowledge_base_empty';
            }
            $output['missing_information'] = array_values(array_unique($output['missing_information']));
            $output['risk_flags'] = array_values(array_unique($output['risk_flags']));
            $ruleScore = $this->ruleScorer->score($lead, $profile);
            $aiWeight = $profile?->ai_weight ?? 60;
            $ruleWeight = $profile?->rule_weight ?? 40;
            $weightTotal = max(1, $aiWeight + $ruleWeight);
            $finalScore = (int) round((($output['fit_score'] * $aiWeight) + ($ruleScore * $ruleWeight)) / $weightTotal);
            $priority = $finalScore >= 75 ? 'high' : ($finalScore >= 45 ? 'medium' : 'low');
            $meta = $output['_meta'] ?? [];

            return DB::transaction(function () use ($organizationId, $lead, $actorId, $run, $output, $ruleScore, $finalScore, $priority, $meta): AiAnalysis {
                $run->update([
                    'status' => 'completed', 'provider' => $meta['provider'] ?? 'unknown', 'model' => $meta['model'] ?? 'unknown',
                    'policy_version' => $meta['policy_version'] ?? 'unknown', 'output' => $output,
                    'input_units' => $meta['input_units'] ?? 0, 'output_units' => $meta['output_units'] ?? 0,
                    'estimated_cost' => $meta['estimated_cost'] ?? 0, 'completed_at' => now(),
                ]);
                $analysis = AiAnalysis::create([
                    'organization_id' => $organizationId, 'lead_id' => $lead->id, 'ai_run_id' => $run->id,
                    'version' => ((int) AiAnalysis::query()->where('lead_id', $lead->id)->max('version')) + 1,
                    'summary' => $output['summary'], 'intent' => $output['intent'], 'requested_services' => $output['requested_services'],
                    'budget' => $output['budget'], 'urgency' => $output['urgency'], 'ai_score' => $output['fit_score'],
                    'rule_score' => $ruleScore, 'final_score' => $finalScore, 'priority' => $priority,
                    'missing_information' => $output['missing_information'], 'risk_flags' => $output['risk_flags'],
                    'recommended_next_action' => $output['recommended_next_action'], 'qualification_questions' => $output['qualification_questions'],
                    'confidence' => $output['confidence'],
                ]);
                UsageRecord::create([
                    'organization_id' => $organizationId, 'ai_run_id' => $run->id, 'operation' => 'lead_analysis',
                    'provider' => $run->provider, 'model' => $run->model, 'input_units' => $run->input_units,
                    'output_units' => $run->output_units, 'estimated_cost' => $run->estimated_cost, 'occurred_at' => now(),
                ]);
                $reviewStage = PipelineStage::query()->where('slug', 'to_review')->first();
                $lead->update(['score' => $finalScore, 'temperature' => $priority === 'high' ? 'hot' : ($priority === 'medium' ? 'warm' : 'cold'), 'operational_status' => 'needs_action', 'pipeline_stage_id' => $reviewStage?->id ?? $lead->pipeline_stage_id, 'last_activity_at' => now()]);
                Activity::create(['organization_id' => $organizationId, 'lead_id' => $lead->id, 'actor_id' => $actorId, 'type' => 'lead_analyzed', 'title' => 'Analisi AI completata', 'data' => ['analysis_id' => $analysis->id, 'ai_score' => $analysis->ai_score, 'rule_score' => $analysis->rule_score, 'final_score' => $analysis->final_score], 'occurred_at' => now()]);

                return $analysis;
            });
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'error_code' => 'invalid_analysis_output', 'error_message' => mb_substr($exception->getMessage(), 0, 2000), 'completed_at' => now()]);
            throw $exception;
        }
    }
}
