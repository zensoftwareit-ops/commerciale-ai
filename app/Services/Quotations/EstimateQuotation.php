<?php

namespace App\Services\Quotations;

use App\Contracts\QuotationEstimator;
use App\Models\AiAnalysis;
use App\Models\AiRun;
use App\Models\Lead;
use App\Models\PricingRule;
use App\Services\Ai\RecordAiUsage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class EstimateQuotation
{
    public function __construct(private readonly QuotationEstimator $estimator, private readonly RecordAiUsage $usageRecorder) {}

    /** @return array<string, mixed> */
    public function handle(Lead $lead, AiAnalysis $analysis, PricingRule $rule): array
    {
        $input = [
            'pricing_rule' => [
                'name' => $rule->name, 'minimum_price' => (float) $rule->minimum_price,
                'maximum_price' => (float) $rule->maximum_price, 'includes' => $rule->includes, 'excludes' => $rule->excludes,
            ],
            'lead' => [
                'requested_service' => $lead->requested_service,
                'request_summary' => $analysis->summary,
                'requested_services' => $analysis->requested_services,
                'request_data' => $lead->request_data,
            ],
            'conversation' => $lead->inboundEmails()->oldest('received_at')->get(['subject', 'body'])->map(fn ($email) => [
                'subject' => $email->subject, 'body' => mb_substr((string) $email->body, 0, 6000),
            ])->concat($lead->whatsappMessages()->where('direction', 'inbound')->oldest('received_at')->get(['body'])->map(fn ($message) => [
                'subject' => 'WhatsApp', 'body' => mb_substr((string) $message->body, 0, 6000),
            ]))->take(-20)->values()->all(),
        ];
        $run = AiRun::create([
            'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
            'operation' => 'quotation_estimate', 'status' => 'running', 'input_context' => $input, 'started_at' => now(),
        ]);

        try {
            $result = $this->estimator->estimate($input);
            $validated = Validator::make($result, [
                'scope_title' => ['required', 'string', 'max:180'], 'scope_description' => ['required', 'string', 'max:3000'],
                'deliverables' => ['present', 'array', 'max:12'], 'deliverables.*' => ['string', 'max:500'],
                'assumptions' => ['present', 'array', 'max:8'], 'assumptions.*' => ['string', 'max:500'],
                'complexity_score' => ['required', 'integer', 'between:0,100'],
                'confidence' => ['required', 'numeric', 'between:0,1'], 'rationale' => ['nullable', 'string', 'max:1200'],
            ])->validate();
            $this->usageRecorder->handle($run, 'quotation_estimate', $result['_meta'] ?? []);
            $run->update(['status' => 'completed', 'output' => $validated, 'completed_at' => now()]);

            return $validated;
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'error_code' => 'quotation_estimate_failed', 'error_message' => mb_substr($exception->getMessage(), 0, 2000), 'completed_at' => now()]);
            throw $exception;
        }
    }
}
