<?php

namespace App\Services\Licensing;

use App\Models\License;
use App\Models\User;
use App\Services\Organizations\OrganizationLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class CreateManualLicensedCustomer
{
    public function __construct(
        private readonly OrganizationProvisioner $organizations,
        private readonly OrganizationLifecycle $lifecycle,
    ) {}

    /** @return array{license:License,reset_link_sent:bool} */
    public function handle(array $data): array
    {
        $license = DB::transaction(function () use ($data): License {
            $email = mb_strtolower(trim($data['owner_email']));
            $owner = User::create([
                'name' => $data['owner_name'],
                'email' => $email,
                'password' => Str::password(32),
            ]);
            $organization = $this->organizations->create($data['company_name']);
            $organization->users()->attach($owner, ['role' => 'owner']);

            return License::create([
                'license_plan_id' => $data['license_plan_id'],
                'organization_id' => $organization->id,
                'owner_user_id' => $owner->id,
                'key' => $this->newKey(),
                'status' => $data['status'],
                'source' => 'manual',
                'starts_at' => now(),
                'current_period_ends_at' => $data['current_period_ends_at'] ?? now()->addYear(),
                'ends_at' => $data['ends_at'] ?? null,
                'metadata' => ['provisioning' => 'super_admin'],
            ])->load('plan', 'organization', 'owner');
        });

        $this->lifecycle->refresh($license->organization);

        try {
            $resetSent = Password::sendResetLink(['email' => $license->owner->email]) === Password::RESET_LINK_SENT;
        } catch (Throwable) {
            $resetSent = false;
        }

        return ['license' => $license, 'reset_link_sent' => $resetSent];
    }

    private function newKey(): string
    {
        do {
            $key = 'CAI-'.Str::upper(Str::random(8).'-'.Str::random(8).'-'.Str::random(8));
        } while (License::query()->where('key', $key)->exists());

        return $key;
    }
}
