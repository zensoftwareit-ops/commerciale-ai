<?php

namespace App\Services\Leads;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\LeadReply;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Services\Ai\AnalyzeLead;
use App\Services\Ai\GenerateLeadReply;
use App\Services\Mail\SendLeadReply;
use App\Support\Tenancy\TenantContext;
use Throwable;

class RunNewLeadAutomation
{
    public function __construct(
        private readonly AnalyzeLead $analyzer,
        private readonly GenerateLeadReply $replyGenerator,
        private readonly SendLeadReply $sender,
    ) {}

    /** @return array{organizations:int,candidates:int,analyzed:int,drafted:int,sent:int,failed:int} */
    public function handle(int $limit = 25): array
    {
        $stats = ['organizations' => 0, 'candidates' => 0, 'analyzed' => 0, 'drafted' => 0, 'sent' => 0, 'failed' => 0];
        foreach (Organization::query()->cursor() as $organization) {
            app(TenantContext::class)->set($organization);
            try {
                $settings = OrganizationSetting::query()->first();
                if (! $settings?->auto_analyze_new_leads || ! $settings->conversation_automation_enabled) continue;
                $stats['organizations']++;
                $allowed = collect($settings->automation_allowed_recipients ?? [])->map(fn ($email) => mb_strtolower(trim((string) $email)))->filter();
                $internalOnly = $settings->internal_test_only || ! config('commerciale-ai.automation.external_send_enabled');
                if ($internalOnly && $allowed->isEmpty()) continue;

                $leads = Lead::query()
                    ->whereNotNull('email_normalized')
                    ->whereNull('initial_automation_completed_at')
                    ->where('initial_automation_attempts', '<', 3)
                    ->where('created_at', '>=', $settings->new_lead_automation_started_at ?? now())
                    ->when($internalOnly, fn ($query) => $query->whereIn('email_normalized', $allowed->all()))
                    ->oldest()->limit(max(1, min($limit, 100)))->get();

                foreach ($leads as $lead) {
                    $stats['candidates']++;
                    $claimed = Lead::query()->whereKey($lead->id)
                        ->where('initial_automation_attempts', $lead->initial_automation_attempts)
                        ->whereNull('initial_automation_completed_at')
                        ->update(['initial_automation_attempts' => $lead->initial_automation_attempts + 1, 'initial_automation_attempted_at' => now()]);
                    if ($claimed !== 1) continue;

                    try {
                        $analysis = $lead->analyses()->first();
                        if (! $analysis) {
                            $analysis = $this->analyzer->handle($lead->fresh());
                            $stats['analyzed']++;
                        }
                        $reply = $lead->replies()->where('status', 'draft')
                            ->whereIn('reply_kind', ['initial', 'initial_qualification', 'initial_quotation'])
                            ->latest()->first();
                        if (! $reply) {
                            $reply = $this->replyGenerator->handle($lead->fresh(), $analysis, null, ['automation_stage' => 'initial']);
                            $stats['drafted']++;
                        }
                        $this->prepareInitialReply($reply, $settings);
                        if ($reply->automation_eligible) {
                            $this->sender->handle($reply->fresh(), automatic: true);
                            $stats['sent']++;
                        }
                        $lead->update(['initial_automation_completed_at' => now(), 'initial_automation_error' => null]);
                    } catch (Throwable $exception) {
                        report($exception);
                        $stats['failed']++;
                        $lead->update(['initial_automation_error' => mb_substr($exception->getMessage(), 0, 2000)]);
                        Activity::create([
                            'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
                            'type' => 'initial_automation_failed', 'title' => 'Automazione iniziale non completata',
                            'data' => ['attempt' => $lead->initial_automation_attempts + 1], 'occurred_at' => now(),
                        ]);
                    }
                }
            } finally {
                app(TenantContext::class)->clear();
            }
        }
        return $stats;
    }

    private function prepareInitialReply(LeadReply $reply, OrganizationSetting $settings): void
    {
        $blockers = collect($reply->automation_blockers ?? []);
        if ($reply->reply_kind === 'initial') {
            $blockers = $blockers->reject(fn ($blocker) => in_array($blocker, ['no_matching_pricing_rule', 'auto_send_quotes_disabled', 'amount_over_limit'], true));
        }
        $blockers = $blockers->values()->all();
        if (! $settings->auto_send_initial_email) $blockers[] = 'auto_send_initial_email_disabled';
        if (! $settings->internal_test_only && ! config('commerciale-ai.automation.external_send_enabled')) $blockers[] = 'external_send_disabled_on_server';
        $reply->update([
            'automation_blockers' => array_values(array_unique($blockers)),
            'automation_eligible' => $blockers === [],
        ]);
    }
}
