<?php

namespace App\Services\Licensing;

use App\Models\Lead;
use App\Models\License;
use App\Models\UsageRecord;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class LicenseUsageGuard
{
    public function assertLeadCapacity(): void
    {
        $license = $this->license(); if (! $license || $license->plan->monthly_lead_limit === null) return;
        $used = Lead::query()->where('created_at', '>=', now()->startOfMonth())->count();
        if ($used >= $license->plan->monthly_lead_limit) throw ValidationException::withMessages(['license' => 'Limite mensile di lead raggiunto per il pacchetto '.$license->plan->name.'.']);
    }

    public function assertAiCapacity(): void
    {
        $license = $this->license(); if (! $license || $license->plan->monthly_ai_token_limit === null) return;
        $used = (int) UsageRecord::query()->where('occurred_at', '>=', now()->startOfMonth())->selectRaw('COALESCE(SUM(input_units + output_units),0) AS total')->value('total');
        if ($used >= $license->plan->monthly_ai_token_limit) {
            throw ValidationException::withMessages(['license' => 'Budget AI mensile esaurito per il pacchetto '.$license->plan->name.'. Il conteggio ripartirà il primo giorno del prossimo mese.']);
        }
    }

    private function license(): ?License
    {
        $license = app(TenantContext::class)->requireOrganization()->activeLicense();
        if (! $license && ! config('commerciale-ai.billing.enforcement_enabled')) return null;
        if (! $license) throw ValidationException::withMessages(['license' => 'La licenza non è attiva.']);
        return $license;
    }
}
