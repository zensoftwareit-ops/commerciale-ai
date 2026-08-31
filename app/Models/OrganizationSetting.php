<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class OrganizationSetting extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id', 'legal_name', 'commercial_name', 'website_url', 'industry', 'business_description',
        'products_services', 'service_area', 'ideal_customer', 'pricing_rules', 'differentiators',
        'qualification_questions', 'exclusion_criteria', 'tone_of_voice', 'email_signature',
        'appointment_details', 'promised_response_minutes', 'completeness',
        'conversation_automation_enabled', 'auto_send_quotes_enabled', 'internal_test_only',
        'automation_allowed_recipients', 'max_automatic_replies', 'max_auto_quote_amount',
        'auto_analyze_new_leads', 'auto_send_initial_email', 'new_lead_automation_started_at',
    ];

    protected function casts(): array
    {
        return [
            'qualification_questions' => 'array', 'completeness' => 'integer',
            'conversation_automation_enabled' => 'boolean', 'auto_send_quotes_enabled' => 'boolean',
            'internal_test_only' => 'boolean', 'automation_allowed_recipients' => 'array',
            'max_automatic_replies' => 'integer', 'max_auto_quote_amount' => 'decimal:2',
            'auto_analyze_new_leads' => 'boolean', 'auto_send_initial_email' => 'boolean',
            'new_lead_automation_started_at' => 'datetime',
        ];
    }

    public static function completenessFor(array $data): int
    {
        $essential = ['commercial_name', 'industry', 'business_description', 'products_services', 'ideal_customer', 'tone_of_voice', 'email_signature'];
        $complete = count(array_filter($essential, fn (string $key): bool => filled($data[$key] ?? null)));

        return (int) round(($complete / count($essential)) * 100);
    }
}
