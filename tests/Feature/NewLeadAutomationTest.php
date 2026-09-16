<?php

namespace Tests\Feature;

use App\Mail\LeadReplyMail;
use App\Contracts\QuotationEstimator;
use App\Models\CommercialNotification;
use App\Models\Lead;
use App\Models\OrganizationSetting;
use App\Models\PricingRule;
use App\Models\Quotation;
use App\Services\Leads\CreateLead;
use App\Services\Leads\RunNewLeadAutomation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class NewLeadAutomationTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_review_mode_analyzes_a_complete_lead_and_creates_the_pdf_without_email_or_conversation(): void
    {
        Mail::fake();
        Storage::fake('local');
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Noleggio', 'business_description' => 'Mezzi promozionali',
            'products_services' => 'Ape Lineare', 'ideal_customer' => 'Aziende', 'tone_of_voice' => 'professionale',
            'email_signature' => 'Demo', 'auto_analyze_new_leads' => true, 'direct_quote_enabled' => true,
            'quotation_review_mode' => true, 'auto_send_initial_email' => false,
            'conversation_automation_enabled' => false, 'auto_send_quotes_enabled' => false,
            'new_lead_automation_started_at' => now()->subMinute(),
        ]);
        PricingRule::create([
            'name' => 'Ape Lineare', 'keywords' => ['ape lineare'],
            'required_fields' => ['start_date', 'end_date', 'destination', 'accessories'],
            'minimum_price' => 500, 'maximum_price' => 1200, 'validity_days' => 15, 'is_active' => true,
        ]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Federico', 'phone' => '+393331234567', 'requested_service' => 'Ape Lineare',
            'source_label' => 'WPForms', 'request_data' => [
                'Dal giorno' => '12-10-2026', 'Al giorno' => '16-10-2026',
                'Quale località deve raggiungere il mezzo?' => 'Cremona',
                'Quali servizi ti occorrono?' => "Decorazione parziale\nLogistica\nTrasporto",
            ],
        ]);
        app(TenantContext::class)->clear();

        $stats = app(RunNewLeadAutomation::class)->handle();

        $this->assertSame(1, $stats['analyzed']);
        $this->assertSame(1, $stats['drafted']);
        $this->assertSame(0, $stats['sent']);
        $this->assertNotNull($lead->fresh()->initial_automation_completed_at);
        $this->assertSame('awaiting_approval', $lead->fresh()->operational_status);
        $this->assertSame(0, $lead->replies()->withoutGlobalScopes()->count());
        $quotation = Quotation::withoutGlobalScopes()->where('lead_id', $lead->id)->firstOrFail();
        $this->assertNotNull($quotation->pdf_generated_at);
        Storage::disk('local')->assertExists($quotation->pdf_path);
        $this->assertDatabaseHas('commercial_notifications', ['lead_id' => $lead->id, 'type' => 'direct_quote_ready']);
        Mail::assertNothingSent();
    }

    public function test_direct_quote_without_review_stops_an_incomplete_quote_and_assigns_it_to_an_operator_without_sending(): void
    {
        Mail::fake();
        Storage::fake('local');
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Noleggio', 'business_description' => 'Mezzi promozionali',
            'products_services' => 'Ape Lineare', 'ideal_customer' => 'Aziende', 'tone_of_voice' => 'professionale',
            'email_signature' => 'Demo', 'auto_analyze_new_leads' => true, 'direct_quote_enabled' => true,
            'quotation_review_mode' => false, 'new_lead_automation_started_at' => now()->subMinute(),
        ]);
        PricingRule::create([
            'name' => 'Ape Lineare', 'keywords' => ['ape lineare'], 'required_fields' => ['destination'],
            'minimum_price' => 500, 'maximum_price' => 1200, 'validity_days' => 15, 'is_active' => true,
        ]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Caso incompleto', 'email' => 'cliente@example.test',
            'requested_service' => 'Ape Lineare', 'source_label' => 'WPForms',
        ]);
        app(TenantContext::class)->clear();

        $stats = app(RunNewLeadAutomation::class)->handle();

        $this->assertSame(1, $stats['analyzed']);
        $this->assertSame(0, $stats['drafted']);
        $this->assertSame(0, $stats['sent']);
        $this->assertSame('needs_action', $lead->fresh()->operational_status);
        $this->assertNotNull($lead->fresh()->initial_automation_completed_at);
        $this->assertSame(0, $lead->replies()->withoutGlobalScopes()->count());
        $quotation = Quotation::withoutGlobalScopes()->where('lead_id', $lead->id)->firstOrFail();
        $this->assertNull($quotation->pdf_generated_at);
        $this->assertSame(['destination'], $quotation->missing_fields);
        $this->assertSame(1, CommercialNotification::withoutGlobalScopes()->where('lead_id', $lead->id)->where('type', 'direct_quote_operator')->count());
        Mail::assertNothingSent();
    }

    public function test_review_mode_generates_the_pdf_with_missing_executive_details_as_review_warnings(): void
    {
        Mail::fake();
        Storage::fake('local');
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Noleggio', 'business_description' => 'Mezzi promozionali',
            'products_services' => 'Ape Lineare', 'ideal_customer' => 'Aziende', 'tone_of_voice' => 'professionale',
            'email_signature' => 'Demo', 'auto_analyze_new_leads' => true, 'quotation_review_mode' => true,
            'new_lead_automation_started_at' => now()->subMinute(),
        ]);
        PricingRule::create([
            'name' => 'Ape Lineare', 'keywords' => ['ape lineare'], 'required_fields' => ['dettagli decorazione'],
            'minimum_price' => 500, 'maximum_price' => 1200, 'validity_days' => 15, 'is_active' => true,
        ]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Revisione dettagli', 'email' => 'cliente@example.test', 'requested_service' => 'Ape Lineare',
            'source_label' => 'WPForms', 'request_data' => ['Quali servizi ti occorrono?' => 'Decorazione parziale'],
        ]);
        app(TenantContext::class)->clear();

        $stats = app(RunNewLeadAutomation::class)->handle();

        $quotation = Quotation::withoutGlobalScopes()->where('lead_id', $lead->id)->firstOrFail();
        $this->assertSame(1, $stats['drafted']);
        $this->assertSame(['dettagli decorazione'], $quotation->missing_fields);
        $this->assertNotNull($quotation->estimated_price);
        $this->assertNotNull($quotation->pdf_generated_at);
        $this->assertSame('awaiting_approval', $lead->fresh()->operational_status);
        Mail::assertNothingSent();
    }

    public function test_review_mode_generates_a_pdf_even_when_the_ai_estimate_has_low_confidence(): void
    {
        Mail::fake();
        Storage::fake('local');
        $this->app->instance(QuotationEstimator::class, new class implements QuotationEstimator
        {
            public function estimate(array $input): array
            {
                return [
                    'scope_title' => 'Ape Lineare',
                    'scope_description' => 'Noleggio per quattro giorni con trasporto e decorazione parziale.',
                    'deliverables' => ['Noleggio Ape Lineare', 'Trasporto', 'Decorazione parziale'],
                    'assumptions' => ['Disponibilità da confermare in fase di revisione.'],
                    'complexity_score' => 60, 'confidence' => 0.51,
                    'rationale' => 'Il documento deve comunque essere prodotto per la revisione interna.',
                    '_meta' => ['provider' => 'test', 'model' => 'low-confidence', 'input_units' => 0, 'output_units' => 0, 'estimated_cost' => 0],
                ];
            }
        });
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Noleggio', 'business_description' => 'Mezzi promozionali',
            'products_services' => 'Ape Lineare', 'ideal_customer' => 'Aziende', 'tone_of_voice' => 'professionale',
            'email_signature' => 'Demo', 'auto_analyze_new_leads' => true, 'direct_quote_enabled' => true,
            'quotation_review_mode' => true, 'new_lead_automation_started_at' => now()->subMinute(),
        ]);
        PricingRule::create([
            'name' => 'Ape Lineare', 'keywords' => ['ape lineare'], 'required_fields' => ['destination'],
            'minimum_price' => 500, 'maximum_price' => 1200, 'validity_days' => 15, 'is_active' => true,
        ]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Caso completo', 'email' => 'cliente@example.test', 'requested_service' => 'Ape Lineare',
            'source_label' => 'WPForms', 'request_data' => ['Quale località deve raggiungere il mezzo?' => 'Cremona'],
        ]);
        app(TenantContext::class)->clear();

        $stats = app(RunNewLeadAutomation::class)->handle();

        $quotation = Quotation::withoutGlobalScopes()->where('lead_id', $lead->id)->firstOrFail();
        $this->assertSame(1, $stats['drafted']);
        $this->assertSame(51, $quotation->confidence);
        $this->assertNotNull($quotation->pdf_generated_at);
        $this->assertSame('awaiting_approval', $lead->fresh()->operational_status);
        $this->assertStringContainsString('Verificare il preventivo PDF', $lead->analyses()->withoutGlobalScopes()->firstOrFail()->recommended_next_action);
        Mail::assertNothingSent();
    }

    public function test_manual_analysis_honors_review_mode_even_if_the_direct_flag_was_not_persisted(): void
    {
        Mail::fake();
        Storage::fake('local');
        [$organization, $user] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Noleggio', 'business_description' => 'Mezzi promozionali',
            'products_services' => 'Ape Lineare', 'ideal_customer' => 'Aziende', 'tone_of_voice' => 'professionale',
            'email_signature' => 'Demo', 'direct_quote_enabled' => false, 'quotation_review_mode' => true,
        ]);
        PricingRule::create([
            'name' => 'Ape Lineare', 'keywords' => ['ape lineare'], 'required_fields' => [],
            'minimum_price' => 500, 'maximum_price' => 1200, 'validity_days' => 15, 'is_active' => true,
        ]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Analisi manuale', 'email' => 'cliente@example.test',
            'requested_service' => 'Ape Lineare', 'source_label' => 'WPForms',
        ]);
        app(TenantContext::class)->clear();

        $this->actingAs($user)->withSession(['organization_id' => $organization->id])
            ->post(route('leads.analyze', $lead))
            ->assertSessionHas('status', 'Analisi completata e preventivo PDF pronto per la revisione. Nessun messaggio è stato inviato.');

        $quotation = Quotation::withoutGlobalScopes()->where('lead_id', $lead->id)->firstOrFail();
        $this->assertNotNull($quotation->pdf_generated_at);
        $this->assertSame(0, $lead->replies()->withoutGlobalScopes()->count());
        Mail::assertNothingSent();
    }

    public function test_direct_quote_prefers_the_vehicle_selected_in_the_form_over_generic_shared_terms(): void
    {
        Mail::fake();
        Storage::fake('local');
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Noleggio', 'business_description' => 'Street food',
            'products_services' => 'Mezzi street food', 'ideal_customer' => 'Aziende', 'tone_of_voice' => 'professionale',
            'email_signature' => 'Demo', 'auto_analyze_new_leads' => true, 'quotation_review_mode' => true,
            'new_lead_automation_started_at' => now()->subMinute(),
        ]);
        PricingRule::create(['name' => 'Ape Lineare', 'keywords' => ['noleggio street food', 'ape lineare'], 'required_fields' => [], 'minimum_price' => 500, 'maximum_price' => 1200, 'is_active' => true]);
        PricingRule::create(['name' => 'Ape Vetrina', 'keywords' => ['noleggio street food', 'ape vetrina'], 'required_fields' => [], 'minimum_price' => 800, 'maximum_price' => 1500, 'is_active' => true]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Selezione prodotto', 'email' => 'cliente@example.test', 'requested_service' => 'Noleggio street food',
            'source_label' => 'WPForms', 'request_data' => ['Quale mezzo ti occorre?' => 'Ape Lineare'],
        ]);
        app(TenantContext::class)->clear();

        app(RunNewLeadAutomation::class)->handle();

        $quotation = Quotation::withoutGlobalScopes()->where('lead_id', $lead->id)->firstOrFail();
        $this->assertSame('Ape Lineare', $quotation->rule()->withoutGlobalScopes()->firstOrFail()->name);
        $this->assertNotNull($quotation->pdf_generated_at);
        Mail::assertNothingSent();
    }

    public function test_it_analyzes_all_new_leads_but_sends_only_to_internal_allowed_leads(): void
    {
        config()->set('mail.default', 'smtp');
        Mail::fake();
        [$organization] = $this->organizationWithUser();
        $this->mailboxFor($organization);
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti web',
            'products_services' => 'Siti web', 'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale',
            'email_signature' => 'Demo', 'conversation_automation_enabled' => true,
            'auto_analyze_new_leads' => true, 'auto_send_initial_email' => true,
            'internal_test_only' => true, 'automation_allowed_recipients' => ['internal@example.test'],
            'max_automatic_replies' => 3, 'new_lead_automation_started_at' => now()->subMinute(),
        ]);
        $internal = app(CreateLead::class)->handle(['name' => 'Test interno', 'email' => 'internal@example.test', 'requested_service' => 'Sito web', 'source_label' => 'web']);
        $external = app(CreateLead::class)->handle(['name' => 'Cliente esterno', 'email' => 'external@example.test', 'requested_service' => 'Sito web', 'source_label' => 'web']);
        app(TenantContext::class)->clear();

        $stats = app(RunNewLeadAutomation::class)->handle();

        $this->assertSame(2, $stats['candidates']);
        $this->assertSame(2, $stats['analyzed']);
        $this->assertSame(2, $stats['drafted']);
        $this->assertSame(1, $stats['sent']);
        $this->assertNotNull($internal->fresh()->initial_automation_completed_at);
        $this->assertNotNull($external->fresh()->initial_automation_completed_at);
        $this->assertDatabaseHas('lead_replies', [
            'lead_id' => $external->id,
            'status' => 'draft',
            'automation_eligible' => false,
        ]);
        Mail::assertSent(LeadReplyMail::class, fn ($mail) => $mail->hasTo('internal@example.test'));
        Mail::assertNotSent(LeadReplyMail::class, fn ($mail) => $mail->hasTo('external@example.test'));
    }

    public function test_it_does_not_process_leads_created_before_automation_was_enabled(): void
    {
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        $oldLead = app(CreateLead::class)->handle(['name' => 'Storico', 'email' => 'internal@example.test', 'source_label' => 'web']);
        $oldLead->forceFill(['created_at' => now()->subHour()])->save();
        OrganizationSetting::create([
            'conversation_automation_enabled' => true, 'auto_analyze_new_leads' => true,
            'auto_send_initial_email' => true, 'internal_test_only' => true,
            'automation_allowed_recipients' => ['internal@example.test'], 'max_automatic_replies' => 3,
            'new_lead_automation_started_at' => now(),
        ]);
        app(TenantContext::class)->clear();

        $stats = app(RunNewLeadAutomation::class)->handle();

        $this->assertSame(0, $stats['candidates']);
        $this->assertSame(0, $oldLead->analyses()->withoutGlobalScopes()->count());
    }

    public function test_initial_analysis_and_email_do_not_require_followup_conversation_automation(): void
    {
        config()->set('mail.default', 'smtp');
        Mail::fake();
        [$organization] = $this->organizationWithUser();
        $this->mailboxFor($organization);
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti web',
            'products_services' => 'Siti web', 'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale',
            'email_signature' => 'Demo', 'conversation_automation_enabled' => false,
            'auto_analyze_new_leads' => true, 'auto_send_initial_email' => true,
            'internal_test_only' => true, 'automation_allowed_recipients' => ['internal@example.test'],
            'max_automatic_replies' => 3, 'new_lead_automation_started_at' => now()->subMinute(),
        ]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Test indipendente', 'email' => 'internal@example.test',
            'requested_service' => 'Sito web', 'source_label' => 'web',
        ]);
        app(TenantContext::class)->clear();

        $stats = app(RunNewLeadAutomation::class)->handle();

        $this->assertSame(1, $stats['analyzed']);
        $this->assertSame(1, $stats['sent']);
        $this->assertNotNull($lead->fresh()->initial_automation_completed_at);
        Mail::assertSent(LeadReplyMail::class, fn ($mail) => $mail->hasTo('internal@example.test'));
    }

    public function test_server_internal_mode_uses_allowlist_even_when_organization_internal_checkbox_is_off(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('commerciale-ai.automation.external_send_enabled', false);
        Mail::fake();
        [$organization] = $this->organizationWithUser();
        $this->mailboxFor($organization);
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti web',
            'products_services' => 'Siti web', 'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale',
            'email_signature' => 'Demo', 'conversation_automation_enabled' => false,
            'auto_analyze_new_leads' => true, 'auto_send_initial_email' => true,
            'internal_test_only' => false, 'automation_allowed_recipients' => ['internal@example.test'],
            'max_automatic_replies' => 3, 'new_lead_automation_started_at' => now()->subMinute(),
        ]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Test allowlist server', 'email' => 'internal@example.test',
            'requested_service' => 'Sito web', 'source_label' => 'web',
        ]);
        app(TenantContext::class)->clear();

        $stats = app(RunNewLeadAutomation::class)->handle();

        $this->assertSame(1, $stats['sent']);
        $this->assertDatabaseHas('lead_replies', [
            'lead_id' => $lead->id,
            'status' => 'sent',
            'delivery_mode' => 'automatic',
        ]);
        Mail::assertSent(LeadReplyMail::class, fn ($mail) => $mail->hasTo('internal@example.test'));
    }

    public function test_explicit_lead_retry_reuses_and_sends_a_previously_blocked_initial_draft(): void
    {
        config()->set('mail.default', 'smtp');
        Mail::fake();
        [$organization] = $this->organizationWithUser();
        $this->mailboxFor($organization);
        app(TenantContext::class)->set($organization);
        $settings = OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti web',
            'products_services' => 'Siti web', 'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale',
            'email_signature' => 'Demo', 'conversation_automation_enabled' => false,
            'auto_analyze_new_leads' => true, 'auto_send_initial_email' => true,
            'internal_test_only' => true, 'automation_allowed_recipients' => [],
            'max_automatic_replies' => 3, 'new_lead_automation_started_at' => now()->subMinute(),
        ]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Test recupero', 'email' => 'internal@example.test',
            'requested_service' => 'Sito web', 'source_label' => 'web',
        ]);
        app(TenantContext::class)->clear();

        $firstRun = app(RunNewLeadAutomation::class)->handle();
        $this->assertSame(0, $firstRun['sent']);
        $this->assertSame(1, $lead->replies()->withoutGlobalScopes()->count());

        app(TenantContext::class)->set($organization);
        $settings->update(['automation_allowed_recipients' => ['internal@example.test']]);
        app(TenantContext::class)->clear();

        $retry = app(RunNewLeadAutomation::class)->handle(25, $lead->id);

        $this->assertSame(1, $retry['sent']);
        $this->assertSame(1, $lead->replies()->withoutGlobalScopes()->count());
        $this->assertDatabaseHas('lead_replies', [
            'lead_id' => $lead->id,
            'status' => 'sent',
            'delivery_mode' => 'automatic',
        ]);
    }
}
