<?php

namespace Tests\Feature;

use App\Models\InboundEmail;
use App\Models\User;
use App\Services\Ai\AnalyzeLead;
use App\Services\Ai\GenerateLeadReply;
use App\Services\Leads\CreateLead;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LeadDeletionTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_owner_can_permanently_delete_a_lead_and_all_related_data(): void
    {
        [$organization, $owner] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Lead da eliminare', 'email' => 'delete@example.test',
            'requested_service' => 'Sito web', 'source_label' => 'manual',
        ]);
        $analysis = app(AnalyzeLead::class)->handle($lead);
        $reply = app(GenerateLeadReply::class)->handle($lead, $analysis);
        InboundEmail::create([
            'lead_id' => $lead->id, 'lead_reply_id' => $reply->id, 'status' => 'linked',
            'message_hash' => hash('sha256', 'delete-message'), 'message_id' => 'delete-message@example.test',
            'from_address' => 'delete@example.test', 'subject' => 'Re: test', 'body' => 'Risposta di test',
            'received_at' => now(),
        ]);
        app(TenantContext::class)->clear();

        $response = $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->delete(route('leads.destroy', $lead), ['confirmation' => 'ELIMINA']);

        $response->assertRedirect(route('leads.index'));
        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
        $this->assertDatabaseMissing('lead_contacts', ['lead_id' => $lead->id]);
        $this->assertDatabaseMissing('activities', ['lead_id' => $lead->id]);
        $this->assertDatabaseMissing('ai_analyses', ['lead_id' => $lead->id]);
        $this->assertDatabaseMissing('lead_replies', ['lead_id' => $lead->id]);
        $this->assertDatabaseMissing('inbound_emails', ['lead_id' => $lead->id]);
        $this->assertDatabaseMissing('ai_runs', ['lead_id' => $lead->id]);
    }

    public function test_deletion_requires_exact_confirmation_and_owner_role(): void
    {
        [$organization, $owner] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        $lead = app(CreateLead::class)->handle(['name' => 'Conserva', 'email' => 'keep@example.test', 'source_label' => 'manual']);
        app(TenantContext::class)->clear();

        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->delete(route('leads.destroy', $lead), ['confirmation' => 'elimina'])
            ->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('leads', ['id' => $lead->id]);

        $sales = User::factory()->create();
        $organization->users()->attach($sales, ['role' => 'sales']);
        $this->actingAs($sales)->withSession(['organization_id' => $organization->id])
            ->delete(route('leads.destroy', $lead), ['confirmation' => 'ELIMINA'])
            ->assertForbidden();
        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }
}
