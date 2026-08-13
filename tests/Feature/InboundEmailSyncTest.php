<?php

namespace Tests\Feature;

use App\Contracts\InboundMailbox;
use App\Data\InboundEmailMessage;
use App\Models\InboundEmail;
use App\Models\Lead;
use App\Models\LeadReply;
use App\Services\Ai\AnalyzeLead;
use App\Services\Ai\GenerateLeadReply;
use App\Services\Leads\CreateLead;
use App\Services\Mail\SyncInboundEmailReplies;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InboundEmailSyncTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_it_imports_a_reply_cancels_follow_up_and_creates_a_new_draft(): void
    {
        [$organization, $user] = $this->organizationWithUser();
        [$lead, $sentReply] = $this->sentReplyWithFollowUp($organization);
        $mailbox = new FakeInboundMailbox([
            new InboundEmailMessage(
                identifier: '101', messageId: 'customer-reply-1@example.test',
                inReplyTo: $sentReply->outbound_message_id, references: [$sentReply->outbound_message_id],
                fromAddress: 'anna@example.test', fromName: 'Anna Demo', subject: 'Re: Il suo progetto web',
                body: 'Grazie, il budget è 2.000 euro. Possiamo sentirci venerdì?',
                receivedAt: CarbonImmutable::now(),
            ),
        ]);
        $service = new SyncInboundEmailReplies($mailbox, app(GenerateLeadReply::class));

        $stats = $service->handle();

        $this->assertSame(1, $stats['imported']);
        $this->assertSame(1, $stats['drafts']);
        $this->assertSame(['101'], $mailbox->seen);
        $this->assertSame(1, InboundEmail::withoutGlobalScopes()->count());
        $this->assertSame(2, LeadReply::withoutGlobalScopes()->where('lead_id', $lead->id)->count());
        $newDraft = LeadReply::withoutGlobalScopes()->where('lead_id', $lead->id)->where('status', 'draft')->firstOrFail();
        $this->assertSame('draft', $newDraft->status);
        $this->assertSame('customer-reply-1@example.test', $newDraft->parent_message_id);
        $this->assertNotNull($sentReply->fresh()->follow_up_cancelled_at);
        $lead = Lead::withoutGlobalScopes()->findOrFail($lead->id);
        $this->assertSame('awaiting_approval', $lead->operational_status);
        $this->assertNull($lead->next_action_at);
        $this->assertDatabaseHas('activities', ['lead_id' => $lead->id, 'type' => 'email_received']);
        $this->assertDatabaseHas('activities', ['lead_id' => $lead->id, 'type' => 'follow_up_cancelled']);
        $this->actingAs($user)->withSession(['organization_id' => $organization->id])
            ->get(route('leads.show', $lead))->assertOk()->assertSee('Grazie, il budget è 2.000 euro.');
    }

    public function test_it_is_idempotent_and_links_a_thread_reply_from_a_different_sender(): void
    {
        [$organization] = $this->organizationWithUser();
        [$lead, $sentReply] = $this->sentReplyWithFollowUp($organization);
        $valid = new InboundEmailMessage(
            identifier: '201', messageId: 'reply-201@example.test', inReplyTo: $sentReply->outbound_message_id,
            references: [], fromAddress: 'anna@example.test', fromName: null, subject: 'Re: Preventivo',
            body: 'Va bene.', receivedAt: CarbonImmutable::now(),
        );
        $mailbox = new FakeInboundMailbox([$valid]);
        $service = new SyncInboundEmailReplies($mailbox, app(GenerateLeadReply::class));
        $service->handle();
        $stats = $service->handle();

        $this->assertSame(1, $stats['duplicates']);
        $this->assertSame(1, InboundEmail::withoutGlobalScopes()->count());

        $differentSenderMailbox = new FakeInboundMailbox([
            new InboundEmailMessage(
                identifier: '202', messageId: 'forwarded-reply@example.test', inReplyTo: $sentReply->outbound_message_id,
                references: [], fromAddress: 'attacker@example.test', fromName: null, subject: 'Re: Preventivo',
                body: 'Rispondo dalla mia casella personale.', receivedAt: CarbonImmutable::now(),
            ),
        ]);
        $differentSenderStats = (new SyncInboundEmailReplies($differentSenderMailbox, app(GenerateLeadReply::class)))->handle();

        $this->assertSame(1, $differentSenderStats['imported']);
        $this->assertSame(['202'], $differentSenderMailbox->seen);
        $this->assertSame(2, InboundEmail::withoutGlobalScopes()->count());
        $linked = InboundEmail::withoutGlobalScopes()->where('message_id', 'forwarded-reply@example.test')->firstOrFail();
        $this->assertTrue($linked->sender_differs);
        $this->assertSame('thread_id', $linked->match_reason);
        $this->assertSame('anna@example.test', Lead::withoutGlobalScopes()->findOrFail($lead->id)->email_normalized);
    }

    public function test_it_queues_an_uncertain_message_and_allows_manual_linking(): void
    {
        [$organization, $user] = $this->organizationWithUser();
        [$lead] = $this->sentReplyWithFollowUp($organization);
        $mailbox = new FakeInboundMailbox([
            new InboundEmailMessage(
                identifier: '301', messageId: 'unknown-301@example.test', inReplyTo: null,
                references: [], fromAddress: 'personal@example.test', fromName: 'Anna privata',
                subject: 'Informazioni aggiuntive', body: 'Sono sempre Anna, scrivo da un altro indirizzo.',
                receivedAt: CarbonImmutable::now(),
            ),
        ]);

        $stats = (new SyncInboundEmailReplies($mailbox, app(GenerateLeadReply::class)))->handle();

        $this->assertSame(1, $stats['unmatched']);
        $pending = InboundEmail::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('pending', $pending->status);
        $this->assertNull($pending->lead_id);
        $this->actingAs($user)->withSession(['organization_id' => $organization->id])
            ->post(route('inbound-emails.link', $pending), [
                'lead_id' => $lead->id,
                'add_secondary_contact' => '1',
            ])->assertRedirect(route('leads.show', $lead));

        $pending->refresh();
        $this->assertSame('linked', $pending->status);
        $this->assertSame($lead->id, $pending->lead_id);
        $this->assertSame('manual', $pending->match_confidence);
        $this->assertDatabaseHas('lead_contacts', [
            'lead_id' => $lead->id,
            'email_normalized' => 'personal@example.test',
            'is_primary' => false,
        ]);
    }

    private function sentReplyWithFollowUp($organization): array
    {
        app(TenantContext::class)->set($organization);
        $lead = app(CreateLead::class)->handle([
            'name' => 'Anna Demo', 'email' => 'anna@example.test', 'requested_service' => 'Sito web',
            'source_label' => 'manual', 'request_data' => ['message' => 'Vorrei un preventivo'],
        ]);
        $analysis = app(AnalyzeLead::class)->handle($lead);
        $reply = app(GenerateLeadReply::class)->handle($lead, $analysis);
        $followUp = now()->addDays(2);
        $reply->update(['status' => 'sent', 'sent_at' => now(), 'follow_up_at' => $followUp]);
        $lead->update(['operational_status' => 'follow_up_scheduled', 'next_action_at' => $followUp]);
        app(TenantContext::class)->clear();

        return [$lead, $reply];
    }
}

class FakeInboundMailbox implements InboundMailbox
{
    public array $seen = [];

    public function __construct(private readonly array $messages) {}

    public function testConnection(): void {}

    public function recent(int $limit): iterable
    {
        yield from array_slice($this->messages, 0, $limit);
    }

    public function markSeen(string $identifier): void
    {
        $this->seen[] = $identifier;
    }

    public function close(): void {}
}
