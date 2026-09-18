<?php

namespace App\Jobs;

use App\Models\AiRun;
use App\Models\Organization;
use App\Services\Ai\ImportPricingDocuments;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class ProcessPricingImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 300;
    public bool $failOnTimeout = true;

    public function __construct(public readonly string $organizationId, public readonly string $runId) {}

    public function handle(ImportPricingDocuments $importer, TenantContext $tenancy): void
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        $tenancy->run($organization, fn () => $importer->process($this->runId));
    }

    public function failed(?Throwable $exception): void
    {
        File::deleteDirectory(storage_path('app/private/pricing-imports/'.$this->organizationId.'/'.$this->runId));
        $run = AiRun::withoutGlobalScopes()->find($this->runId);
        if (! $run) return;
        $context = $run->input_context ?? [];
        unset($context['stored_files']);
        if (in_array($run->status, ['completed', 'failed'], true)) {
            $run->update(['input_context' => $context]);
            return;
        }
        $run->update([
            'status' => 'failed', 'error_code' => 'pricing_import_worker_failed',
            'error_message' => $exception ? class_basename($exception).': '.Str::limit($exception->getMessage(), 700) : 'Il processo di analisi è terminato in modo inatteso.',
            'input_context' => $context, 'completed_at' => now(),
        ]);
    }
}
