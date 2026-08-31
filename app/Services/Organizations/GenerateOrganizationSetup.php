<?php

namespace App\Services\Organizations;

use App\Contracts\SetupWizardGenerator;
use App\Models\AiRun;
use App\Models\OrganizationSetting;
use App\Services\Ai\RecordAiUsage;
use App\Services\Licensing\LicenseUsageGuard;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Validator;
use Throwable;

class GenerateOrganizationSetup
{
    public function __construct(
        private readonly SetupWizardGenerator $generator,
        private readonly LicenseUsageGuard $licenseGuard,
        private readonly RecordAiUsage $usageRecorder,
        private readonly TenantContext $tenants,
    ) {}

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
            $draft = $this->generator->generate($description, $existingProfile, $website);
            $this->usageRecorder->handle($run, 'setup_wizard', $draft['_meta'] ?? []);
            $this->validate($draft);
            $meta = $draft['_meta'] ?? [];
            unset($draft['_meta']);
            $run->update([
                'status' => 'completed',
                'provider' => $meta['provider'] ?? 'unknown',
                'model' => $meta['model'] ?? 'unknown',
                'policy_version' => $meta['policy_version'] ?? 'setup-wizard-v1',
                'output' => $draft,
                'input_units' => $meta['input_units'] ?? 0,
                'output_units' => $meta['output_units'] ?? 0,
                'estimated_cost' => $meta['estimated_cost'] ?? 0,
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
            'profile.qualification_questions' => ['required', 'array', 'max:8'],
            'profile.qualification_questions.*' => ['required', 'string', 'max:500'],
            'profile.promised_response_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'knowledge' => ['required', 'array'],
            'knowledge.services' => ['required', 'string', 'max:50000'],
            'knowledge.faq' => ['required', 'string', 'max:50000'],
            'knowledge.request_management' => ['required', 'string', 'max:50000'],
            'knowledge.pricing_guidance' => ['required', 'string', 'max:50000'],
            'assumptions' => ['required', 'array', 'max:12'],
            'assumptions.*' => ['required', 'string', 'max:1000'],
        ])->validate();
    }
}
