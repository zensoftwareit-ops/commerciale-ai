<?php

namespace App\Providers;

use App\Contracts\LeadAnalyzer;
use App\Services\Ai\FakeLeadAnalyzer;
use App\Services\Ai\OpenAiLeadAnalyzer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LeadAnalyzer::class, fn ($app) => config('commerciale-ai.ai_provider') === 'openai'
            ? $app->make(OpenAiLeadAnalyzer::class)
            : $app->make(FakeLeadAnalyzer::class));
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
