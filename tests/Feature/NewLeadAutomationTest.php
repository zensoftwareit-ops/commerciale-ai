<?php

namespace Tests\Feature;

use App\Mail\LeadReplyMail;
use App\Models\Lead;
use App\Models\OrganizationSetting;
use App\Services\Leads\CreateLead;
use App\Services\Leads\RunNewLeadAutomation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

class NewLeadAutomationTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

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
}
