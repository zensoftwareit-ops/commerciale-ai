<?php

namespace Database\Seeders;

use App\Models\InboundSource;
use App\Models\Organization;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\Leads\CreateLead;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::firstOrCreate(['slug' => 'zen-software-demo'], ['name' => 'Zen Software Demo', 'timezone' => 'Europe/Rome', 'locale' => 'it']);
        $user = User::firstOrCreate(['email' => 'demo@commerciale-ai.test'], ['name' => 'Gabriele Demo', 'password' => Hash::make('CommercialeAI!2026')]);
        $organization->users()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
        app(TenantContext::class)->set($organization);
        foreach ([
            ['Nuovo', 'new', 'open'], ['Da valutare', 'to_review', 'open'], ['Qualificazione', 'qualification', 'open'], ['Qualificato', 'qualified', 'open'], ['Proposta', 'proposal', 'open'], ['Negoziazione', 'negotiation', 'open'], ['Vinto', 'won', 'won'], ['Perso', 'lost', 'lost'], ['Non idoneo', 'disqualified', 'lost'], ['Irreperibile', 'unreachable', 'lost'],
        ] as $position => [$name,$slug,$category]) {
            PipelineStage::firstOrCreate(['organization_id' => $organization->id, 'slug' => $slug], ['name' => $name, 'system_category' => $category, 'position' => $position + 1]);
        }
        InboundSource::firstOrCreate(['key' => 'preventivositoweb-demo'], ['organization_id' => $organization->id, 'name' => 'PreventivoSitoWeb Demo', 'secret' => 'change-me-in-local-env', 'is_active' => true]);
        if (! $organization->leads()->exists()) {
            app(CreateLead::class)->handle(['source_label' => 'preventivositoweb.it', 'name' => 'Mario Rossi', 'email' => 'mario.rossi@example.test', 'company' => 'Rossi Demo Srl', 'requested_service' => 'Rifacimento sito web', 'request_data' => ['message' => 'Vorrei rinnovare il sito aziendale.', 'budget' => '1000-2000 EUR'], 'consent_data' => ['privacy_accepted' => true, 'marketing_accepted' => false]]);
        }
        app(TenantContext::class)->clear();
    }
}
