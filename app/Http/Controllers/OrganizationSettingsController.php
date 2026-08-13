<?php

namespace App\Http\Controllers;

use App\Models\OrganizationSetting;
use App\Models\PricingRule;
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

        $pricingRules = PricingRule::query()->orderBy('name')->get();
        return view('settings.organization', compact('settings', 'aiStatus', 'pricingRules'));
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
            'conversation_automation_enabled' => ['nullable', 'boolean'], 'auto_send_quotes_enabled' => ['nullable', 'boolean'],
            'internal_test_only' => ['nullable', 'boolean'], 'automation_allowed_recipients_text' => ['nullable', 'string', 'max:5000'],
            'max_automatic_replies' => ['required', 'integer', 'min:1', 'max:10'], 'max_auto_quote_amount' => ['nullable', 'numeric', 'min:0'],
            'auto_analyze_new_leads' => ['nullable', 'boolean'], 'auto_send_initial_email' => ['nullable', 'boolean'],
        ]);
        $data['qualification_questions'] = collect(preg_split('/\r\n|\r|\n/', $data['qualification_questions_text'] ?? ''))->map(fn (string $line): string => trim($line))->filter()->values()->all();
        unset($data['qualification_questions_text']);
        $data['automation_allowed_recipients'] = collect(preg_split('/[\r\n,]+/', $data['automation_allowed_recipients_text'] ?? ''))->map(fn ($line) => mb_strtolower(trim($line)))->filter()->values()->all();
        unset($data['automation_allowed_recipients_text']);
        $data['conversation_automation_enabled'] = (bool) ($data['conversation_automation_enabled'] ?? false);
        $data['auto_send_quotes_enabled'] = (bool) ($data['auto_send_quotes_enabled'] ?? false);
        $data['internal_test_only'] = (bool) ($data['internal_test_only'] ?? false);
        $data['auto_analyze_new_leads'] = (bool) ($data['auto_analyze_new_leads'] ?? false);
        $data['auto_send_initial_email'] = (bool) ($data['auto_send_initial_email'] ?? false);
        $current = OrganizationSetting::query()->first();
        if ($data['auto_analyze_new_leads'] && ! $current?->auto_analyze_new_leads) {
            $data['new_lead_automation_started_at'] = now();
        }
        $data['completeness'] = OrganizationSetting::completenessFor($data);
        OrganizationSetting::query()->updateOrCreate(['organization_id' => app(TenantContext::class)->id()], $data);

        return back()->with('status', 'Profilo aziendale aggiornato.');
    }
}
