<?php

namespace Tests\Feature;

use App\Mail\LeadReplyMail;
use App\Models\InboundEmail;
use App\Models\OrganizationSetting;
use App\Models\PricingRule;
use App\Models\Quotation;
use App\Services\Ai\AnalyzeLead;
use App\Services\Ai\GenerateLeadReply;
use App\Services\Leads\CreateLead;
use App\Services\Mail\RunConversationAutomation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class QuotationAutomationTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_external_automation_is_blocked_until_sender_domain_is_verified(): void
    {
        config()->set('commerciale-ai.automation.external_send_enabled', true);
        [$organization] = $this->organizationWithUser();
        $this->mailboxFor($organization, false);
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti',
            'products_services' => 'Siti web', 'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale',
            'email_signature' => 'Demo', 'conversation_automation_enabled' => true,
            'internal_test_only' => false, 'max_automatic_replies' => 3,
        ]);
        $lead = app(CreateLead::class)->handle(['name' => 'Anna', 'email' => 'anna@example.test', 'source_label' => 'test']);
        $reply = app(GenerateLeadReply::class)->handle($lead, app(AnalyzeLead::class)->handle($lead), null, [
            'incoming_email' => ['message_id' => 'external@example.test'],
        ]);

        $this->assertFalse($reply->automation_eligible);
        $this->assertContains('sender_domain_not_verified', $reply->automation_blockers);
    }

    public function test_it_builds_a_deterministic_quote_but_keeps_it_as_draft_by_default(): void
    {
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create(['commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti', 'products_services' => 'Siti web', 'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale', 'email_signature' => 'Demo']);
        PricingRule::create(['name' => 'Sito vetrina', 'keywords' => ['sito web', 'vetrina'], 'required_fields' => ['pages'], 'minimum_price' => 1500, 'maximum_price' => 2200, 'validity_days' => 15]);
        $lead = app(CreateLead::class)->handle(['name' => 'Anna', 'email' => 'anna@example.test', 'requested_service' => 'Sito web vetrina', 'source_label' => 'manual', 'request_data' => ['pages' => 5]]);
        $analysis = app(AnalyzeLead::class)->handle($lead);
        $reply = app(GenerateLeadReply::class)->handle($lead, $analysis);

        $quote = Quotation::firstOrFail();
        $this->assertSame('1500.00', $quote->minimum_price);
        $this->assertSame('2200.00', $quote->maximum_price);
        $this->assertFalse($quote->auto_send_eligible);
        $this->assertContains('conversation_automation_disabled', $quote->automation_blockers);
        $this->assertSame('quotation', $reply->reply_kind);
        $this->assertFalse($reply->automation_eligible);
    }

    public function test_internal_allowlist_can_enable_and_send_a_fully_reliable_quote(): void
    {
        config()->set('mail.default', 'smtp');
        Mail::fake();
        Storage::fake('local');
        [$organization] = $this->organizationWithUser();
        $this->mailboxFor($organization);
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti', 'products_services' => 'Siti web',
            'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale', 'email_signature' => 'Demo',
            'conversation_automation_enabled' => true, 'auto_send_quotes_enabled' => true, 'internal_test_only' => true,
            'automation_allowed_recipients' => ['anna@example.test'], 'max_automatic_replies' => 2, 'max_auto_quote_amount' => 3000,
            'quotation_primary_color' => '#112233', 'quotation_header_text' => "+39 02 1234567\ninfo@demo.test",
            'quotation_footer_left' => 'info@demo.test', 'quotation_footer_center' => 'Demo Srl | P. IVA 12345678901',
            'quotation_footer_right' => 'www.demo.test',
        ]);
        PricingRule::create(['name' => 'Sito vetrina', 'keywords' => ['sito web'], 'required_fields' => ['pages'], 'minimum_price' => 1500, 'maximum_price' => 2200, 'validity_days' => 15]);
        $lead = app(CreateLead::class)->handle(['name' => 'Anna', 'email' => 'anna@example.test', 'requested_service' => 'Sito web', 'source_label' => 'manual', 'request_data' => ['pages' => 5]]);
        $reply = app(GenerateLeadReply::class)->handle($lead, app(AnalyzeLead::class)->handle($lead), null, [
            'incoming_email' => ['message_id' => 'quote-request@example.test'],
        ]);
        app(TenantContext::class)->clear();

        $this->assertTrue($reply->automation_eligible);
        $stats = app(RunConversationAutomation::class)->handle();
        $this->assertSame(1, $stats['sent']);
        $this->assertSame('automatic', $reply->fresh()->delivery_mode);
        $quote = Quotation::firstOrFail();
        $this->assertNotNull($quote->fresh()->pdf_generated_at);
        Storage::disk('local')->assertExists($quote->fresh()->pdf_path);
        $pdf = Storage::disk('local')->get($quote->fresh()->pdf_path);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('+39 02 1234567', $pdf);
        $this->assertStringContainsString('0.067 0.133 0.200 RG', $pdf);
        Mail::assertSent(LeadReplyMail::class, fn ($mail) => $mail->hasTo('anna@example.test')
            && count($mail->attachments()) === 1
            && $mail->quotationPdfFilename === 'Preventivo-'.$quote->document_number.'.pdf');
    }

    public function test_customer_can_download_only_a_quote_belonging_to_the_selected_lead(): void
    {
        Storage::fake('local');
        [$organization, $user] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti',
            'products_services' => 'Siti web', 'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale',
            'email_signature' => 'Demo', 'quotation_company_details' => 'Demo Srl | P. IVA 12345678901',
        ]);
        PricingRule::create(['name' => 'Sito vetrina', 'keywords' => ['sito web'], 'required_fields' => [], 'minimum_price' => 1500, 'maximum_price' => 2200]);
        $lead = app(CreateLead::class)->handle(['name' => 'Anna', 'email' => 'anna@example.test', 'requested_service' => 'Sito web', 'source_label' => 'manual']);
        app(GenerateLeadReply::class)->handle($lead, app(AnalyzeLead::class)->handle($lead));
        $quote = Quotation::firstOrFail();
        app(TenantContext::class)->clear();

        $response = $this->actingAs($user)->get(route('leads.quotations.pdf', [$lead, $quote]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        Storage::disk('local')->assertExists($quote->fresh()->pdf_path);
    }

    public function test_missing_required_data_creates_an_automatable_question_not_a_quote(): void
    {
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti', 'products_services' => 'Siti web',
            'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale', 'email_signature' => 'Demo',
            'conversation_automation_enabled' => true, 'internal_test_only' => true,
            'automation_allowed_recipients' => ['anna@example.test'], 'max_automatic_replies' => 2,
        ]);
        PricingRule::create(['name' => 'Sito vetrina', 'keywords' => ['sito web'], 'required_fields' => ['pages'], 'minimum_price' => 1500, 'maximum_price' => 2200]);
        $lead = app(CreateLead::class)->handle(['name' => 'Anna', 'email' => 'anna@example.test', 'requested_service' => 'Sito web', 'source_label' => 'manual']);
        $reply = app(GenerateLeadReply::class)->handle($lead, app(AnalyzeLead::class)->handle($lead), null, [
            'incoming_email' => ['message_id' => 'qualification-request@example.test'],
        ]);

        $this->assertSame('qualification', $reply->reply_kind);
        $this->assertTrue($reply->automation_eligible);
        $this->assertStringContainsString('pages', $reply->body);
        $this->assertFalse(Quotation::firstOrFail()->auto_send_eligible);
    }

    public function test_an_internal_general_reply_to_an_inbound_email_can_be_sent_automatically(): void
    {
        config()->set('mail.default', 'smtp');
        Mail::fake();
        [$organization] = $this->organizationWithUser();
        $this->mailboxFor($organization);
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti', 'products_services' => 'Siti web',
            'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale', 'email_signature' => 'Demo',
            'conversation_automation_enabled' => true, 'internal_test_only' => true,
            'automation_allowed_recipients' => ['anna@example.test'], 'max_automatic_replies' => 3,
        ]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Anna', 'email' => 'anna@example.test', 'requested_service' => 'Consulenza',
            'source_label' => 'manual', 'request_data' => ['message' => 'Vorrei informazioni'],
        ]);
        InboundEmail::create([
            'lead_id' => $lead->id, 'status' => 'linked', 'match_confidence' => 'high', 'match_reason' => 'thread_id',
            'message_hash' => hash('sha256', 'general-inbound'), 'message_id' => 'general-inbound@example.test',
            'from_address' => 'anna@example.test', 'subject' => 'Re: Informazioni', 'body' => 'Grazie, mi dica pure.',
            'received_at' => now(), 'linked_at' => now(),
        ]);
        $reply = app(GenerateLeadReply::class)->handle($lead, app(AnalyzeLead::class)->handle($lead), null, [
            'incoming_email' => ['message_id' => 'general-inbound@example.test'],
        ]);
        app(TenantContext::class)->clear();

        $this->assertSame('general', $reply->reply_kind);
        $this->assertTrue($reply->automation_eligible);
        $stats = app(RunConversationAutomation::class)->handle();
        $this->assertSame(1, $stats['sent']);
        $this->assertSame('automatic', $reply->fresh()->delivery_mode);
    }

    public function test_server_internal_mode_recovers_and_sends_an_allowlisted_followup_draft(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('commerciale-ai.automation.external_send_enabled', false);
        Mail::fake();
        [$organization] = $this->organizationWithUser();
        $this->mailboxFor($organization);
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti', 'products_services' => 'Siti web',
            'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale', 'email_signature' => 'Demo',
            'conversation_automation_enabled' => true, 'internal_test_only' => false,
            'automation_allowed_recipients' => ['anna@example.test'], 'max_automatic_replies' => 3,
        ]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Anna', 'email' => 'anna@example.test', 'requested_service' => 'Consulenza',
            'source_label' => 'manual', 'request_data' => ['message' => 'Vorrei informazioni'],
        ]);
        InboundEmail::create([
            'lead_id' => $lead->id, 'status' => 'linked', 'match_confidence' => 'high', 'match_reason' => 'thread_id',
            'message_hash' => hash('sha256', 'server-internal-inbound'), 'message_id' => 'server-internal@example.test',
            'from_address' => 'anna@example.test', 'subject' => 'Re: Informazioni', 'body' => 'Grazie, mi dica pure.',
            'received_at' => now(), 'linked_at' => now(),
        ]);
        $reply = app(GenerateLeadReply::class)->handle($lead, app(AnalyzeLead::class)->handle($lead), null, [
            'incoming_email' => ['message_id' => 'server-internal@example.test'],
        ]);
        $reply->update([
            'automation_eligible' => false,
            'automation_blockers' => ['external_send_disabled_on_server'],
        ]);
        app(TenantContext::class)->clear();

        $stats = app(RunConversationAutomation::class)->handle();

        $this->assertSame(1, $stats['sent']);
        $this->assertSame('automatic', $reply->fresh()->delivery_mode);
        Mail::assertSent(LeadReplyMail::class, fn ($mail) => $mail->hasTo('anna@example.test'));
    }

    public function test_after_one_qualification_attempt_it_offers_an_indicative_range_without_more_questions(): void
    {
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti', 'products_services' => 'Siti web',
            'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale', 'email_signature' => 'Demo',
            'conversation_automation_enabled' => true, 'auto_send_quotes_enabled' => true, 'internal_test_only' => true,
            'automation_allowed_recipients' => ['anna@example.test'], 'max_automatic_replies' => 5, 'max_auto_quote_amount' => 3000,
        ]);
        PricingRule::create(['name' => 'Sito vetrina', 'keywords' => ['sito web'], 'required_fields' => ['pages'], 'minimum_price' => 1500, 'maximum_price' => 2200]);
        $lead = app(CreateLead::class)->handle(['name' => 'Anna', 'email' => 'anna@example.test', 'requested_service' => 'Sito web', 'source_label' => 'manual']);
        $analysis = app(AnalyzeLead::class)->handle($lead);
        $qualification = app(GenerateLeadReply::class)->handle($lead, $analysis);
        $qualification->update(['status' => 'sent', 'delivery_mode' => 'automatic', 'sent_at' => now()]);
        InboundEmail::create([
            'lead_id' => $lead->id, 'status' => 'linked', 'match_confidence' => 'high', 'match_reason' => 'thread_id',
            'message_hash' => hash('sha256', 'evasive-answer'), 'message_id' => 'evasive@example.test',
            'from_address' => 'anna@example.test', 'subject' => 'Re: Preventivo', 'body' => 'Non saprei, mi dia almeno un prezzo.',
            'received_at' => now(), 'linked_at' => now(),
        ]);

        $offer = app(GenerateLeadReply::class)->handle($lead, $analysis, null, [
            'incoming_email' => ['message_id' => 'evasive@example.test', 'subject' => 'Re: Preventivo'],
        ]);

        $this->assertSame('quotation', $offer->reply_kind);
        $this->assertTrue($offer->automation_eligible);
        $this->assertStringContainsString('fascia indicativa', $offer->body);
        $this->assertStringNotContainsString('Può indicare', $offer->body);
    }

    public function test_a_later_customer_clarification_can_match_a_pricing_rule(): void
    {
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti', 'products_services' => 'Siti web',
            'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale', 'email_signature' => 'Demo',
            'conversation_automation_enabled' => true, 'auto_send_quotes_enabled' => true, 'internal_test_only' => true,
            'automation_allowed_recipients' => ['anna@example.test'], 'max_automatic_replies' => 5, 'max_auto_quote_amount' => 5000,
        ]);
        PricingRule::create([
            'name' => 'E-commerce', 'keywords' => ['negozio online'], 'required_fields' => [],
            'minimum_price' => 2500, 'maximum_price' => 4000,
        ]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Anna', 'email' => 'anna@example.test', 'requested_service' => 'Consulenza',
            'source_label' => 'manual', 'request_data' => ['message' => 'Vorrei informazioni'],
        ]);
        $analysis = app(AnalyzeLead::class)->handle($lead);
        $firstReply = app(GenerateLeadReply::class)->handle($lead, $analysis);
        $firstReply->update(['status' => 'sent', 'delivery_mode' => 'automatic', 'sent_at' => now()]);
        InboundEmail::create([
            'lead_id' => $lead->id, 'status' => 'linked', 'match_confidence' => 'high', 'match_reason' => 'thread_id',
            'message_hash' => hash('sha256', 'late-service-detail'), 'message_id' => 'late-detail@example.test',
            'from_address' => 'anna@example.test', 'subject' => 'Re: Richiesta',
            'body' => 'Confermo che mi serve un negozio online e vorrei il preventivo.',
            'received_at' => now(), 'linked_at' => now(),
        ]);

        $reply = app(GenerateLeadReply::class)->handle($lead, $analysis, null, [
            'incoming_email' => [
                'message_id' => 'late-detail@example.test',
                'subject' => 'Re: Richiesta',
                'body' => 'Confermo che mi serve un negozio online e vorrei il preventivo.',
            ],
        ]);

        $this->assertSame('quotation', $reply->reply_kind);
        $this->assertSame('E-commerce', Quotation::latest('created_at')->firstOrFail()->rule->name);
    }

    public function test_a_site_request_matches_a_semantically_equivalent_price_rule(): void
    {
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti', 'products_services' => 'Siti web',
            'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale', 'email_signature' => 'Demo',
            'conversation_automation_enabled' => true, 'auto_send_quotes_enabled' => true, 'internal_test_only' => true,
            'automation_allowed_recipients' => ['anna@example.test'], 'max_automatic_replies' => 5, 'max_auto_quote_amount' => 5000,
        ]);
        PricingRule::create([
            'name' => 'Creazione sito web', 'keywords' => ['realizzazione website'], 'required_fields' => [],
            'minimum_price' => 1200, 'maximum_price' => 2000,
        ]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Anna', 'email' => 'anna@example.test', 'requested_service' => 'Presenza digitale',
            'source_label' => 'manual', 'request_data' => ['message' => 'Vorrei un nuovo sito internet per la mia azienda.'],
        ]);

        $reply = app(GenerateLeadReply::class)->handle($lead, app(AnalyzeLead::class)->handle($lead), null, [
            'incoming_email' => [
                'message_id' => 'site-quote@example.test',
                'subject' => 'Re: Richiesta',
                'body' => 'A questo punto vorrei ricevere il preventivo per il sito internet.',
            ],
        ]);

        $this->assertSame('quotation', $reply->reply_kind);
        $this->assertStringContainsString('1.200', $reply->body);
        $this->assertSame('Creazione sito web', Quotation::firstOrFail()->rule->name);
    }
}
