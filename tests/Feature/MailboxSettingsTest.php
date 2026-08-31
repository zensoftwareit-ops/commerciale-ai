<?php

namespace Tests\Feature;

use App\Models\MailboxAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class MailboxSettingsTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_owner_can_store_an_encrypted_imap_account(): void
    {
        [$organization, $owner] = $this->organizationWithUser();

        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->post(route('settings.mailboxes.store'), [
                'from_address' => 'commerciale@azienda.test', 'from_name' => 'Commerciale Azienda',
                'reply_to_address' => 'risposte@azienda.test', 'host' => 'imap.example.test', 'port' => 993,
                'encryption' => 'ssl', 'validate_cert' => '1', 'username' => 'sales@example.test',
                'password' => 'secret-password', 'folder' => 'INBOX', 'is_active' => '1',
            ])->assertRedirect();

        $mailbox = MailboxAccount::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('secret-password', $mailbox->password);
        $this->assertSame('commerciale@azienda.test', $mailbox->from_address);
        $this->assertSame('risposte@azienda.test', $mailbox->reply_to_address);
        $this->assertSame('pending', $mailbox->domain_verification_status);
        $this->assertNotSame('secret-password', DB::table('mailbox_accounts')->value('password'));
        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->get(route('settings.mailboxes.index'))->assertOk()->assertSee('sales@example.test')->assertDontSee('secret-password');
    }

    public function test_only_platform_admin_can_confirm_the_sender_domain(): void
    {
        [$organization, $owner] = $this->organizationWithUser();
        $mailbox = $this->mailboxFor($organization, false);
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->post(route('admin.organizations.mail.verify', [$organization, $mailbox]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.organizations.mail.verify', [$organization, $mailbox]))
            ->assertRedirect();

        $this->assertSame('verified', $mailbox->fresh()->domain_verification_status);
        $this->assertSame($admin->id, $mailbox->fresh()->domain_verified_by);
    }

    public function test_owner_can_test_the_organization_outbound_identity(): void
    {
        Mail::fake();
        config()->set('mail.default', 'smtp');
        [$organization, $owner] = $this->organizationWithUser();
        $mailbox = MailboxAccount::create([
            'organization_id' => $organization->id, 'name' => 'Email Daria',
            'from_address' => 'commerciale@azienda.test', 'from_name' => 'Commerciale Azienda',
            'reply_to_address' => 'commerciale@azienda.test', 'host' => 'imap.example.test',
            'port' => 993, 'encryption' => 'ssl', 'validate_cert' => true,
            'username' => 'commerciale@azienda.test', 'password' => 'secret', 'folder' => 'INBOX', 'is_active' => true,
        ]);

        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->post(route('settings.mailboxes.test-outbound', $mailbox), ['test_recipient' => 'test@example.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertNotNull($mailbox->fresh()->last_outbound_tested_at);
    }

    public function test_non_owner_cannot_manage_mailboxes(): void
    {
        [$organization, $sales] = $this->organizationWithUser('sales');

        $this->actingAs($sales)->withSession(['organization_id' => $organization->id])
            ->get(route('settings.mailboxes.index'))->assertForbidden();
    }

    public function test_owner_can_register_the_sender_domain_through_resend_api(): void
    {
        config()->set('services.resend.key', 're_test_key');
        config()->set('services.resend.api_url', 'https://api.resend.com');
        config()->set('services.resend.domain_automation_enabled', true);
        config()->set('services.resend.domain_region', 'eu-west-1');
        Http::fake([
            'https://api.resend.com/domains' => Http::sequence()
                ->push(['object' => 'list', 'data' => []])
                ->push([
                    'id' => 'domain-123', 'name' => 'azienda.test', 'status' => 'not_started',
                    'records' => [[
                        'record' => 'DKIM', 'name' => 'resend._domainkey', 'type' => 'TXT',
                        'value' => 'public-key', 'status' => 'not_started',
                    ]],
                ], 201),
        ]);
        [$organization, $owner] = $this->organizationWithUser();
        $mailbox = $this->mailboxFor($organization, false);

        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->post(route('settings.mailboxes.resend-domain.register', $mailbox))
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $mailbox->refresh();
        $this->assertSame('domain-123', $mailbox->resend_domain_id);
        $this->assertSame('azienda.test', $mailbox->resend_domain_name);
        $this->assertSame('not_started', $mailbox->resend_domain_status);
        $this->assertSame('pending', $mailbox->domain_verification_status);
        $this->assertSame('public-key', $mailbox->resend_dns_records[0]['value']);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer re_test_key'));
    }

    public function test_resend_verified_status_automatically_enables_the_sender_domain(): void
    {
        config()->set('services.resend.key', 're_test_key');
        config()->set('services.resend.api_url', 'https://api.resend.com');
        config()->set('services.resend.domain_automation_enabled', true);
        Http::fake([
            'https://api.resend.com/domains/domain-123/verify' => Http::response(['object' => 'domain', 'id' => 'domain-123']),
            'https://api.resend.com/domains/domain-123' => Http::response([
                'id' => 'domain-123', 'name' => 'azienda.test', 'status' => 'verified',
                'records' => [[
                    'record' => 'SPF', 'name' => 'send', 'type' => 'TXT',
                    'value' => 'v=spf1 include:amazonses.com ~all', 'status' => 'verified',
                ]],
            ]),
        ]);
        [$organization, $owner] = $this->organizationWithUser();
        $mailbox = $this->mailboxFor($organization, false);
        $mailbox->update([
            'resend_domain_id' => 'domain-123', 'resend_domain_name' => 'azienda.test',
            'resend_domain_status' => 'not_started',
        ]);

        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->post(route('settings.mailboxes.resend-domain.verify', $mailbox))
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $mailbox->refresh();
        $this->assertSame('verified', $mailbox->resend_domain_status);
        $this->assertSame('verified', $mailbox->domain_verification_status);
        $this->assertNotNull($mailbox->domain_verified_at);
        $this->assertNull($mailbox->domain_verified_by);
    }
}
