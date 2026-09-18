<?php

namespace App\Jobs;

use App\Models\AiRun;
use App\Models\Organization;
use App\Services\Organizations\GenerateOrganizationSetup;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class ProcessOrganizationSetup implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 240;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $organizationId, public readonly string $runId) {}

    public function handle(GenerateOrganizationSetup $setup, TenantContext $tenancy): void
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        $tenancy->run($organization, fn () => $setup->process($this->runId));
    }

    public function failed(?Throwable $exception): void
    {
        $run = AiRun::withoutGlobalScopes()->find($this->runId);
        if (! $run || $run->status === 'completed') {
            return;
        }
        $run->update([
            'status' => 'failed', 'error_code' => 'setup_worker_failed',
            'error_message' => $exception ? class_basename($exception).': '.Str::limit($exception->getMessage(), 700) : 'Il processo di configurazione è terminato in modo inatteso.',
            'completed_at' => now(),
        ]);
    }
}
