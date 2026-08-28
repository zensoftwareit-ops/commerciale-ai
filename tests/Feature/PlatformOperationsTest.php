<?php

namespace Tests\Feature;

use App\Models\PlatformAuditLog;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_admin_mutations_are_audited_without_field_values(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->put(route('admin.account.system-mail-identity.update'), [
            'system_mail_from_address' => 'sistema@daria-ai.it',
            'system_mail_from_name' => 'Daria',
        ])->assertSessionHasNoErrors();

        $audit = PlatformAuditLog::query()->latest('id')->firstOrFail();
        $this->assertSame('admin.account.system-mail-identity.update', $audit->action);
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertContains('system_mail_from_address', $audit->metadata['changed_fields']);
        $this->assertStringNotContainsString('sistema@daria-ai.it', json_encode($audit->metadata));
    }

    public function test_admin_can_confirm_a_verified_backup(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.health.backup-confirm'))
            ->assertRedirect();

        $this->assertNotNull(PlatformSetting::query()->findOrFail(1)->last_backup_verified_at);
        $this->assertDatabaseHas('platform_audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'admin.health.backup-confirm',
        ]);
    }
}
