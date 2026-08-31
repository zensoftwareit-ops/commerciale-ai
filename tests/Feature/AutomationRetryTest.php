<?php

namespace Tests\Feature;

use App\Models\LeadReply;
use App\Models\OrganizationSetting;
use App\Services\Leads\CreateLead;
use App\Services\Mail\RunConversationAutomation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AutomationRetryTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_failed_automatic_delivery_stops_after_three_attempts_and_notifies_a_human(): void
    {
        config()->set('mail.default', 'log');
        config()->set('commerciale-ai.automation.delivery_max_attempts', 3);
        config()->set('commerciale-ai.automation.retry_base_minutes', 1);
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create(['conversation_automation_enabled' => true, 'internal_test_only' => true, 'max_automatic_replies' => 3]);
        $lead = app(CreateLead::class)->handle(['name' => 'Lead retry', 'email' => 'retry@example.test', 'source_label' => 'test']);
        $reply = LeadReply::create([
            'lead_id' => $lead->id, 'status' => 'draft', 'reply_kind' => 'general',
            'automation_eligible' => true, 'automation_blockers' => [],
            'recipient' => $lead->email, 'subject' => 'Test', 'body' => 'Test',
        ]);
        app(TenantContext::class)->clear();

        app(RunConversationAutomation::class)->handle();
        $this->travel(2)->minutes();
        app(RunConversationAutomation::class)->handle();
        $this->travel(3)->minutes();
        app(RunConversationAutomation::class)->handle();

        $reply->refresh();
        $this->assertSame(3, $reply->automation_attempts);
        $this->assertFalse($reply->automation_eligible);
        $this->assertNotNull($reply->automation_failed_at);
        $this->assertDatabaseHas('commercial_notifications', ['lead_id' => $lead->id, 'type' => 'automation_failed']);
    }
}
