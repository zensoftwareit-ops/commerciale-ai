<?php

namespace App\Services\Mail;

use App\Models\LeadReply;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Support\Tenancy\TenantContext;
use App\Services\Notifications\NotifyAutomationFailure;
use Throwable;

class RunConversationAutomation
{
    public function __construct(
        private readonly SendLeadReply $sender,
        private readonly NotifyAutomationFailure $failureNotifier,
    ) {}

    /** @return array{organizations:int,candidates:int,sent:int,failed:int} */
    public function handle(int $limit = 25): array
    {
        $stats = ['organizations' => 0, 'candidates' => 0, 'sent' => 0, 'failed' => 0];
        foreach (Organization::query()->cursor() as $organization) {
            app(TenantContext::class)->set($organization);
            try {
                $settings = OrganizationSetting::query()->first();
                if (! $settings?->conversation_automation_enabled) continue;
                $stats['organizations']++;
                $this->refreshInternalTestEligibility($settings);
                $maxAttempts = max(1, (int) config('commerciale-ai.automation.delivery_max_attempts', 3));
                $replies = LeadReply::query()->where('status', 'draft')->whereIn('reply_kind', ['general', 'qualification', 'quotation'])
                    ->where('automation_eligible', true)
                    ->where('automation_attempts', '<', $maxAttempts)
                    ->where(fn ($query) => $query->whereNull('automation_next_attempt_at')->orWhere('automation_next_attempt_at', '<=', now()))
                    ->latest()->limit(500)->get()
                    ->unique('lead_id')->take(max(1, min($limit, 100)));
                foreach ($replies as $reply) {
                    $stats['candidates']++;
                    try {
                        LeadReply::query()->where('lead_id', $reply->lead_id)->where('status', 'draft')
                            ->where('automation_eligible', true)->where('id', '!=', $reply->id)
                            ->update(['automation_eligible' => false, 'automation_blockers' => json_encode(['superseded_by_newer_draft'], JSON_THROW_ON_ERROR)]);
                        $this->sender->handle($reply, automatic: true);
                        $reply->update(['automation_next_attempt_at' => null, 'automation_failed_at' => null]);
                        $stats['sent']++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $stats['failed']++;
                        $attempts = (int) $reply->automation_attempts + 1;
                        $final = $attempts >= $maxAttempts;
                        $base = max(1, (int) config('commerciale-ai.automation.retry_base_minutes', 5));
                        $blockers = collect($reply->automation_blockers ?? []);
                        if ($final) {
                            $blockers->push('automatic_delivery_failed');
                        }
                        $reply->update([
                            'automation_attempts' => $attempts,
                            'automation_next_attempt_at' => $final ? null : now()->addMinutes($base * (2 ** ($attempts - 1))),
                            'automation_failed_at' => $final ? now() : null,
                            'automation_eligible' => ! $final,
                            'automation_blockers' => $blockers->unique()->values()->all(),
                            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                        ]);
                        if ($final) {
                            $this->failureNotifier->handle($reply->lead, $exception->getMessage());
                        }
                    }
                }
            } finally {
                app(TenantContext::class)->clear();
            }
        }
        return $stats;
    }

    private function refreshInternalTestEligibility(OrganizationSetting $settings): void
    {
        $internalOnly = $settings->internal_test_only || ! config('commerciale-ai.automation.external_send_enabled');
        if (! $internalOnly) return;

        $allowed = collect($settings->automation_allowed_recipients ?? [])
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter();
        if ($allowed->isEmpty()) return;

        LeadReply::query()
            ->with('lead')
            ->where('status', 'draft')
            ->whereIn('reply_kind', ['general', 'qualification', 'quotation'])
            ->where('automation_eligible', false)
            ->latest()
            ->limit(500)
            ->get()
            ->each(function (LeadReply $reply) use ($allowed): void {
                if (! $allowed->contains($reply->lead->email_normalized)) return;

                $blockers = collect($reply->automation_blockers ?? []);
                $refreshed = $blockers->reject(fn ($blocker) => in_array($blocker, [
                    'external_send_disabled_on_server',
                    'recipient_not_in_internal_allowlist',
                ], true))->values();
                if ($refreshed->count() === $blockers->count()) return;

                $reply->update([
                    'automation_blockers' => $refreshed->all(),
                    'automation_eligible' => $refreshed->isEmpty(),
                ]);
            });
    }
}
