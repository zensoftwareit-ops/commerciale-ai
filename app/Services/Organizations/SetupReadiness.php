<?php

namespace App\Services\Organizations;

use App\Models\InboundSource;
use App\Models\KnowledgeDocument;
use App\Models\MailboxAccount;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\PricingRule;
use App\Support\Tenancy\TenantContext;

class SetupReadiness
{
    public function __construct(private readonly TenantContext $tenants) {}

    /** @return array<string, mixed> */
    public function assess(Organization $organization): array
    {
        return $this->tenants->run($organization, function () use ($organization): array {
            $settings = OrganizationSetting::query()->first();
            $knowledge = KnowledgeDocument::query()->where('status', 'active')->get();
            $usableKnowledge = $knowledge->filter(fn (KnowledgeDocument $document): bool => mb_strlen(trim((string) $document->content)) >= 80);
            $mailbox = MailboxAccount::query()->where('is_active', true)->first();
            $pricing = PricingRule::query()->where('is_active', true)->get();
            $executablePricing = $pricing->filter(fn (PricingRule $rule): bool => is_array($rule->pricing_formula) && $rule->pricing_formula !== [])->count();
            $quoteMode = (bool) ($settings?->direct_quote_enabled || $settings?->quotation_review_mode || $settings?->auto_send_quotes_enabled);

            $checks = [
                'license' => (bool) $organization->activeLicense(),
                'profile' => (int) ($settings?->completeness ?? 0) >= 100,
                'knowledge' => $usableKnowledge->count() >= 2,
                'source' => InboundSource::query()->where('is_active', true)->exists(),
                'mailbox' => $mailbox !== null && filled($mailbox->from_address) && filled($mailbox->from_name),
                'mailbox_tested' => $mailbox !== null && $mailbox->last_tested_at !== null && $mailbox->last_outbound_tested_at !== null,
                'pricing' => $pricing->isNotEmpty(),
                'pricing_executable' => $executablePricing > 0,
            ];
            $workspaceRequired = ['license', 'profile', 'knowledge', 'source'];
            $done = count(array_filter($workspaceRequired, fn (string $key): bool => $checks[$key]));
            $ready = $done === count($workspaceRequired);
            $automationReady = $ready && $checks['mailbox_tested'] && (! $quoteMode || $checks['pricing_executable']);
            $warnings = [];
            if ($knowledge->isNotEmpty() && ! $checks['knowledge']) {
                $warnings[] = 'La knowledge base contiene materiale troppo breve per essere considerato affidabile.';
            }
            if ($checks['mailbox'] && ! $checks['mailbox_tested']) {
                $warnings[] = 'La casella è configurata ma i test di entrata e uscita non sono entrambi riusciti.';
            }
            if ($quoteMode && ! $checks['pricing_executable']) {
                $warnings[] = 'I preventivi sono richiesti, ma non esiste ancora una ricetta di calcolo attiva ed eseguibile.';
            }
            if (($settings?->conversation_automation_enabled || $settings?->auto_send_initial_email || $settings?->auto_send_quotes_enabled) && ! $automationReady) {
                $warnings[] = 'Alcune automazioni risultano selezionate, ma il workspace non ha ancora superato tutti i controlli per l’invio automatico.';
            }

            return [
                ...$checks,
                'status' => $organization->status,
                'progress' => (int) round(($done / count($workspaceRequired)) * 100),
                'ready' => $ready,
                'automation_ready' => $automationReady,
                'quote_mode' => $quoteMode,
                'knowledge_documents' => $usableKnowledge->count(),
                'pricing_rules' => $pricing->count(),
                'executable_pricing_rules' => $executablePricing,
                'warnings' => $warnings,
                'missing' => collect([
                    'license' => 'Licenza attiva', 'profile' => 'Profilo aziendale completo',
                    'knowledge' => 'Knowledge base verificabile', 'source' => 'Sorgente lead attiva',
                ])->filter(fn (string $label, string $key): bool => ! $checks[$key])->values()->all(),
            ];
        });
    }
}
