<?php

namespace App\Http\Controllers;

use App\Models\OrganizationSetting;
use App\Models\PricingRule;
use App\Models\Quotation;
use App\Support\Tenancy\TenantContext;
use App\Services\Organizations\OrganizationLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
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

    public function update(Request $request, OrganizationLifecycle $lifecycle, TenantContext $tenants): RedirectResponse
    {
        $sectionFields = [
            'identity' => ['legal_name', 'commercial_name', 'website_url', 'industry', 'service_area'],
            'offering' => ['business_description', 'products_services', 'ideal_customer', 'pricing_rules', 'differentiators'],
            'lead_handling' => ['qualification_questions_text', 'exclusion_criteria', 'tone_of_voice', 'email_signature', 'appointment_details', 'promised_response_minutes'],
            'automation' => ['conversation_automation_enabled', 'auto_send_quotes_enabled', 'internal_test_only', 'automation_allowed_recipients_text', 'max_automatic_replies', 'max_auto_quote_amount', 'auto_analyze_new_leads', 'auto_send_initial_email'],
            'privacy' => ['data_retention_days', 'privacy_cleanup_enabled'],
            'quotation_document' => ['quotation_logo', 'remove_quotation_logo', 'quotation_primary_color', 'quotation_header_text', 'quotation_intro_text', 'quotation_company_details', 'quotation_payment_terms', 'quotation_footer', 'quotation_footer_left', 'quotation_footer_center', 'quotation_footer_right', 'quotation_acceptance_text'],
        ];
        $section = (string) $request->input('section', 'all');
        $request->merge(['section' => $section]);
        $rules = [
            'legal_name' => ['nullable', 'string', 'max:255'], 'commercial_name' => ['required', 'string', 'max:255'],
            'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'industry' => ['required', 'string', 'max:255'], 'business_description' => ['required', 'string', 'max:5000'],
            'products_services' => ['required', 'string', 'max:5000'], 'service_area' => ['nullable', 'string', 'max:255'],
            'ideal_customer' => ['required', 'string', 'max:5000'], 'pricing_rules' => ['nullable', 'string', 'max:5000'],
            'differentiators' => ['nullable', 'string', 'max:5000'], 'qualification_questions_text' => ['nullable', 'string', 'max:5000'],
            'exclusion_criteria' => ['nullable', 'string', 'max:5000'], 'tone_of_voice' => ['required', 'string', 'max:255'],
            'email_signature' => ['required', 'string', 'max:2000'], 'appointment_details' => ['nullable', 'string', 'max:2000'],
            'promised_response_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'conversation_automation_enabled' => ['nullable', 'boolean'], 'auto_send_quotes_enabled' => ['nullable', 'boolean'],
            'internal_test_only' => ['nullable', 'boolean'], 'automation_allowed_recipients_text' => ['nullable', 'string', 'max:5000'],
            'max_automatic_replies' => ['required', 'integer', 'min:1', 'max:10'], 'max_auto_quote_amount' => ['nullable', 'numeric', 'min:0'],
            'auto_analyze_new_leads' => ['nullable', 'boolean'], 'auto_send_initial_email' => ['nullable', 'boolean'],
            'data_retention_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'privacy_cleanup_enabled' => ['nullable', 'boolean'],
            'quotation_logo' => ['nullable', 'file', 'mimes:jpg,jpeg', 'max:2048'],
            'remove_quotation_logo' => ['nullable', 'boolean'],
            'quotation_company_details' => ['nullable', 'string', 'max:2000'],
            'quotation_payment_terms' => ['nullable', 'string', 'max:3000'],
            'quotation_footer' => ['nullable', 'string', 'max:1000'],
            'quotation_primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'quotation_header_text' => ['nullable', 'string', 'max:1000'],
            'quotation_intro_text' => ['nullable', 'string', 'max:2000'],
            'quotation_footer_left' => ['nullable', 'string', 'max:255'],
            'quotation_footer_center' => ['nullable', 'string', 'max:255'],
            'quotation_footer_right' => ['nullable', 'string', 'max:255'],
            'quotation_acceptance_text' => ['nullable', 'string', 'max:1000'],
        ];
        $request->validate(['section' => ['required', 'in:'.implode(',', [...array_keys($sectionFields), 'all'])]]);
        $data = $request->validate($section === 'all' ? $rules : Arr::only($rules, $sectionFields[$section]));

        if (array_key_exists('qualification_questions_text', $data)) {
            $data['qualification_questions'] = collect(preg_split('/\r\n|\r|\n/', $data['qualification_questions_text'] ?? ''))->map(fn (string $line): string => trim($line))->filter()->values()->all();
            unset($data['qualification_questions_text']);
        }
        if (array_key_exists('automation_allowed_recipients_text', $data)) {
            $data['automation_allowed_recipients'] = collect(preg_split('/[\r\n,]+/', $data['automation_allowed_recipients_text'] ?? ''))->map(fn ($line) => mb_strtolower(trim($line)))->filter()->values()->all();
            unset($data['automation_allowed_recipients_text']);
        }
        foreach (['conversation_automation_enabled', 'auto_send_quotes_enabled', 'internal_test_only', 'auto_analyze_new_leads', 'auto_send_initial_email', 'privacy_cleanup_enabled'] as $boolean) {
            if ($section === 'all' || in_array($boolean, $sectionFields[$section], true)) {
                $data[$boolean] = (bool) ($data[$boolean] ?? false);
            }
        }
        $current = OrganizationSetting::query()->first();
        if (($data['remove_quotation_logo'] ?? false) && $current?->quotation_logo_path) {
            Storage::disk('local')->delete($current->quotation_logo_path);
            $data['quotation_logo_path'] = null;
        }
        unset($data['remove_quotation_logo']);
        if ($request->hasFile('quotation_logo')) {
            if ($current?->quotation_logo_path) Storage::disk('local')->delete($current->quotation_logo_path);
            $data['quotation_logo_path'] = $request->file('quotation_logo')->storeAs(
                'organizations/'.$tenants->requireOrganization()->id.'/branding', 'quotation-logo.jpg', 'local'
            );
        }
        unset($data['quotation_logo']);
        if (($data['auto_analyze_new_leads'] ?? false) && ! $current?->auto_analyze_new_leads) {
            $data['new_lead_automation_started_at'] = now();
        }
        $data['completeness'] = OrganizationSetting::completenessFor(array_merge($current?->toArray() ?? [], $data));
        OrganizationSetting::query()->updateOrCreate(['organization_id' => app(TenantContext::class)->id()], $data);
        if ($section === 'quotation_document') {
            Quotation::query()->whereNotNull('pdf_path')->whereHas('reply', fn ($query) => $query->where('status', '!=', 'sent'))
                ->get()->each(function (Quotation $quotation): void {
                    Storage::disk('local')->delete($quotation->pdf_path);
                    $quotation->update(['pdf_path' => null, 'pdf_generated_at' => null]);
                });
        }
        $lifecycle->refresh($tenants->requireOrganization());

        $labels = [
            'identity' => 'Identità aziendale aggiornata.', 'offering' => 'Offerta commerciale aggiornata.',
            'lead_handling' => 'Gestione delle richieste aggiornata.', 'automation' => 'Automazioni aggiornate.',
            'privacy' => 'Impostazioni privacy aggiornate.', 'all' => 'Profilo aziendale aggiornato.',
            'quotation_document' => 'Modello PDF dei preventivi aggiornato.',
        ];

        return back()->with('status', $labels[$section]);
    }
}
