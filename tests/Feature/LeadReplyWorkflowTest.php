<?php

namespace Tests\Feature;

use App\Mail\LeadReplyMail;
use App\Models\Lead;
use App\Models\LeadReply;
use App\Models\MailboxAccount;
use App\Models\PipelineStage;
use App\Services\Leads\CreateLead;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

class LeadReplyWorkflowTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_analysis_creates_an_editable_reply_draft(): void
    {
        [$organization, $user] = $this->organizationWithUser('sales');
        app(TenantContext::class)->set($organization);
        PipelineStage::create(['name' => 'Da valutare', 'slug' => 'to_review', 'system_category' => 'open', 'position' => 2]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Anna Demo', 'email' => 'anna@example.test', 'requested_service' => 'Sito web',
            'source_label' => 'manual', 'request_data' => ['message' => 'Vorrei un preventivo'],
        ]);
        app(TenantContext::class)->clear();

        $this->actingAs($user)->withSession(['organization_id' => $organization->id])
            ->post(route('leads.analyze', $lead))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Analisi completata e bozza email preparata.');

        $reply = LeadReply::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('draft', $reply->status);
        $this->assertSame('anna@example.test', $reply->recipient);
        $this->assertSame('awaiting_approval', Lead::withoutGlobalScopes()->findOrFail($lead->id)->operational_status);
        $this->assertDatabaseHas('ai_runs', ['operation' => 'reply_draft', 'status' => 'completed']);
        $this->assertDatabaseHas('activities', ['lead_id' => $lead->id, 'type' => 'reply_draft_created']);
    }

    public function test_operator_can_edit_approve_send_and_schedule_follow_up(): void
    {
        config()->set('mail.default', 'smtp');
        Mail::fake();
        [$organization, $user] = $this->organizationWithUser('sales');
        MailboxAccount::create([
            'organization_id' => $organization->id, 'name' => 'Email Daria',
            'from_address' => 'commerciale@azienda.test', 'from_name' => 'Commerciale Azienda',
            'reply_to_address' => 'risposte@azienda.test', 'host' => 'imap.azienda.test', 'port' => 993,
            'encryption' => 'ssl', 'validate_cert' => true, 'username' => 'commerciale@azienda.test',
            'password' => 'secret', 'folder' => 'INBOX', 'is_active' => true,
        ]);
        app(TenantContext::class)->set($organization);
        PipelineStage::create(['name' => 'Da valutare', 'slug' => 'to_review', 'system_category' => 'open', 'position' => 2]);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Anna Demo', 'email' => 'anna@example.test', 'requested_service' => 'Sito web',
            'source_label' => 'manual', 'request_data' => ['message' => 'Vorrei un preventivo'],
        ]);
        app(TenantContext::class)->clear();
        $session = $this->actingAs($user)->withSession(['organization_id' => $organization->id]);
        $session->post(route('leads.analyze', $lead));
        $reply = LeadReply::withoutGlobalScopes()->firstOrFail();
        $followUp = now()->addDays(2)->seconds(0);

        $session->patch(route('replies.update', [$lead, $reply]), [
            'recipient' => 'anna@example.test', 'subject' => 'Il suo progetto web',
            'body' => 'Buongiorno Anna, possiamo fissare una chiamata?',
            'follow_up_at' => $followUp->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $session->post(route('replies.send', [$lead, $reply]))->assertSessionHasNoErrors();

        $reply->refresh();
        $lead = Lead::withoutGlobalScopes()->findOrFail($lead->id);
        $this->assertSame('sent', $reply->status);
        $this->assertSame('commerciale@azienda.test', $reply->sender_address);
        $this->assertSame('Commerciale Azienda', $reply->sender_name);
        $this->assertSame('risposte@azienda.test', $reply->reply_to_address);
        $this->assertNotNull($reply->outbound_message_id);
        $this->assertSame($user->id, $reply->approved_by);
        $this->assertSame('follow_up_scheduled', $lead->operational_status);
        $this->assertNotNull($lead->next_action_at);
        $this->assertDatabaseHas('activities', ['lead_id' => $lead->id, 'type' => 'email_sent']);
        $this->assertDatabaseHas('activities', ['lead_id' => $lead->id, 'type' => 'follow_up_scheduled']);
        Mail::assertSent(LeadReplyMail::class, fn (LeadReplyMail $mail): bool => $mail->hasTo('anna@example.test')
            && $mail->envelope()->from->address === 'commerciale@azienda.test'
            && $mail->envelope()->replyTo[0]->address === 'risposte@azienda.test'
            && $mail->headers()->messageId === $reply->outbound_message_id);

        $session->patch(route('replies.update', [$lead, $reply]), [
            'recipient' => 'other@example.test', 'subject' => 'Modifica', 'body' => 'Non consentita',
        ])->assertStatus(422);
    }

    public function test_log_mailer_cannot_mark_a_reply_as_sent(): void
    {
        config()->set('mail.default', 'log');
        Mail::fake();
        [$organization, $user] = $this->organizationWithUser('sales');
        app(TenantContext::class)->set($organization);
        PipelineStage::create(['name' => 'Da valutare', 'slug' => 'to_review', 'system_category' => 'open', 'position' => 2]);
        $lead = app(CreateLead::class)->handle(['name' => 'Anna Demo', 'email' => 'anna@example.test', 'source_label' => 'manual']);
        app(TenantContext::class)->clear();
        $session = $this->actingAs($user)->withSession(['organization_id' => $organization->id]);
        $session->post(route('leads.analyze', $lead));
        $reply = LeadReply::withoutGlobalScopes()->firstOrFail();

        $session->post(route('replies.send', [$lead, $reply]))->assertSessionHasErrors('reply');

        $this->assertSame('draft', $reply->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_analysis_without_email_succeeds_without_creating_a_draft(): void
    {
        [$organization, $user] = $this->organizationWithUser('sales');
        app(TenantContext::class)->set($organization);
        PipelineStage::create(['name' => 'Da valutare', 'slug' => 'to_review', 'system_category' => 'open', 'position' => 2]);
        $lead = app(CreateLead::class)->handle(['name' => 'Solo telefono', 'phone' => '+39 0212345678', 'source_label' => 'manual']);
        app(TenantContext::class)->clear();

        $this->actingAs($user)->withSession(['organization_id' => $organization->id])
            ->post(route('leads.analyze', $lead))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, LeadReply::withoutGlobalScopes()->count());
        $this->assertDatabaseHas('ai_analyses', ['lead_id' => $lead->id]);
    }
}
