<?php

namespace Tests\Feature;

use App\Mail\LeadReplyMail;
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
}
