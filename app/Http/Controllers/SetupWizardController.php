<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
use App\Models\KnowledgeDocument;
use App\Models\OrganizationSetting;
use App\Services\Organizations\GenerateOrganizationSetup;
use App\Services\Organizations\OrganizationLifecycle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'websiteUrl' => old('website_url', $settings?->website_url),
            'aiStatus' => [
                'provider' => (string) config('commerciale-ai.ai_provider'),
                'configured' => config('commerciale-ai.ai_provider') !== 'openai'
                    || filled(config('commerciale-ai.openai.api_key')),
            ],
        ]);
    }

    public function generate(
        Request $request,
        GenerateOrganizationSetup $generator,
    ): RedirectResponse {
        $data = $request->validate([
            'description' => ['nullable', 'required_without:website_url', 'string', 'min:80', 'max:10000'],
            'website_url' => ['nullable', 'required_without:description', 'url:http,https', 'max:2048'],
        ], [
            'description.required_without' => 'Inserisci una descrizione oppure l’URL del sito aziendale.',
            'description.min' => 'La descrizione deve contenere almeno 80 caratteri.',
            'description.max' => 'La descrizione non può superare 10.000 caratteri.',
            'website_url.required_without' => 'Inserisci l’URL del sito oppure una descrizione dell’attività.',
            'website_url.url' => 'Inserisci un URL completo, ad esempio https://www.azienda.it.',
            'website_url.max' => 'L’URL del sito è troppo lungo.',
        ]);
        $description = trim((string) ($data['description'] ?? ''));

        try {
            $run = $generator->enqueue($description, filled($data['website_url'] ?? null) ? (string) $data['website_url'] : null, (string) $request->user()->id);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors([
                'wizard' => 'Non e stato possibile generare il setup: '.$exception->getMessage(),
            ]);
        }

        return redirect()->route($run->status === 'completed' ? 'setup-wizard.preview' : 'setup-wizard.status', $run->id);
    }

    public function status(Request $request, string $draft): View|RedirectResponse
    {
        $run = $this->draft($draft, $request);
        if ($run->status === 'completed') {
            return redirect()->route('setup-wizard.preview', $run->id);
        }

        return view('setup-wizard.status', ['run' => $run]);
    }

    public function retry(Request $request, string $draft, GenerateOrganizationSetup $generator): RedirectResponse
    {
        $run = $this->draft($draft, $request);
        $generator->retry($run);

        return redirect()->route('setup-wizard.status', $run->id);
    }

    public function preview(Request $request, string $draft): View|RedirectResponse
    {
        $run = $this->draft($draft, $request);
        if ($run->status !== 'completed') {
            return redirect()->route('setup-wizard.status', $run->id);
        }
        if (isset($run->output['applied_at'])) {
            return redirect()->route('onboarding')->with('status', 'Questa configurazione è già stata applicata.');
        }
        $context = $run->input_context ?? [];
        $draftOutput = $run->output;
        $draftOutput['profile']['website_url'] = $context['website_url'] ?? ($draftOutput['profile']['website_url'] ?? null);

        return view('setup-wizard.preview', [
            'payload' => [
                'id' => $run->id,
                'description' => $context['activity_description'] ?? '',
                'website' => filled($context['website_url'] ?? null) ? [
                    'url' => $context['website_url'], 'pages' => $context['website_pages'] ?? [],
                ] : null,
            ],
            'draft' => $draftOutput,
            'documents' => self::DOCUMENTS,
        ]);
    }

    public function apply(
        Request $request,
        TenantContext $tenants,
        OrganizationLifecycle $lifecycle,
    ): RedirectResponse {
        $organization = $tenants->requireOrganization();
        $run = $this->draft((string) $request->input('draft_id'), $request);
        abort_unless($run->status === 'completed' && ! isset($run->output['applied_at']), 422);

        $data = $request->validate($this->applicationRules());
        DB::transaction(function () use ($data, $request, $organization, $run): void {
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
            $run->update(['output' => $run->output + ['applied_at' => now()->toIso8601String()]]);
        });

        $lifecycle->refresh($organization);

        return redirect()->route('onboarding')->with('status', 'Base di conoscenza salvata. Ora Daria esegue i controlli di prontezza prima di consentire le automazioni.');
    }

    private function draft(string $id, Request $request): AiRun
    {
        $run = AiRun::query()->where('operation', 'setup_wizard')->findOrFail($id);
        abort_unless(($run->input_context['user_id'] ?? null) === (string) $request->user()->id, 404);

        return $run;
    }

    private function applicationRules(): array
    {
        $rules = [
            'draft_id' => ['required', 'uuid'],
            'confirm_review' => ['accepted'],
            'profile' => ['required', 'array'],
            'profile.legal_name' => ['nullable', 'string', 'max:255'],
            'profile.commercial_name' => ['required', 'string', 'max:255'],
            'profile.website_url' => ['nullable', 'url:http,https', 'max:2048'],
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
