<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AnalysisOutputValidator
{
    public function validate(array $output): array
    {
        $validator = Validator::make($output, [
            'summary' => ['required', 'string', 'max:5000'],
            'intent' => ['required', 'string', 'max:255'],
            'requested_services' => ['present', 'array'],
            'requested_services.*' => ['string', 'max:255'],
            'budget' => ['required', 'array'],
            'budget.raw' => ['present', 'nullable', 'string', 'max:255'],
            'budget.min' => ['present', 'nullable', 'numeric', 'min:0'],
            'budget.max' => ['present', 'nullable', 'numeric', 'min:0'],
            'budget.currency' => ['required', 'in:EUR'],
            'urgency' => ['required', 'in:low,medium,high,unknown'],
            'fit_score' => ['required', 'integer', 'between:0,100'],
            'priority' => ['required', 'in:low,medium,high'],
            'missing_information' => ['present', 'array'],
            'risk_flags' => ['present', 'array'],
            'recommended_next_action' => ['required', 'string', 'max:3000'],
            'qualification_questions' => ['present', 'array'],
            'confidence' => ['required', 'numeric', 'between:0,1'],
            '_meta' => ['sometimes', 'array'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated() + ['_meta' => $output['_meta'] ?? []];
    }

    public function repair(array $output): array
    {
        foreach (['requested_services', 'missing_information', 'risk_flags', 'qualification_questions'] as $key) {
            $output[$key] = is_array($output[$key] ?? null) ? array_values($output[$key]) : [];
        }
        $output['budget'] = array_merge(['raw' => null, 'min' => null, 'max' => null, 'currency' => 'EUR'], is_array($output['budget'] ?? null) ? $output['budget'] : []);
        if (isset($output['fit_score'])) {
            $output['fit_score'] = max(0, min(100, (int) $output['fit_score']));
        }
        if (isset($output['confidence'])) {
            $output['confidence'] = max(0, min(1, (float) $output['confidence']));
        }

        return $output;
    }
}
