<?php

namespace Tests\Feature;

use App\Contracts\LeadAnalyzer;
use App\Models\AiAnalysis;
use App\Models\AiRun;
use App\Models\KnowledgeDocument;
use App\Models\Lead;
use App\Models\OrganizationSetting;
use App\Models\PipelineStage;
use App\Models\QualificationProfile;
use App\Models\UsageRecord;
use App\Services\Ai\AnalyzeLead;
use App\Services\Ai\RuleScorer;
use App\Services\Leads\CreateLead;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

class SprintTwoAnalysisTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_owner_can_complete_company_profile_and_manage_knowledge(): void
    {
        [$organization, $user] = $this->organizationWithUser();
        $this->actingAs($user)->withSession(['organization_id' => $organization->id])->put(route('settings.organization.update'), [
            'commercial_name' => 'Azienda Demo', 'industry' => 'Software', 'business_description' => 'Sviluppo software per PMI.',
            'products_services' => 'Applicazioni e siti web.', 'ideal_customer' => 'PMI italiane.', 'tone_of_voice' => 'diretto',
            'email_signature' => 'Team Demo', 'qualification_questions_text' => "Budget?\nTempistiche?",
        ])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($organization);
        $this->assertSame(100, OrganizationSetting::query()->firstOrFail()->completeness);
        app(TenantContext::class)->clear();

        $this->actingAs($user)->withSession(['organization_id' => $organization->id])->post(route('knowledge.store'), ['title' => 'Servizio web', 'type' => 'service', 'content' => 'Siti web per PMI.', 'status' => 'active'])->assertRedirect(route('knowledge.index'));
        $this->assertSame(1, KnowledgeDocument::withoutGlobalScopes()->where('organization_id', $organization->id)->count());
    }

    public function test_analysis_persists_mixed_scores_usage_and_timeline(): void
    {
        [$organization, $user] = $this->organizationWithUser('sales');
        app(TenantContext::class)->set($organization);
        PipelineStage::create(['name' => 'Da valutare', 'slug' => 'to_review', 'system_category' => 'open', 'position' => 2]);
        QualificationProfile::create(['rules' => RuleScorer::DEFAULT_RULES, 'ai_weight' => 60, 'rule_weight' => 40, 'is_active' => true]);
        $lead = app(CreateLead::class)->handle(['name' => 'Anna Demo', 'email' => 'anna@example.test', 'requested_service' => 'Sito web', 'source_label' => 'manual', 'request_data' => ['message' => 'Vorrei un sito', 'budget' => '2000 EUR'], 'consent_data' => ['privacy_accepted' => true]]);
        app(TenantContext::class)->clear();

        $this->actingAs($user)->withSession(['organization_id' => $organization->id])->post(route('leads.analyze', $lead))->assertSessionHasNoErrors();
        $analysis = AiAnalysis::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(70, $analysis->ai_score);
        $this->assertGreaterThan(0, $analysis->rule_score);
        $this->assertSame('completed', AiRun::withoutGlobalScopes()->firstOrFail()->status);
        $this->assertSame(1, UsageRecord::withoutGlobalScopes()->count());
        $this->assertContains('organization_profile_incomplete', $analysis->risk_flags);
        $this->assertContains('knowledge_base_empty', $analysis->risk_flags);
        $this->assertSame('to_review', Lead::withoutGlobalScopes()->findOrFail($lead->id)->stage()->withoutGlobalScopes()->first()->slug);
    }

    public function test_invalid_provider_output_is_recorded_as_failure(): void
    {
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        $lead = app(CreateLead::class)->handle(['name' => 'Output non valido', 'source_label' => 'manual']);
        app()->instance(LeadAnalyzer::class, new class implements LeadAnalyzer
        {
            public function analyze(Lead $lead, array $context = []): array
            {
                return ['summary' => 'incompleto'];
            }
        });

        $this->expectException(ValidationException::class);
        try {
            app(AnalyzeLead::class)->handle($lead);
        } finally {
            $this->assertSame('failed', AiRun::withoutGlobalScopes()->firstOrFail()->status);
        }
    }
}
