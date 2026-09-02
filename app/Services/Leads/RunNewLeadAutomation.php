<?php

namespace App\Services\Leads;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\LeadReply;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\MailboxAccount;
use App\Services\Ai\AnalyzeLead;
use App\Services\Ai\GenerateLeadReply;
use App\Services\Mail\SendLeadReply;
use App\Support\Tenancy\TenantContext;
use App\Services\Notifications\NotifyAutomationFailure;
use Throwable;

class RunNewLeadAutomation
{
    public function __construct(
        private readonly AnalyzeLead $analyzer,
        private readonly GenerateLeadReply $replyGenerator,
        private readonly SendLeadReply $sender,
        private readonly NotifyAutomationFailure $failureNotifier,
    ) {}

    /** @return array{organizations:int,candidates:int,analyzed:int,drafted:int,sent:int,failed:int} */
    public function handle(int $limit = 25, ?string $leadId = null): array
    {
        $stats = ['organizations' => 0, 'candidates' => 0, 'analyzed' => 0, 'drafted' => 0, 'sent' => 0, 'failed' => 0];
        foreach (Organization::query()->cursor() as $organization) {
            app(TenantContext::class)->set($organization);
            try {
                $settings = OrganizationSetting::query()->first();
                // L'analisi iniziale ha un interruttore proprio: non dipende
                // dall'automazione delle risposte successive della conversazione.
                if (! $settings?->auto_analyze_new_leads) continue;
                $stats['organizations']++;
                $maxAttempts = max(1, (int) config('commerciale-ai.automation.delivery_max_attempts', 3));
                $leads = Lead::query()
                    ->whereNotNull('email_normalized')
                    ->when($leadId, fn ($query) => $query
                        ->whereKey($leadId)
                        ->whereDoesntHave('replies', fn ($replies) => $replies
                            ->where('status', 'sent')
                            ->whereIn('reply_kind', ['initial', 'initial_qualification', 'initial_quotation'])))
                    ->when(! $leadId, fn ($query) => $query
                        ->whereNull('initial_automation_completed_at')
                        ->where('initial_automation_attempts', '<', $maxAttempts)
                        ->where(fn ($retry) => $retry->whereNull('initial_automation_next_attempt_at')->orWhere('initial_automation_next_attempt_at', '<=', now()))
                        ->where('created_at', '>=', $settings->new_lead_automation_started_at ?? now()))
                    ->oldest()->limit(max(1, min($limit, 100)))->get();

                foreach ($leads as $lead) {
                    $stats['candidates']++;
                    $claim = Lead::query()->whereKey($lead->id)
                        ->where('initial_automation_attempts', $lead->initial_automation_attempts);
                    if (! $leadId) $claim->whereNull('initial_automation_completed_at');
                    $claimed = $claim->update([
                        'initial_automation_attempts' => $lead->initial_automation_attempts + 1,
                        'initial_automation_attempted_at' => now(),
                        'initial_automation_completed_at' => null,
                    ]);
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
                        $lead->update([
                            'initial_automation_completed_at' => now(),
                            'initial_automation_next_attempt_at' => null,
                            'initial_automation_failed_at' => null,
                            'initial_automation_error' => null,
                        ]);
                    } catch (Throwable $exception) {
                        report($exception);
                        $stats['failed']++;
                        $attempts = (int) $lead->initial_automation_attempts + 1;
                        $final = $attempts >= $maxAttempts;
                        $base = max(1, (int) config('commerciale-ai.automation.retry_base_minutes', 5));
                        $lead->update([
                            'initial_automation_next_attempt_at' => $final ? null : now()->addMinutes($base * (2 ** ($attempts - 1))),
                            'initial_automation_failed_at' => $final ? now() : null,
                            'initial_automation_error' => mb_substr($exception->getMessage(), 0, 2000),
                        ]);
                        Activity::create([
                            'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
                            'type' => 'initial_automation_failed', 'title' => 'Automazione iniziale non completata',
                            'data' => ['attempt' => $attempts, 'final' => $final], 'occurred_at' => now(),
                        ]);
                        if ($final) {
                            $this->failureNotifier->handle($lead, $exception->getMessage());
                        }
                    }
                }
            } finally {
                app(TenantContext::class)->clear();
            }
        }
        return $stats;
    }

    /** @return array<int, array<string, int|string>> */
    public function diagnose(): array
    {
        $rows = [];
        $maxAttempts = max(1, (int) config('commerciale-ai.automation.delivery_max_attempts', 3));
        foreach (Organization::query()->cursor() as $organization) {
            app(TenantContext::class)->set($organization);
            try {
                $settings = OrganizationSetting::query()->first();
                $allowed = collect($settings?->automation_allowed_recipients ?? [])->map(fn ($email) => mb_strtolower(trim((string) $email)))->filter();
                $internalOnly = ($settings?->internal_test_only ?? true) || ! config('commerciale-ai.automation.external_send_enabled');
                $base = Lead::query();
                $startedAt = $settings?->new_lead_automation_started_at;
                $rows[] = [
                    'organization' => $organization->name,
                    'conversation' => $settings?->conversation_automation_enabled ? 'ON' : 'OFF',
                    'analysis' => $settings?->auto_analyze_new_leads ? 'ON' : 'OFF',
                    'initial_email' => $settings?->auto_send_initial_email ? 'ON' : 'OFF',
                    'internal_only' => $internalOnly ? 'YES' : 'NO',
                    'allowlist' => $allowed->implode(', ') ?: '(vuota)',
                    'started_at' => $startedAt?->format('Y-m-d H:i:s') ?? '(non impostato)',
                    'total_leads' => (clone $base)->count(),
                    'without_email' => (clone $base)->whereNull('email_normalized')->count(),
                    'before_activation' => $startedAt ? (clone $base)->where('created_at', '<', $startedAt)->count() : (clone $base)->count(),
                    'not_allowed' => $internalOnly ? (clone $base)->whereNotNull('email_normalized')->whereNotIn('email_normalized', $allowed->all())->count() : 0,
                    'completed' => (clone $base)->whereNotNull('initial_automation_completed_at')->count(),
                    'failed_max' => (clone $base)->whereNull('initial_automation_completed_at')->where('initial_automation_attempts', '>=', $maxAttempts)->count(),
                    'eligible_now' => $settings?->auto_analyze_new_leads && $startedAt
                        ? (clone $base)->whereNotNull('email_normalized')->whereNull('initial_automation_completed_at')
                            ->where('initial_automation_attempts', '<', $maxAttempts)
                            ->where(fn ($query) => $query->whereNull('initial_automation_next_attempt_at')->orWhere('initial_automation_next_attempt_at', '<=', now()))
                            ->where('created_at', '>=', $startedAt)->count()
                        : 0,
                ];
            } finally {
                app(TenantContext::class)->clear();
            }
        }

        return $rows;
    }

    private function prepareInitialReply(LeadReply $reply, OrganizationSetting $settings): void
    {
        // La prima email ha interruttori propri e non deve ereditare i blocchi
        // dell'automazione delle risposte successive. I controlli di ambiente e
        // allowlist vengono ricalcolati subito sotto in modo coerente.
        $blockers = collect($reply->automation_blockers ?? [])->reject(fn ($blocker) => in_array($blocker, [
            'conversation_automation_disabled',
            'external_send_disabled_on_server',
            'recipient_not_in_internal_allowlist',
        ], true));
        if ($reply->reply_kind === 'initial') {
            $blockers = $blockers->reject(fn ($blocker) => in_array($blocker, ['no_matching_pricing_rule', 'auto_send_quotes_disabled', 'amount_over_limit'], true));
        }
        $blockers = $blockers->values()->all();
        if (! $settings->auto_send_initial_email) $blockers[] = 'auto_send_initial_email_disabled';
        $allowed = collect($settings->automation_allowed_recipients ?? [])
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter();
        $internalOnly = $settings->internal_test_only || ! config('commerciale-ai.automation.external_send_enabled');
        if ($internalOnly && ! $allowed->contains($reply->lead->email_normalized)) $blockers[] = 'recipient_not_allowed';
        if (! $internalOnly && ! MailboxAccount::query()->where('is_active', true)->where('domain_verification_status', 'verified')->exists()) {
            $blockers[] = 'sender_domain_not_verified';
        }
        $reply->update([
            'automation_blockers' => array_values(array_unique($blockers)),
            'automation_eligible' => $blockers === [],
        ]);
    }
}
