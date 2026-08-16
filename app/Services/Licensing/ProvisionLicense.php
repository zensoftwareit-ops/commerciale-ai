<?php

namespace App\Services\Licensing;

use App\Models\License;
use App\Models\LicenseEvent;
use App\Models\LicensePlan;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;

class ProvisionLicense
{
    /** @return array{license:License,account_created:bool,reset_link_sent:bool} */
    public function handle(array $data): array
    {
        $existingEvent = LicenseEvent::query()->where('external_event_id', $data['event_id'])->first();
        if ($existingEvent?->license) return ['license' => $existingEvent->license->load('plan', 'organization', 'owner'), 'account_created' => false, 'reset_link_sent' => false];

        $accountCreated = false;
        $license = DB::transaction(function () use ($data, &$accountCreated): License {
            $license = filled($data['stripe_subscription_id'] ?? null)
                ? License::query()->where('stripe_subscription_id', $data['stripe_subscription_id'])->first()
                : License::query()->where('external_account_id', $data['external_account_id'])->latest()->first();
            if ($license && ! hash_equals((string) $license->external_account_id, (string) $data['external_account_id'])) throw new RuntimeException('L’abbonamento appartiene a un altro account.');
            $planQuery = filled($data['stripe_price_id'] ?? null)
                ? LicensePlan::query()->where('stripe_price_id', $data['stripe_price_id'])
                : LicensePlan::query()->where('slug', $data['plan_slug']);
            if (! $license) $planQuery->where('is_active', true);
            $plan = $planQuery->firstOrFail();

            $email = mb_strtolower(trim($data['email']));
            $user = User::query()->where('email', $email)->first();
            if (! $user) {
                $user = User::create(['name' => $data['name'], 'email' => $email, 'password' => Str::password(32), 'external_account_id' => $data['external_account_id']]);
                $accountCreated = true;
            } elseif ($user->external_account_id && ! hash_equals($user->external_account_id, $data['external_account_id'])) {
                throw new RuntimeException('L’email è già collegata a un altro account commerciale.');
            } elseif (! $user->external_account_id) {
                $user->update(['external_account_id' => $data['external_account_id']]);
            }

            $organization = Organization::query()->where('billing_account_ref', $data['external_account_id'])->first();
            if (! $organization) {
                $organization = Organization::create([
                    'name' => $data['company'] ?: $data['name'],
                    'slug' => $this->uniqueSlug($data['company'] ?: $data['name']),
                    'billing_account_ref' => $data['external_account_id'],
                    'timezone' => 'Europe/Rome', 'locale' => 'it',
                ]);
                $organization->users()->attach($user, ['role' => 'owner']);
                $this->initializeOrganization($organization, $data['company'] ?: $data['name']);
            } else {
                $organization->users()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
            }

            $values = [
                'license_plan_id' => $plan->id, 'organization_id' => $organization->id, 'owner_user_id' => $user->id,
                'status' => $data['status'], 'source' => 'wordpress_stripe', 'external_account_id' => $data['external_account_id'],
                'stripe_customer_id' => $data['stripe_customer_id'] ?? null, 'stripe_subscription_id' => $data['stripe_subscription_id'] ?? null,
                'starts_at' => $data['starts_at'] ?? now(), 'current_period_ends_at' => $data['current_period_ends_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null, 'cancel_at_period_end' => (bool) ($data['cancel_at_period_end'] ?? false),
            ];
            if ($license) $license->update($values);
            else $license = License::create([...$values, 'key' => $this->newKey()]);

            LicenseEvent::create([
                'license_id' => $license->id, 'external_event_id' => $data['event_id'], 'source' => 'wordpress_stripe',
                'type' => $data['event_type'], 'payload_hash' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)),
                'status' => 'processed', 'payload' => ['status' => $data['status'], 'plan_slug' => $data['plan_slug']], 'processed_at' => now(),
            ]);

            return $license->load('plan', 'organization', 'owner');
        });

        $resetSent = $accountCreated && Password::sendResetLink(['email' => $license->owner->email]) === Password::RESET_LINK_SENT;
        return ['license' => $license, 'account_created' => $accountCreated, 'reset_link_sent' => $resetSent];
    }

    private function initializeOrganization(Organization $organization, string $name): void
    {
        foreach ([['Nuovo', 'new', 'open'], ['Da valutare', 'to_review', 'open'], ['Qualificazione', 'qualification', 'open'], ['Qualificato', 'qualified', 'open'], ['Proposta', 'proposal', 'open'], ['Negoziazione', 'negotiation', 'open'], ['Vinto', 'won', 'won'], ['Perso', 'lost', 'lost']] as $position => [$label, $slug, $category]) {
            PipelineStage::create(['organization_id' => $organization->id, 'name' => $label, 'slug' => $slug, 'system_category' => $category, 'position' => $position + 1]);
        }
        OrganizationSetting::create(['organization_id' => $organization->id, 'commercial_name' => $name, 'completeness' => 0]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'azienda';
        do $slug = $base.'-'.Str::lower(Str::random(6)); while (Organization::query()->where('slug', $slug)->exists());
        return $slug;
    }

    private function newKey(): string
    {
        do $key = 'CAI-'.Str::upper(Str::random(8).'-'.Str::random(8).'-'.Str::random(8)); while (License::query()->where('key', $key)->exists());
        return $key;
    }
}

