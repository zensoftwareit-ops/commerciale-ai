<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PipelineStage;
use App\Models\User;
use App\Models\MailboxAccount;
use App\Support\Tenancy\TenantContext;
use Tests\TestCase;

abstract class CommercialeAiTestCase extends TestCase
{
    protected function organizationWithUser(string $role = 'owner'): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user, ['role' => $role]);
        app(TenantContext::class)->set($organization);
        PipelineStage::create(['name' => 'Nuovo', 'slug' => 'new', 'system_category' => 'open', 'position' => 1]);
        app(TenantContext::class)->clear();

        return [$organization, $user];
    }

    protected function mailboxFor(Organization $organization, bool $verified = true): MailboxAccount
    {
        app(TenantContext::class)->set($organization);
        $mailbox = MailboxAccount::create([
            'name' => 'Email Daria',
            'from_address' => 'daria@azienda.test',
            'from_name' => 'Daria Azienda',
            'reply_to_address' => 'daria@azienda.test',
            'domain_verification_status' => $verified ? 'verified' : 'pending',
            'domain_verified_at' => $verified ? now() : null,
            'host' => 'imap.azienda.test',
            'port' => 993,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => 'daria@azienda.test',
            'password' => 'secret',
            'folder' => 'INBOX',
            'is_active' => true,
        ]);
        app(TenantContext::class)->clear();

        return $mailbox;
    }
}
