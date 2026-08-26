<?php

namespace App\Services\Ai;

use App\Contracts\LeadReplyGenerator;
use App\Exceptions\ConversationHandoffRequired;
use App\Models\Activity;
use App\Models\AiAnalysis;
use App\Models\AiRun;
use App\Models\Lead;
use App\Models\LeadReply;
use App\Models\OrganizationSetting;
use App\Services\Quotations\BuildQuotation;
use App\Services\Licensing\LicenseUsageGuard;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class GenerateLeadReply
{
    public function __construct(
        private readonly LeadReplyGenerator $generator,
        private readonly BuildQuotation $quotationBuilder,
        private readonly LicenseUsageGuard $licenseGuard,
        private readonly RecordAiUsage $usageRecorder,
    ) {}

    public function handle(Lead $lead, AiAnalysis $analysis, ?int $actorId = null, array $extraContext = []): LeadReply
    {
        $this->licenseGuard->assertAiCapacity();
        $organizationId = app(TenantContext::class)->requireOrganization()->id;
        if (! filled($lead->email)) {
            throw ValidationException::withMessages(['reply' => 'Il lead non ha un indirizzo email valido.']);
        }

        $settings = OrganizationSetting::query()->first();
        $quotationResult = $this->quotationBuilder->handle($lead);
        $isInboundConversation = is_array(data_get($extraContext, 'incoming_email'));
        $completedGeneralTurns = $lead->replies()->where('status', 'sent')->where('delivery_mode', 'automatic')
            ->where('reply_kind', 'general')->count();
        if ($isInboundConversation && ! $quotationResult['quotation'] && $completedGeneralTurns >= 1) {
            throw new ConversationHandoffRequired('no_pricing_rule_after_conversation_turn');
        }
        $qualificationAttempts = $lead->replies()->where('status', 'sent')
            ->whereIn('reply_kind', ['qualification', 'initial_qualification'])->count();
        $context = ['organization' => $settings?->only([
            'commercial_name', 'business_description', 'products_services', 'tone_of_voice',
            'email_signature', 'appointment_details', 'promised_response_minutes',
        ]), 'quotation' => $quotationResult['context'], ...$extraContext,
            'conversation_history' => $this->conversationHistory($lead),
            'conversation_policy' => [
                'qualification_attempts' => $qualificationAttempts,
                'maximum_qualification_attempts' => 1,
                'must_not_ask_more_questions' => $qualificationAttempts >= 1,
            ],
        ];
        $run = AiRun::create([
            'organization_id' => $organizationId,
            'lead_id' => $lead->id,
            'operation' => 'reply_draft',
            'status' => 'running',
            'input_context' => ['analysis_id' => $analysis->id, ...$context],
            'started_at' => now(),
        ]);

        try {
            $result = $this->generator->generate($lead, $analysis, $context);
            $this->usageRecorder->handle($run, 'reply_draft', $result['_meta'] ?? []);
            validator($result, [
                'subject' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:10000'],
            ])->validate();
            $meta = $result['_meta'] ?? [];

            return DB::transaction(function () use ($organizationId, $lead, $analysis, $actorId, $run, $result, $meta, $context, $quotationResult): LeadReply {
                $run->update([
                    'status' => 'completed', 'provider' => $meta['provider'] ?? 'unknown',
                    'model' => $meta['model'] ?? 'unknown', 'policy_version' => $meta['policy_version'] ?? 'reply-draft-v1',
                    'output' => ['subject' => $result['subject'], 'body' => $result['body']],
                    'input_units' => $meta['input_units'] ?? 0, 'output_units' => $meta['output_units'] ?? 0,
                    'estimated_cost' => $meta['estimated_cost'] ?? 0, 'completed_at' => now(),
                ]);
                $missingQuotationFields = $quotationResult['context']['missing_fields'] ?? [];
                $replyKind = $quotationResult['quotation']
                    ? ($missingQuotationFields === [] || data_get($quotationResult, 'context.indicative') ? 'quotation' : 'qualification')
                    : 'general';
                if (data_get($context, 'automation_stage') === 'initial') {
                    $replyKind = match ($replyKind) {
                        'quotation' => 'initial_quotation',
                        'qualification' => 'initial_qualification',
                        default => 'initial',
                    };
                }
                $automationBlockers = $replyKind === 'general' || str_ends_with($replyKind, 'qualification')
                    ? $quotationResult['conversation_blockers']
                    : $quotationResult['blockers'];
                if (! is_array(data_get($context, 'incoming_email')) && data_get($context, 'automation_stage') !== 'initial') {
                    $automationBlockers[] = 'manual_draft';
                }
                $reply = LeadReply::create([
                    'organization_id' => $organizationId, 'lead_id' => $lead->id,
                    'ai_analysis_id' => $analysis->id, 'ai_run_id' => $run->id,
                    'status' => 'draft', 'parent_message_id' => data_get($context, 'incoming_email.message_id'),
                    'reply_kind' => $replyKind,
                    'automation_eligible' => $automationBlockers === [],
                    'automation_blockers' => $automationBlockers,
                    'recipient' => $lead->email,
                    'subject' => $result['subject'], 'body' => $result['body'],
                ]);
                $quotationResult['quotation']?->update(['lead_reply_id' => $reply->id]);
                $lead->update(['operational_status' => 'awaiting_approval', 'last_activity_at' => now()]);
                Activity::create([
                    'organization_id' => $organizationId, 'lead_id' => $lead->id, 'actor_id' => $actorId,
                    'type' => 'reply_draft_created', 'title' => 'Bozza email preparata',
                    'data' => ['reply_id' => $reply->id, 'analysis_id' => $analysis->id], 'occurred_at' => now(),
                ]);

                return $reply;
            });
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed', 'error_code' => 'reply_generation_failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000), 'completed_at' => now(),
            ]);
            throw $exception;
        }
    }

    private function conversationHistory(Lead $lead): array
    {
        $inbound = $lead->inboundEmails()->where('status', 'linked')->get()->map(fn ($email) => [
            'direction' => 'inbound', 'at' => $email->received_at?->toIso8601String(),
            'subject' => $email->subject, 'body' => mb_substr((string) $email->body, 0, 6000),
        ]);
        $outbound = $lead->replies()->where('status', 'sent')->get()->map(fn ($reply) => [
            'direction' => 'outbound', 'at' => $reply->sent_at?->toIso8601String(),
            'subject' => $reply->subject, 'body' => mb_substr((string) $reply->body, 0, 6000),
        ]);

        return $inbound->concat($outbound)->sortBy('at')->take(-20)->values()->all();
    }
}
