<?php

namespace App\Http\Controllers;

use App\Models\OrganizationSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationSettingsController extends Controller
{
    public function edit(): View
    {
        $settings = OrganizationSetting::query()->firstOrNew();
        $aiStatus = [
            'provider' => config('commerciale-ai.ai_provider'),
            'model' => config('commerciale-ai.openai.model'),
            'configured' => config('commerciale-ai.ai_provider') !== 'openai' || filled(config('commerciale-ai.openai.api_key')),
        ];

        return view('settings.organization', compact('settings', 'aiStatus'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'legal_name' => ['nullable', 'string', 'max:255'], 'commercial_name' => ['required', 'string', 'max:255'],
            'industry' => ['required', 'string', 'max:255'], 'business_description' => ['required', 'string', 'max:5000'],
            'products_services' => ['required', 'string', 'max:5000'], 'service_area' => ['nullable', 'string', 'max:255'],
            'ideal_customer' => ['required', 'string', 'max:5000'], 'pricing_rules' => ['nullable', 'string', 'max:5000'],
            'differentiators' => ['nullable', 'string', 'max:5000'], 'qualification_questions_text' => ['nullable', 'string', 'max:5000'],
            'exclusion_criteria' => ['nullable', 'string', 'max:5000'], 'tone_of_voice' => ['required', 'string', 'max:255'],
            'email_signature' => ['required', 'string', 'max:2000'], 'appointment_details' => ['nullable', 'string', 'max:2000'],
            'promised_response_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'], 'authorized_sender' => ['nullable', 'email', 'max:255'],
        ]);
        $data['qualification_questions'] = collect(preg_split('/\r\n|\r|\n/', $data['qualification_questions_text'] ?? ''))->map(fn (string $line): string => trim($line))->filter()->values()->all();
        unset($data['qualification_questions_text']);
        $data['completeness'] = OrganizationSetting::completenessFor($data);
        OrganizationSetting::query()->updateOrCreate(['organization_id' => app(TenantContext::class)->id()], $data);

        return back()->with('status', 'Profilo aziendale aggiornato.');
    }
}
