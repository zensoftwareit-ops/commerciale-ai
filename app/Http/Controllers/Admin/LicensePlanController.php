<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LicensePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LicensePlanController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_if(LicensePlan::query()->count() >= 3, 422, 'Sono previsti al massimo tre pacchetti.');
        LicensePlan::create($this->validated($request));
        return back()->with('status', 'Pacchetto creato.');
    }

    public function update(Request $request, string $plan): RedirectResponse
    {
        $plan = LicensePlan::query()->findOrFail($plan);
        $plan->update($this->validated($request, $plan));
        return back()->with('status', 'Pacchetto aggiornato.');
    }

    private function validated(Request $request, ?LicensePlan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('license_plans', 'slug')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:3000'], 'annual_price_cents' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'], 'seat_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'monthly_lead_limit' => ['nullable', 'integer', 'min:1'], 'monthly_ai_token_limit' => ['nullable', 'integer', 'min:1'],
            'features_text' => ['nullable', 'string', 'max:5000'],
            'stripe_price_id' => ['nullable', 'string', 'max:255', Rule::unique('license_plans', 'stripe_price_id')->ignore($plan?->id)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100'], 'is_active' => ['nullable', 'boolean'],
        ]);
        $data['features'] = collect(preg_split('/\r\n|\r|\n/', $data['features_text'] ?? ''))->map(fn ($line) => trim($line))->filter()->values()->all();
        unset($data['features_text']);
        $data['currency'] = mb_strtoupper($data['currency']);
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }
}

