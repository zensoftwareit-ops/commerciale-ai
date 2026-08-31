<?php

namespace Tests\Feature;

use App\Models\PlatformAuditLog;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Operations\PlatformHealthAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_protected_platform_health_endpoint_exposes_only_check_statuses(): void
    {
        config()->set('commerciale-ai.operations.healthcheck_token', 'monitor-secret');

        $this->getJson('/api/v1/platform-health')->assertNotFound();
        $response = $this->withHeader('X-Daria-Health-Token', 'monitor-secret')->getJson('/api/v1/platform-health');

        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure(['status', 'checked_at', 'checks' => [['key', 'status']]]);
        $response->assertJsonMissing(['detail']);
    }

    public function test_health_errors_are_emailed_once_and_recorded(): void
    {
        Mail::fake();
        config()->set('mail.default', 'smtp');
        User::factory()->create(['is_super_admin' => true]);
        PlatformSetting::query()->create([
            'id' => 1,
            'system_mail_from_address' => 'sistema@daria-ai.it',
            'system_mail_from_name' => 'Daria',
        ]);

        $result = app(PlatformHealthAlert::class)->handle(force: true);

        $this->assertSame('sent', $result['status']);
        $this->assertNotNull(PlatformSetting::query()->findOrFail(1)->last_health_alerted_at);
    }
}
