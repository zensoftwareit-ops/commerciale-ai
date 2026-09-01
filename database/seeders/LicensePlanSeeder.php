<?php

namespace Database\Seeders;

use App\Models\LicensePlan;
use Illuminate\Database\Seeder;

class LicensePlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Per iniziare a strutturare il processo commerciale con Daria.',
                'annual_price_cents' => 49000,
                'currency' => 'EUR',
                'seat_limit' => 1,
                'monthly_lead_limit' => 100,
                'monthly_ai_token_limit' => 500000,
                'features' => ['CRM e pipeline', 'Importazione e gestione lead', 'Assistente AI Daria', 'Supporto via email'],
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Per team commerciali che vogliono automatizzare e misurare la vendita.',
                'annual_price_cents' => 99000,
                'currency' => 'EUR',
                'seat_limit' => 3,
                'monthly_lead_limit' => 500,
                'monthly_ai_token_limit' => 2000000,
                'features' => ['Tutto di Starter', 'Fino a 3 utenti', 'Automazioni commerciali', 'Analisi avanzate', 'Supporto prioritario'],
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Per organizzazioni con volumi elevati e processi commerciali articolati.',
                'annual_price_cents' => 179000,
                'currency' => 'EUR',
                'seat_limit' => 8,
                'monthly_lead_limit' => 2000,
                'monthly_ai_token_limit' => 8000000,
                'features' => ['Tutto di Professional', 'Fino a 8 utenti', 'Volumi maggiorati', 'Onboarding dedicato', 'Supporto prioritario'],
                'sort_order' => 30,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $attributes) {
            $slug = $attributes['slug'];
            $configuredPriceId = config("commerciale-ai.billing.stripe_price_ids.{$slug}");
            $existing = LicensePlan::query()->where('slug', $slug)->first();

            $attributes['stripe_price_id'] = filled($configuredPriceId)
                ? $configuredPriceId
                : $existing?->stripe_price_id;

            LicensePlan::query()->updateOrCreate(['slug' => $slug], $attributes);
        }
    }
}
