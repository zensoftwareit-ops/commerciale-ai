<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_totp_matches_the_rfc_6238_sha1_vector(): void
    {
        $this->assertSame(
            '94287082',
            app(Totp::class)->code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59, 8),
        );
    }

    public function test_required_two_factor_enrollment_and_login_challenge(): void
    {
        config()->set('commerciale-ai.security.platform_2fa_required', true);
        $admin = User::factory()->create([
            'is_super_admin' => true,
            'password' => 'PasswordSicura!2026',
        ]);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'PasswordSicura!2026',
        ])->assertRedirect(route('admin.two-factor.enroll'));

        $this->post(route('admin.two-factor.setup'))->assertRedirect();
        $admin->refresh();
        $this->assertNotEmpty($admin->two_factor_secret);

        $code = app(Totp::class)->code($admin->two_factor_secret);
        $this->post(route('admin.two-factor.confirm'), ['code' => $code])
            ->assertRedirect()
            ->assertSessionHas('two_factor_recovery_codes');
        $this->get(route('admin.licensing'))->assertOk();

        $this->post(route('logout'));
        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'PasswordSicura!2026',
        ])->assertRedirect(route('admin.two-factor.challenge'));
        $this->get(route('admin.licensing'))->assertRedirect(route('admin.two-factor.challenge'));

        $admin->refresh();
        $this->post(route('admin.two-factor.verify'), [
            'code' => app(Totp::class)->code($admin->two_factor_secret),
        ])->assertRedirect(route('admin.licensing'));
        $this->get(route('admin.licensing'))->assertOk();
    }

    public function test_confirmed_two_factor_secret_cannot_be_regenerated_directly(): void
    {
        $admin = User::factory()->create([
            'is_super_admin' => true,
            'two_factor_secret' => app(Totp::class)->generateSecret(),
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);
        $secret = $admin->two_factor_secret;

        $this->actingAs($admin)
            ->withSession(['platform_2fa_verified_at' => now()->timestamp])
            ->post(route('admin.two-factor.setup'))
            ->assertSessionHasErrors('two_factor');

        $this->assertSame($secret, $admin->fresh()->two_factor_secret);
    }
}
