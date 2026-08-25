<?php

namespace Tests\Feature;

use App\Models\MailboxAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class MailboxSettingsTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_owner_can_store_an_encrypted_imap_account(): void
    {
        [$organization, $owner] = $this->organizationWithUser();

        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->post(route('settings.mailboxes.store'), [
                'name' => 'Commerciale', 'host' => 'imap.example.test', 'port' => 993,
                'encryption' => 'ssl', 'validate_cert' => '1', 'username' => 'sales@example.test',
                'password' => 'secret-password', 'folder' => 'INBOX', 'is_active' => '1',
            ])->assertRedirect();

        $mailbox = MailboxAccount::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('secret-password', $mailbox->password);
        $this->assertNotSame('secret-password', DB::table('mailbox_accounts')->value('password'));
        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->get(route('settings.mailboxes.index'))->assertOk()->assertSee('sales@example.test')->assertDontSee('secret-password');
    }

    public function test_non_owner_cannot_manage_mailboxes(): void
    {
        [$organization, $sales] = $this->organizationWithUser('sales');

        $this->actingAs($sales)->withSession(['organization_id' => $organization->id])
            ->get(route('settings.mailboxes.index'))->assertForbidden();
    }
}

