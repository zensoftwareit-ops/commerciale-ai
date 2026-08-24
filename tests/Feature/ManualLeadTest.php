<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\LeadContact;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ManualLeadTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_sales_user_can_create_a_manual_lead(): void
    {
        [$organization, $user] = $this->organizationWithUser('sales');
        $response = $this->actingAs($user)->withSession(['organization_id' => $organization->id])->post(route('leads.store'), ['name' => 'Anna Bianchi', 'email' => ' ANNA@example.test ', 'requested_service' => 'Sito web', 'message' => 'Contattatemi']);
        $lead = Lead::withoutGlobalScopes()->firstOrFail();
        $response->assertRedirect(route('leads.show', $lead));
        $this->assertSame('anna@example.test', $lead->email_normalized);
        $this->assertSame($organization->id, $lead->organization_id);
        $this->assertSame(1, Activity::withoutGlobalScopes()->where('lead_id', $lead->id)->count());
        $this->assertSame(1, LeadContact::withoutGlobalScopes()->where('lead_id', $lead->id)->count());
    }
}
