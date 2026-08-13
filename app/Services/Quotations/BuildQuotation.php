<?php

namespace App\Services\Quotations;

use App\Models\Lead;
use App\Models\OrganizationSetting;
use App\Models\PricingRule;
use App\Models\Quotation;
use Illuminate\Support\Str;

class BuildQuotation
{
    /** @return array{quotation:Quotation|null,context:array|null,blockers:array,conversation_blockers:array} */
    public function handle(Lead $lead): array
    {
        $settings = OrganizationSetting::query()->first();
        $inbound = $lead->inboundEmails()->first();
        $haystack = $this->normalize(implode(' ', array_filter([$lead->requested_service, json_encode($lead->request_data, JSON_UNESCAPED_UNICODE), $inbound?->subject, $inbound?->body])));
        $ranked = PricingRule::query()->where('is_active', true)->get()
            ->map(fn (PricingRule $rule) => ['rule' => $rule, 'score' => collect($rule->keywords)->filter(fn ($keyword) => str_contains($haystack, $this->normalize((string) $keyword)))->count()])
            ->filter(fn ($item) => $item['score'] > 0)->sortByDesc('score')->values();

        if ($ranked->isEmpty()) return ['quotation' => null, 'context' => null, 'blockers' => ['no_matching_pricing_rule'], 'conversation_blockers' => ['no_matching_pricing_rule']];
        if ($ranked->count() > 1 && $ranked[0]['score'] === $ranked[1]['score']) return ['quotation' => null, 'context' => null, 'blockers' => ['ambiguous_pricing_rule'], 'conversation_blockers' => ['ambiguous_pricing_rule']];

        /** @var PricingRule $rule */
        $rule = $ranked[0]['rule'];
        $missing = collect($rule->required_fields ?? [])->filter(fn ($field) => blank($this->fieldValue($lead, (string) $field)))->values()->all();
        $conversationBlockers = [];
        if (! $settings?->conversation_automation_enabled) $conversationBlockers[] = 'conversation_automation_disabled';
        if ($inbound?->sender_differs && $inbound?->match_reason !== 'known_contact') $conversationBlockers[] = 'sender_requires_verification';
        if ($lead->replies()->where('delivery_mode', 'automatic')->where('status', 'sent')->count() >= ($settings?->max_automatic_replies ?? 0)) $conversationBlockers[] = 'automatic_reply_limit_reached';
        $allowed = collect($settings?->automation_allowed_recipients ?? [])->map(fn ($email) => mb_strtolower(trim((string) $email)));
        if ($settings?->internal_test_only && ! $allowed->contains($lead->email_normalized)) $conversationBlockers[] = 'recipient_not_in_internal_allowlist';
        if (! $settings?->internal_test_only && ! config('commerciale-ai.automation.external_send_enabled')) $conversationBlockers[] = 'external_send_disabled_on_server';
        $blockers = $conversationBlockers;
        if ($missing !== []) $blockers[] = 'missing_required_fields';
        if (! $settings?->auto_send_quotes_enabled) $blockers[] = 'auto_send_quotes_disabled';
        if ($settings?->max_auto_quote_amount === null || (float) $rule->maximum_price > (float) $settings->max_auto_quote_amount) $blockers[] = 'amount_over_limit';

        $version = ((int) $lead->quotations()->max('version')) + 1;
        $confidence = $missing === [] ? 100 : max(50, 100 - count($missing) * 15);
        $quotation = Quotation::create([
            'organization_id' => $lead->organization_id, 'lead_id' => $lead->id, 'pricing_rule_id' => $rule->id,
            'version' => $version, 'minimum_price' => $rule->minimum_price, 'maximum_price' => $rule->maximum_price,
            'confidence' => $confidence, 'input_snapshot' => ['requested_service' => $lead->requested_service, 'request_data' => $lead->request_data, 'inbound_email_id' => $inbound?->id],
            'missing_fields' => $missing, 'auto_send_eligible' => $blockers === [], 'automation_blockers' => $blockers,
        ]);

        return ['quotation' => $quotation, 'context' => [
            'version' => $version, 'rule_name' => $rule->name, 'minimum_price' => (float) $rule->minimum_price,
            'maximum_price' => (float) $rule->maximum_price, 'currency' => 'EUR', 'includes' => $rule->includes,
            'excludes' => $rule->excludes, 'valid_until' => now()->addDays($rule->validity_days)->toDateString(),
            'missing_fields' => $missing, 'confidence' => $confidence,
        ], 'blockers' => $blockers, 'conversation_blockers' => $conversationBlockers];
    }

    private function fieldValue(Lead $lead, string $field): mixed
    {
        if (in_array($field, ['name', 'email', 'phone', 'company', 'requested_service'], true)) return $lead->{$field};
        $value = data_get($lead->request_data, $field);
        if (filled($value)) return $value;
        $inboundBody = (string) $lead->inboundEmails()->first()?->body;
        $normalizedField = $this->normalize($field);
        $inboundText = $this->normalize($inboundBody);
        if (in_array($normalizedField, ['pages', 'page count', 'numero pagine'], true)
            && preg_match('/\b(\d{1,3})\s*(?:pagine|pagina|pages?)\b/iu', $inboundBody, $matches)) return (int) $matches[1];
        if ($normalizedField === 'budget'
            && preg_match('/(?:budget|spesa)[^0-9]{0,15}([0-9][0-9.,]*)/iu', $inboundBody, $matches)) return $matches[1];

        return str_contains($inboundText, $normalizedField) ? $inboundText : null;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();
    }
}
