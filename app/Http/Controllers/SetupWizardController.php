<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeDocument;
use App\Models\OrganizationSetting;
use App\Services\Organizations\GenerateOrganizationSetup;
use App\Services\Organizations\OrganizationLifecycle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class SetupWizardController extends Controller
{
    private const DOCUMENTS = [
        'services' => ['title' => 'Servizi e proposta di valore', 'type' => 'service'],
        'faq' => ['title' => 'FAQ commerciali', 'type' => 'faq'],
        'request_management' => ['title' => 'Gestione e qualificazione delle richieste', 'type' => 'text'],
        'pricing_guidance' => ['title' => 'Prezzi e preparazione dei preventivi', 'type' => 'pricing'],
    ];

    public function create(): View
    {
        $settings = OrganizationSetting::query()->first();

        return view('setup-wizard.create', [
            'description' => old('description', $settings?->business_description),
            'aiStatus' => [
                'provider' => (string) config('commerciale-ai.ai_provider'),
                'configured' => config('commerciale-ai.ai_provider') !== 'openai'
                    || filled(config('commerciale-ai.openai.api_key')),
            ],
        ]);
    }

    public function generate(Request $request, GenerateOrganizationSetup $generator, TenantContext $tenants): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:80', 'max:10000'],
        ]);

        try {
            $draft = $generator->handle(trim($data['description']));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors([
                'wizard' => 'Non e stato possibile generare il setup: '.$exception->getMessage(),
            ]);
        }

        $request->session()->put('setup_wizard_draft', [
            'id' => (string) Str::uuid(),
            'organization_id' => $tenants->requireOrganization()->id,
            'description' => trim($data['description']),
            'draft' => $draft,
            'generated_at' => now()->toIso8601String(),
        ]);

        return redirect()->route('setup-wizard.preview');
    }

    public function preview(Request $request, TenantContext $tenants): View|RedirectResponse
    {
        $payload = $request->session()->get('setup_wizard_draft');
        if (! is_array($payload) || ($payload['organization_id'] ?? null) !== $tenants->requireOrganization()->id) {
            return redirect()->route('setup-wizard.create')->withErrors(['wizard' => 'Genera prima una nuova bozza di configurazione.']);
        }

        return view('setup-wizard.preview', [
            'payload' => $payload,
            'draft' => $payload['draft'],
            'documents' => self::DOCUMENTS,
        ]);
    }

    public function apply(
        Request $request,
        TenantContext $tenants,
        OrganizationLifecycle $lifecycle,
    ): RedirectResponse {
        $payload = $request->session()->get('setup_wizard_draft');
        $organization = $tenants->requireOrganization();
        if (! is_array($payload)
            || ($payload['organization_id'] ?? null) !== $organization->id
            || ! hash_equals((string) ($payload['id'] ?? ''), (string) $request->input('draft_id'))) {
            return redirect()->route('setup-wizard.create')->withErrors(['wizard' => 'La bozza non e piu valida. Generala nuovamente.']);
        }

        $data = $request->validate($this->applicationRules());
        DB::transaction(function () use ($data, $request, $organization): void {
            $profile = $data['profile'];
            $profile['qualification_questions'] = collect(preg_split('/\r\n|\r|\n/', $profile['qualification_questions_text']))
                ->map(fn (string $question): string => trim($question))
                ->filter()
                ->take(8)
                ->values()
                ->all();
            unset($profile['qualification_questions_text']);
            $profile['completeness'] = OrganizationSetting::completenessFor($profile);
            $settings = OrganizationSetting::query()->firstOrNew(['organization_id' => $organization->id]);
            $settings->fill($profile);
            if (! $settings->exists) {
                $settings->fill([
                    'conversation_automation_enabled' => false,
                    'auto_send_quotes_enabled' => false,
                    'internal_test_only' => true,
                    'max_automatic_replies' => 3,
                    'auto_analyze_new_leads' => false,
                    'auto_send_initial_email' => false,
                ]);
            }
            $settings->save();

            $selected = [];
            foreach (self::DOCUMENTS as $key => $definition) {
                $document = $data['knowledge'][$key];
                if (! (bool) $document['enabled']) {
                    continue;
                }
                $selected[] = $key;
                KnowledgeDocument::query()->updateOrCreate(
                    ['organization_id' => $organization->id, 'source_key' => $key],
                    [
                        'updated_by' => $request->user()->id,
                        'title' => $document['title'],
                        'type' => $definition['type'],
                        'content' => $document['content'],
                        'status' => 'active',
                        'source' => 'setup_wizard',
                        'structured_data' => ['generated_by' => 'setup_wizard', 'version' => 1],
                    ],
                );
            }
            KnowledgeDocument::query()
                ->where('source', 'setup_wizard')
                ->when($selected !== [], fn ($query) => $query->whereNotIn('source_key', $selected))
                ->update(['status' => 'archived', 'updated_by' => $request->user()->id]);
        });

        $request->session()->forget('setup_wizard_draft');
        $lifecycle->refresh($organization);

        return redirect()->route('settings.organization')->with('status', 'Setup AI applicato. Controlla il profilo e la knowledge base prima di attivare le automazioni.');
    }

    private function applicationRules(): array
    {
        $rules = [
            'draft_id' => ['required', 'uuid'],
            'profile' => ['required', 'array'],
            'profile.legal_name' => ['nullable', 'string', 'max:255'],
            'profile.commercial_name' => ['required', 'string', 'max:255'],
            'profile.industry' => ['required', 'string', 'max:255'],
            'profile.business_description' => ['required', 'string', 'max:5000'],
            'profile.products_services' => ['required', 'string', 'max:5000'],
            'profile.service_area' => ['nullable', 'string', 'max:255'],
            'profile.ideal_customer' => ['required', 'string', 'max:5000'],
            'profile.pricing_rules' => ['nullable', 'string', 'max:5000'],
            'profile.differentiators' => ['nullable', 'string', 'max:5000'],
            'profile.qualification_questions_text' => ['required', 'string', 'max:4000'],
            'profile.exclusion_criteria' => ['nullable', 'string', 'max:5000'],
            'profile.tone_of_voice' => ['required', 'string', 'max:255'],
            'profile.email_signature' => ['required', 'string', 'max:2000'],
            'profile.appointment_details' => ['nullable', 'string', 'max:2000'],
            'profile.promised_response_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'knowledge' => ['required', 'array'],
        ];
        foreach (array_keys(self::DOCUMENTS) as $key) {
            $rules['knowledge.'.$key] = ['required', 'array'];
            $rules['knowledge.'.$key.'.enabled'] = ['required', 'boolean'];
            $rules['knowledge.'.$key.'.title'] = ['required', 'string', 'max:255'];
            $rules['knowledge.'.$key.'.content'] = ['required', 'string', 'max:50000'];
        }

        return $rules;
    }
}
