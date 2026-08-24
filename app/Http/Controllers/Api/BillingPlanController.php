<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LicensePlan;
use Illuminate\Http\JsonResponse;

class BillingPlanController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => LicensePlan::query()->where('is_active', true)->orderBy('sort_order')->get()->map(fn (LicensePlan $plan) => [
            'name' => $plan->name, 'slug' => $plan->slug, 'description' => $plan->description,
            'annual_price_cents' => $plan->annual_price_cents, 'currency' => $plan->currency,
            'seat_limit' => $plan->seat_limit, 'monthly_lead_limit' => $plan->monthly_lead_limit,
            'monthly_ai_token_limit' => $plan->monthly_ai_token_limit, 'features' => $plan->features ?? [],
            'stripe_price_id' => $plan->stripe_price_id, 'purchasable' => filled($plan->stripe_price_id),
        ])->values()]);
    }
}

