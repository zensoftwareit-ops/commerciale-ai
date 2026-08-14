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

class QuotationAutomationTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

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
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Web', 'business_description' => 'Siti', 'products_services' => 'Siti web',
            'ideal_customer' => 'PMI', 'tone_of_voice' => 'professionale', 'email_signature' => 'Demo',
            'conversation_automation_enabled' => true, 'auto_send_quotes_enabled' => true, 'internal_test_only' => true,
            'automation_allowed_recipients' => ['anna@example.test'], 'max_automatic_replies' => 2, 'max_auto_quote_amount' => 3000,
        ]);
        PricingRule::create(['name' => 'Sito vetrina', 'keywords' => ['sito web'], 'required_fields' => ['pages'], 'minimum_price' => 1500, 'maximum_price' => 2200, 'validity_days' => 15]);
        $lead = app(CreateLead::class)->handle(['name' => 'Anna', 'email' => 'anna@example.test', 'requested_service' => 'Sito web', 'source_label' => 'manual', 'request_data' => ['pages' => 5]]);
        $reply = app(GenerateLeadReply::class)->handle($lead, app(AnalyzeLead::class)->handle($lead));
        app(TenantContext::class)->clear();

        $this->assertTrue($reply->automation_eligible);
        $stats = app(RunConversationAutomation::class)->handle();
        $this->assertSame(1, $stats['sent']);
        $this->assertSame('automatic', $reply->fresh()->delivery_mode);
        Mail::assertSent(LeadReplyMail::class, fn ($mail) => $mail->hasTo('anna@example.test'));
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
        $reply = app(GenerateLeadReply::class)->handle($lead, app(AnalyzeLead::class)->handle($lead));

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
}

