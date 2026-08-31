<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\KnowledgeDocument;
use App\Models\OrganizationSetting;
use App\Models\UsageRecord;
use App\Contracts\SetupWizardGenerator;
use App\Services\Ai\FakeSetupWizardGenerator;
use App\Services\Organizations\WebsiteContentReader;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SetupWizardTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_owner_can_generate_review_and_apply_a_complete_setup(): void
    {
        [$organization, $owner] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        KnowledgeDocument::create([
            'updated_by' => $owner->id,
            'title' => 'Documento manuale',
            'type' => 'text',
            'content' => 'Questo contenuto deve rimanere invariato.',
            'status' => 'active',
        ]);
        KnowledgeDocument::create([
            'updated_by' => $owner->id,
            'title' => 'Vecchi servizi generati',
            'type' => 'service',
            'content' => 'Contenuto da aggiornare.',
            'status' => 'active',
            'source' => 'setup_wizard',
            'source_key' => 'services',
        ]);
        app(TenantContext::class)->clear();

        $session = ['organization_id' => $organization->id];
        $description = str_repeat('Realizziamo servizi digitali per PMI italiane con un processo consulenziale. ', 2);
        $draft = app(FakeSetupWizardGenerator::class)->generate($description);
        $draft['assumptions'] = [];
        $this->mock(SetupWizardGenerator::class)->shouldReceive('generate')->once()->andReturn($draft);
        $response = $this->actingAs($owner)->withSession($session)
            ->post(route('setup-wizard.generate'), ['description' => $description])
            ->assertRedirect(route('setup-wizard.preview'))
            ->assertSessionHasNoErrors();

        $payload = $response->getSession()->get('setup_wizard_draft');
        $this->assertSame($organization->id, $payload['organization_id']);
        $this->actingAs($owner)->withSession([...$session, 'setup_wizard_draft' => $payload])
            ->get(route('setup-wizard.preview'))
            ->assertOk()
            ->assertSee('Controlla prima di applicare');

        $profile = $payload['draft']['profile'];
        $questions = implode("\n", $profile['qualification_questions']);
        unset($profile['qualification_questions']);
        $profile['qualification_questions_text'] = $questions;
        $titles = [
            'services' => 'Servizi e proposta di valore',
            'faq' => 'FAQ commerciali',
            'request_management' => 'Gestione e qualificazione delle richieste',
            'pricing_guidance' => 'Prezzi e preparazione dei preventivi',
        ];
        $knowledge = [];
        foreach ($payload['draft']['knowledge'] as $key => $content) {
            $knowledge[$key] = ['enabled' => '1', 'title' => $titles[$key], 'content' => $content];
        }

        $this->actingAs($owner)->withSession([...$session, 'setup_wizard_draft' => $payload])
            ->post(route('setup-wizard.apply'), [
                'draft_id' => $payload['id'],
                'profile' => $profile,
                'knowledge' => $knowledge,
            ])->assertRedirect(route('settings.organization'))->assertSessionHasNoErrors();

        $settings = OrganizationSetting::withoutGlobalScopes()->where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame(100, $settings->completeness);
        $this->assertFalse($settings->conversation_automation_enabled);
        $this->assertFalse($settings->auto_send_initial_email);
        $this->assertSame(4, KnowledgeDocument::withoutGlobalScopes()->where('organization_id', $organization->id)->where('source', 'setup_wizard')->where('status', 'active')->count());
        $this->assertSame(1, KnowledgeDocument::withoutGlobalScopes()->where('organization_id', $organization->id)->where('source_key', 'services')->count());
        $this->assertDatabaseHas('knowledge_documents', ['organization_id' => $organization->id, 'title' => 'Documento manuale', 'status' => 'active']);
        $this->assertSame('completed', AiRun::withoutGlobalScopes()->where('operation', 'setup_wizard')->firstOrFail()->status);
        $this->assertSame(1, UsageRecord::withoutGlobalScopes()->where('operation', 'setup_wizard')->count());
    }

    public function test_sales_user_cannot_use_the_setup_wizard(): void
    {
        [$organization, $sales] = $this->organizationWithUser('sales');

        $this->actingAs($sales)->withSession(['organization_id' => $organization->id])
            ->get(route('setup-wizard.create'))
            ->assertForbidden();
    }

    public function test_owner_can_generate_the_setup_using_only_a_public_website(): void
    {
        [$organization, $owner] = $this->organizationWithUser();
        $this->mock(WebsiteContentReader::class)
            ->shouldReceive('read')
            ->once()
            ->with('https://example.com')
            ->andReturn([
                'url' => 'https://example.com',
                'pages' => [[
                    'url' => 'https://example.com', 'title' => 'Demo',
                    'text' => str_repeat('Servizi digitali per aziende italiane. ', 4),
                ]],
            ]);

        $response = $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->post(route('setup-wizard.generate'), ['description' => '', 'website_url' => 'https://example.com'])
            ->assertRedirect(route('setup-wizard.preview'))
            ->assertSessionHasNoErrors();

        $payload = $response->getSession()->get('setup_wizard_draft');
        $this->assertSame('https://example.com', $payload['website']['url']);
        $this->assertSame('https://example.com', $payload['draft']['profile']['website_url']);
    }
}
