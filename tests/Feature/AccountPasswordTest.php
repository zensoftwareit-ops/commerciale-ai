<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AccountPasswordTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_change_password(): void
    {
        [$organization, $user] = $this->organizationWithUser();
        $user->update(['password' => 'CurrentPassword!1']);

        $this->actingAs($user)->withSession(['organization_id' => $organization->id])->put(route('account.password.update'), [
            'current_password' => 'CurrentPassword!1',
            'password' => 'NewSecurePassword!2',
            'password_confirmation' => 'NewSecurePassword!2',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewSecurePassword!2', $user->fresh()->password));
        $this->actingAs($user)->withSession(['organization_id' => $organization->id])
            ->get(route('account.edit'))->assertOk()->assertDontSee('Mittente email');
    }

    public function test_platform_admin_configures_a_dedicated_system_mail_sender(): void
    {
        $admin = User::factory()->create([
            'is_super_admin' => true,
            'email' => 'admin-personale@example.test',
        ]);

        $this->actingAs($admin)->put(route('admin.account.system-mail-identity.update'), [
            'system_mail_from_address' => 'assistenza@daria-ai.it',
            'system_mail_from_name' => 'Daria',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertDatabaseHas('platform_settings', [
            'system_mail_from_address' => 'assistenza@daria-ai.it',
            'system_mail_from_name' => 'Daria',
        ]);
        $this->assertDatabaseMissing('platform_settings', [
            'system_mail_from_address' => 'admin-personale@example.test',
        ]);
    }

    public function test_platform_mail_test_rejects_a_non_delivering_mailer(): void
    {
        config()->set('mail.default', 'log');
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->post(route('admin.account.system-mail-test'))
            ->assertSessionHasErrors('mail');
    }

    public function test_platform_admin_can_send_a_system_mail_test_with_smtp(): void
    {
        Mail::fake();
        config()->set('mail.default', 'smtp');
        $admin = User::factory()->create(['is_super_admin' => true]);
        PlatformSetting::create([
            'id' => 1,
            'system_mail_from_address' => 'assistenza@daria-ai.it',
            'system_mail_from_name' => 'Daria',
        ]);

        $this->actingAs($admin)->post(route('admin.account.system-mail-test'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
    }
}
