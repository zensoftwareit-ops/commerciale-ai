<?php

namespace App\Services\Ai;

use App\Models\AiRun;
use App\Models\CommercialNotification;
use App\Models\UsageRecord;
use App\Support\Tenancy\TenantContext;

class RecordAiUsage
{
    public function __construct(private readonly AiUsageSummary $summaries) {}

    public function handle(AiRun $run, string $operation, array $meta): UsageRecord
    {
        $values = [
            'organization_id' => $run->organization_id,
            'operation' => $operation,
            'provider' => $meta['provider'] ?? 'unknown',
            'model' => $meta['model'] ?? 'unknown',
            'input_units' => (int) ($meta['input_units'] ?? 0),
            'output_units' => (int) ($meta['output_units'] ?? 0),
            'estimated_cost' => (float) ($meta['estimated_cost'] ?? 0),
            'occurred_at' => now(),
        ];

        $run->update([
            'provider' => $values['provider'], 'model' => $values['model'],
            'input_units' => $values['input_units'], 'output_units' => $values['output_units'],
            'estimated_cost' => $values['estimated_cost'],
        ]);

        $usage = UsageRecord::query()->updateOrCreate(['ai_run_id' => $run->id], $values);
        $this->notifyThresholds();

        return $usage;
    }

    private function notifyThresholds(): void
    {
        $organization = app(TenantContext::class)->requireOrganization();
        $summary = $this->summaries->forOrganization($organization);
        if ($summary['limit'] === null || $summary['percentage'] < 80) return;

        $threshold = $summary['percentage'] >= 100 ? 100 : 80;
        $type = 'ai_budget_'.$threshold;
        if (CommercialNotification::query()->where('type', $type)->where('created_at', '>=', now()->startOfMonth())->exists()) return;

        foreach ($organization->users()->wherePivot('role', 'owner')->get() as $owner) {
            CommercialNotification::create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
                'type' => $type,
                'title' => $threshold === 100 ? 'Budget AI mensile esaurito' : 'Budget AI vicino al limite',
                'message' => number_format($summary['total_tokens'], 0, ',', '.').' token utilizzati su '.number_format($summary['limit'], 0, ',', '.').'.',
                'data' => ['threshold' => $threshold, 'usage' => $summary],
            ]);
        }
    }
}
