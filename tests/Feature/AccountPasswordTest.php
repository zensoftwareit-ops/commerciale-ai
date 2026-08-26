<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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
    }

    public function test_customer_can_configure_their_own_mail_sender(): void
    {
        [$organization, $user] = $this->organizationWithUser();

        $this->actingAs($user)->withSession(['organization_id' => $organization->id])
            ->put(route('account.mail-identity.update'), [
                'mail_from_address' => 'commerciale@cliente.test',
                'mail_from_name' => 'Ufficio commerciale Cliente',
            ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'mail_from_address' => 'commerciale@cliente.test',
            'mail_from_name' => 'Ufficio commerciale Cliente',
        ]);
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
}
