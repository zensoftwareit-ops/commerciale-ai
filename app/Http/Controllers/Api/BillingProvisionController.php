<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Organization;
use App\Models\User;
use App\Services\Licensing\ProvisionLicense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class BillingProvisionController extends Controller
{
    public function store(Request $request, ProvisionLicense $provision): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'string', 'max:255'], 'event_type' => ['required', 'string', 'max:80'],
            'external_account_id' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'], 'company' => ['nullable', 'string', 'max:255'],
            'plan_slug' => ['required', 'string', 'max:100'], 'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'stripe_customer_id' => ['nullable', 'string', 'max:255'], 'stripe_subscription_id' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'trialing', 'past_due', 'unpaid', 'canceled', 'paused', 'suspended'])],
            'starts_at' => ['nullable', 'date'], 'current_period_ends_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date'],
            'cancel_at_period_end' => ['nullable', 'boolean'],
        ]);
        try {
            $result = $provision->handle($data);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
        return response()->json(['data' => $this->serialize($result['license']), 'account_created' => $result['account_created'], 'reset_link_sent' => $result['reset_link_sent'], 'login_url' => url('/login')]);
    }

    public function show(string $externalAccountId): JsonResponse
    {
        $license = License::query()->with('plan')->where('external_account_id', $externalAccountId)->latest()->firstOrFail();
        return response()->json(['data' => $this->serialize($license)]);
    }

    public function updateAccount(Request $request, string $externalAccountId): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'company' => ['nullable', 'string', 'max:255']]);
        $user = User::query()->where('external_account_id', $externalAccountId)->firstOrFail();
        $organization = Organization::query()->where('billing_account_ref', $externalAccountId)->firstOrFail();
        $user->update(['name' => $data['name']]);
        $organization->update(['name' => $data['company'] ?: $data['name']]);
        $organization->settings()->update(['commercial_name' => $data['company'] ?: $data['name']]);
        return response()->json(['data' => ['name' => $user->name, 'company' => $organization->name]]);
    }

    private function serialize(License $license): array
    {
        return ['key' => $license->key, 'status' => $license->status, 'plan' => $license->plan->only(['name', 'slug', 'seat_limit', 'monthly_lead_limit', 'monthly_ai_token_limit']), 'current_period_ends_at' => $license->current_period_ends_at?->toIso8601String(), 'cancel_at_period_end' => $license->cancel_at_period_end, 'usable' => $license->isUsable()];
    }
}

