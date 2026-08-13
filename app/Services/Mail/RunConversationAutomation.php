<?php

namespace App\Services\Mail;

use App\Models\LeadReply;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Support\Tenancy\TenantContext;
use Throwable;

class RunConversationAutomation
{
    public function __construct(private readonly SendLeadReply $sender) {}

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
                $replies = LeadReply::query()->where('status', 'draft')->whereIn('reply_kind', ['qualification', 'quotation'])
                    ->where('automation_eligible', true)->latest()->limit(500)->get()
                    ->unique('lead_id')->take(max(1, min($limit, 100)));
                foreach ($replies as $reply) {
                    $stats['candidates']++;
                    try {
                        LeadReply::query()->where('lead_id', $reply->lead_id)->where('status', 'draft')
                            ->where('automation_eligible', true)->where('id', '!=', $reply->id)
                            ->update(['automation_eligible' => false, 'automation_blockers' => json_encode(['superseded_by_newer_draft'], JSON_THROW_ON_ERROR)]);
                        $this->sender->handle($reply, automatic: true);
                        $stats['sent']++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $stats['failed']++;
                    }
                }
            } finally {
                app(TenantContext::class)->clear();
            }
        }
        return $stats;
    }
}
