<?php

namespace App\Providers;

use App\Contracts\LeadAnalyzer;
use App\Services\Ai\FakeLeadAnalyzer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LeadAnalyzer::class, FakeLeadAnalyzer::class);
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
