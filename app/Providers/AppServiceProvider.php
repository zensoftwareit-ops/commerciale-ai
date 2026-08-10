<?php

namespace App\Providers;

use App\Contracts\InboundMailbox;
use App\Contracts\LeadAnalyzer;
use App\Contracts\LeadReplyGenerator;
use App\Services\Ai\FakeLeadAnalyzer;
use App\Services\Ai\FakeLeadReplyGenerator;
use App\Services\Ai\OpenAiLeadAnalyzer;
use App\Services\Ai\OpenAiLeadReplyGenerator;
use App\Services\Mail\WebklexInboundMailbox;
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
        $this->app->singleton(LeadReplyGenerator::class, fn ($app) => config('commerciale-ai.ai_provider') === 'openai'
            ? $app->make(OpenAiLeadReplyGenerator::class)
            : $app->make(FakeLeadReplyGenerator::class));
        $this->app->singleton(InboundMailbox::class, WebklexInboundMailbox::class);
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
