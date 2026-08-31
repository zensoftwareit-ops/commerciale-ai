<?php

namespace Tests\Feature;

use App\Services\Auth\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerTwoFactorTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_customer_can_enable_two_factor_and_is_challenged_on_a_new_login(): void
    {
        [$organization, $owner] = $this->organizationWithUser();
        $owner->update(['password' => 'PasswordSicura!2026']);

        $this->actingAs($owner)->post(route('account.two-factor.setup'))->assertRedirect();
        $owner->refresh();
        $this->post(route('account.two-factor.confirm'), [
            'code' => app(Totp::class)->code($owner->two_factor_secret),
        ])->assertSessionHas('two_factor_recovery_codes');

        $this->post(route('logout'));
        $this->post('/login', ['email' => $owner->email, 'password' => 'PasswordSicura!2026'])
            ->assertRedirect(route('account.two-factor.challenge'));
        $this->withSession(['organization_id' => $organization->id])->get(route('leads.index'))
            ->assertRedirect(route('account.two-factor.challenge'));

        $owner->refresh();
        $this->post(route('account.two-factor.verify'), [
            'code' => app(Totp::class)->code($owner->two_factor_secret),
        ])->assertRedirect(route('leads.index'));
    }
}
