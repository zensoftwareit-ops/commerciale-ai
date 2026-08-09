<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class OrganizationSetting extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id', 'legal_name', 'commercial_name', 'industry', 'business_description',
        'products_services', 'service_area', 'ideal_customer', 'pricing_rules', 'differentiators',
        'qualification_questions', 'exclusion_criteria', 'tone_of_voice', 'email_signature',
        'appointment_details', 'promised_response_minutes', 'authorized_sender', 'completeness',
    ];

    protected function casts(): array
    {
        return ['qualification_questions' => 'array', 'completeness' => 'integer'];
    }

    public static function completenessFor(array $data): int
    {
        $essential = ['commercial_name', 'industry', 'business_description', 'products_services', 'ideal_customer', 'tone_of_voice', 'email_signature'];
        $complete = count(array_filter($essential, fn (string $key): bool => filled($data[$key] ?? null)));

        return (int) round(($complete / count($essential)) * 100);
    }
}
