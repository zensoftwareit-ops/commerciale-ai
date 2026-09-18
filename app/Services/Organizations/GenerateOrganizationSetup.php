<?php

namespace App\Services\Organizations;

use App\Contracts\SetupWizardGenerator;
use App\Jobs\ProcessOrganizationSetup;
use App\Models\AiRun;
use App\Models\OrganizationSetting;
use App\Services\Ai\RecordAiUsage;
use App\Services\Licensing\LicenseUsageGuard;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GenerateOrganizationSetup
{
    public function __construct(
        private readonly SetupWizardGenerator $generator,
        private readonly LicenseUsageGuard $licenseGuard,
        private readonly RecordAiUsage $usageRecorder,
        private readonly TenantContext $tenants,
        private readonly WebsiteContentReader $websites,
    ) {}

    public function enqueue(string $description, ?string $websiteUrl, string $userId): AiRun
    {
        $this->licenseGuard->assertAiCapacity();
        $organization = $this->tenants->requireOrganization();
        $run = AiRun::create([
            'organization_id' => $organization->id,
            'operation' => 'setup_wizard',
            'status' => 'queued',
            'policy_version' => 'setup-wizard-v2',
            'input_context' => [
                'user_id' => $userId,
                'activity_description' => $description,
                'website_url' => $websiteUrl,
            ],
            'started_at' => now(),
        ]);

        try {
            ProcessOrganizationSetup::dispatch((string) $organization->id, (string) $run->id)->onQueue('ai');

            return $run->refresh();
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_code' => 'setup_queue_failed',
                'error_message' => class_basename($exception).': '.Str::limit($exception->getMessage(), 700),
                'completed_at' => now(),
            ]);
            throw $exception;
        }
    }

    public function retry(AiRun $run): AiRun
    {
        if ($run->operation !== 'setup_wizard' || $run->status !== 'failed') {
            throw new RuntimeException('Questa configurazione non può essere riavviata.');
        }
        $this->licenseGuard->assertAiCapacity();
        $run->update([
            'status' => 'queued', 'error_code' => null, 'error_message' => null,
            'output' => null, 'started_at' => now(), 'completed_at' => null,
        ]);
        ProcessOrganizationSetup::dispatch((string) $run->organization_id, (string) $run->id)->onQueue('ai');

        return $run->refresh();
    }

    public function process(string $runId): AiRun
    {
        $run = AiRun::query()->where('operation', 'setup_wizard')->findOrFail($runId);
        if ($run->status === 'completed') {
            return $run;
        }
        $context = $run->input_context ?? [];
        $description = trim((string) ($context['activity_description'] ?? ''));
        $websiteUrl = trim((string) ($context['website_url'] ?? ''));
        $run->update(['status' => 'running', 'error_code' => null, 'error_message' => null, 'started_at' => now()]);

        try {
            $website = $websiteUrl !== '' ? $this->websites->read($websiteUrl) : [];
            $draft = $this->generateDraft($run, $description, $website);
            $run->update([
                'input_context' => $context + ['website_pages' => collect($website['pages'] ?? [])->map(fn (array $page): array => [
                    'url' => $page['url'], 'title' => $page['title'], 'characters' => mb_strlen($page['text']),
                ])->all()],
                'status' => 'completed', 'output' => $draft, 'completed_at' => now(),
            ]);

            return $run->refresh();
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_code' => $exception instanceof RuntimeException ? 'setup_source_or_provider_failed' : 'setup_invalid_output',
                'error_message' => class_basename($exception).': '.Str::limit($exception->getMessage(), 700),
                'completed_at' => now(),
            ]);
            report($exception);
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function handle(string $description, array $website = []): array
    {
        $this->licenseGuard->assertAiCapacity();
        $organization = $this->tenants->requireOrganization();
        $existing = OrganizationSetting::query()->first();
        $existingProfile = $existing?->only([
            'legal_name', 'commercial_name', 'website_url', 'industry', 'business_description', 'products_services',
            'service_area', 'ideal_customer', 'pricing_rules', 'differentiators', 'qualification_questions',
            'exclusion_criteria', 'tone_of_voice', 'email_signature', 'appointment_details', 'promised_response_minutes',
        ]) ?? [];
        $run = AiRun::create([
            'organization_id' => $organization->id,
            'operation' => 'setup_wizard',
            'status' => 'running',
            'policy_version' => 'setup-wizard-v1',
            'input_context' => [
                'activity_description' => $description,
                'existing_profile' => $existingProfile,
                'website_url' => $website['url'] ?? null,
                'website_pages' => collect($website['pages'] ?? [])->pluck('url')->values()->all(),
            ],
            'started_at' => now(),
        ]);

        try {
            $draft = $this->generateDraft($run, $description, $website, $existingProfile);
            $meta = $run->fresh()->only(['provider', 'model', 'policy_version', 'input_units', 'output_units', 'estimated_cost']);
            $run->update([
                'status' => 'completed',
                'output' => $draft,
                'completed_at' => now(),
            ]);

            return $draft;
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_code' => 'setup_wizard_failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function generateDraft(AiRun $run, string $description, array $website, ?array $existingProfile = null): array
    {
        $existingProfile ??= OrganizationSetting::query()->first()?->only([
            'legal_name', 'commercial_name', 'website_url', 'industry', 'business_description', 'products_services',
            'service_area', 'ideal_customer', 'pricing_rules', 'differentiators', 'qualification_questions',
            'exclusion_criteria', 'tone_of_voice', 'email_signature', 'appointment_details', 'promised_response_minutes',
        ]) ?? [];
        $draft = $this->normalize($this->generator->generate($description, $existingProfile, $website));
        $this->usageRecorder->handle($run, 'setup_wizard', $draft['_meta'] ?? []);
        $this->validate($draft);
        $meta = $draft['_meta'] ?? [];
        unset($draft['_meta']);
        $run->update([
            'provider' => $meta['provider'] ?? 'unknown',
            'model' => $meta['model'] ?? 'unknown',
            'policy_version' => $meta['policy_version'] ?? 'setup-wizard-v2',
            'input_units' => $meta['input_units'] ?? 0,
            'output_units' => $meta['output_units'] ?? 0,
            'estimated_cost' => $meta['estimated_cost'] ?? 0,
        ]);

        return $draft;
    }

    private function validate(array $draft): void
    {
        Validator::make($draft, [
            'profile' => ['required', 'array'],
            'profile.commercial_name' => ['required', 'string', 'max:255'],
            'profile.industry' => ['required', 'string', 'max:255'],
            'profile.business_description' => ['required', 'string', 'max:5000'],
            'profile.products_services' => ['required', 'string', 'max:5000'],
            'profile.ideal_customer' => ['required', 'string', 'max:5000'],
            'profile.tone_of_voice' => ['required', 'string', 'max:255'],
            'profile.email_signature' => ['required', 'string', 'max:2000'],
            'profile.qualification_questions' => ['present', 'array', 'min:1', 'max:8'],
            'profile.qualification_questions.*' => ['required', 'string', 'max:500'],
            'profile.promised_response_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'knowledge' => ['required', 'array'],
            'knowledge.services' => ['required', 'string', 'max:50000'],
            'knowledge.faq' => ['required', 'string', 'max:50000'],
            'knowledge.request_management' => ['required', 'string', 'max:50000'],
            'knowledge.pricing_guidance' => ['required', 'string', 'max:50000'],
            'assumptions' => ['present', 'array', 'max:12'],
            'assumptions.*' => ['required', 'string', 'max:1000'],
            'quality' => ['required', 'array'],
            'quality.source_coverage' => ['required', 'in:weak,partial,strong'],
            'quality.confirmed_facts' => ['present', 'array', 'max:12'],
            'quality.confirmed_facts.*' => ['required', 'string', 'max:1000'],
            'quality.needs_confirmation' => ['present', 'array', 'max:12'],
            'quality.needs_confirmation.*' => ['required', 'string', 'max:1000'],
        ], [
            'required' => 'OpenAI non ha compilato il campo :attribute.',
            'present' => 'OpenAI non ha restituito il campo :attribute.',
            'array' => 'OpenAI ha restituito un formato non valido per :attribute.',
            'min' => 'OpenAI deve proporre almeno una voce per :attribute.',
            'max' => 'OpenAI ha generato un contenuto troppo esteso per :attribute.',
            'string' => 'OpenAI ha restituito un testo non valido per :attribute.',
            'integer' => 'OpenAI ha restituito un numero non valido per :attribute.',
        ], [
            'profile.qualification_questions' => 'le domande di qualificazione',
            'assumptions' => 'le informazioni da verificare',
            'quality.confirmed_facts' => 'i fatti confermati dalle fonti',
            'quality.needs_confirmation' => 'le informazioni da confermare',
            'knowledge.services' => 'i servizi',
            'knowledge.faq' => 'le FAQ',
            'knowledge.request_management' => 'la gestione delle richieste',
            'knowledge.pricing_guidance' => 'le indicazioni sui prezzi',
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function normalize(array $draft): array
    {
        $signature = trim((string) data_get($draft, 'profile.email_signature', ''));
        if ($signature !== '') {
            return $draft;
        }

        $commercialName = trim((string) data_get($draft, 'profile.commercial_name', ''));
        data_set($draft, 'profile.email_signature', $commercialName !== '' ? 'Il team di '.$commercialName : 'Il team commerciale');
        $assumptions = is_array($draft['assumptions'] ?? null) ? $draft['assumptions'] : [];
        $assumptions[] = 'La firma email è stata proposta automaticamente perché non era indicata nelle fonti.';
        $draft['assumptions'] = $assumptions;

        return $draft;
    }
}
