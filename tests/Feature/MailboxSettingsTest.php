<?php

namespace Tests\Feature;

use App\Models\MailboxAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertNotSame('secret-password', DB::table('mailbox_accounts')->value('password'));
        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->get(route('settings.mailboxes.index'))->assertOk()->assertSee('sales@example.test')->assertDontSee('secret-password');
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
}
