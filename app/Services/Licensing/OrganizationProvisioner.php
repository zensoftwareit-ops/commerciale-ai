<?php

namespace App\Services\Licensing;

use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\PipelineStage;
use Illuminate\Support\Str;

class OrganizationProvisioner
{
    public function create(string $name, ?string $billingAccountRef = null): Organization
    {
        $organization = Organization::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'billing_account_ref' => $billingAccountRef,
            'timezone' => 'Europe/Rome',
            'locale' => 'it',
            'status' => 'onboarding',
        ]);

        foreach ([
            ['Nuovo', 'new', 'open'],
            ['Da valutare', 'to_review', 'open'],
            ['Qualificazione', 'qualification', 'open'],
            ['Qualificato', 'qualified', 'open'],
            ['Proposta', 'proposal', 'open'],
            ['Negoziazione', 'negotiation', 'open'],
            ['Vinto', 'won', 'won'],
            ['Perso', 'lost', 'lost'],
        ] as $position => [$label, $slug, $category]) {
            PipelineStage::create([
                'organization_id' => $organization->id,
                'name' => $label,
                'slug' => $slug,
                'system_category' => $category,
                'position' => $position + 1,
            ]);
        }

        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'commercial_name' => $name,
            'completeness' => 0,
        ]);

        return $organization;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'azienda';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Organization::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
