<?php

namespace App\Services\Quotations;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PricingFormulaValidator
{
    public function validate(?array $formula): ?array
    {
        if ($formula === null || $formula === []) return null;
        $validated = Validator::make($formula, [
            'version' => ['required', 'integer', 'in:1'],
            'variables' => ['required', 'array', 'max:50'],
            'variables.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,63}$/'],
            'variables.*.label' => ['required', 'string', 'max:255'],
            'variables.*.type' => ['required', 'in:number,text,boolean,distance_km'],
            'variables.*.aliases' => ['required', 'array', 'max:30'],
            'variables.*.aliases.*' => ['string', 'max:255'],
            'variables.*.required' => ['required', 'boolean'],
            'variables.*.unit' => ['nullable', 'string', 'max:30'],
            'variables.*.conversion_factor' => ['nullable', 'numeric', 'gt:0'],
            'variables.*.origin_address' => ['nullable', 'string', 'max:500'],
            'variables.*.round_trip' => ['nullable', 'boolean'],
            'components' => ['required', 'array', 'min:1', 'max:100'],
            'components.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,63}$/'],
            'components.*.label' => ['required', 'string', 'max:255'],
            'components.*.operation' => ['required', 'in:fixed,multiply,tiered,lookup,percentage'],
            'components.*.quantity_variable' => ['nullable', 'string', 'max:64'],
            'components.*.unit_price' => ['nullable', 'numeric'],
            'components.*.amount' => ['nullable', 'numeric'],
            'components.*.rate_percent' => ['nullable', 'numeric'],
            'components.*.base_components' => ['nullable', 'array', 'max:100'],
            'components.*.base_components.*' => ['string', 'max:64'],
            'components.*.tiers' => ['nullable', 'array', 'max:100'],
            'components.*.tiers.*.min' => ['required_with:components.*.tiers', 'numeric'],
            'components.*.tiers.*.max' => ['nullable', 'numeric'],
            'components.*.tiers.*.unit_price' => ['nullable', 'numeric'],
            'components.*.tiers.*.amount' => ['nullable', 'numeric'],
            'components.*.options' => ['nullable', 'array', 'max:100'],
            'components.*.options.*.value' => ['required_with:components.*.options', 'string', 'max:255'],
            'components.*.options.*.aliases' => ['nullable', 'array', 'max:30'],
            'components.*.options.*.aliases.*' => ['string', 'max:255'],
            'components.*.options.*.amount' => ['required_with:components.*.options', 'numeric'],
            'components.*.condition' => ['nullable', 'array'],
            'components.*.condition.variable' => ['required_with:components.*.condition', 'string', 'max:64'],
            'components.*.condition.operator' => ['required_with:components.*.condition', 'in:eq,neq,in,contains,gt,gte,lt,lte,truthy'],
            'components.*.condition.value' => ['nullable'],
        ])->validate();

        $variableKeys = collect($validated['variables'])->pluck('key');
        $componentKeys = collect($validated['components'])->pluck('key');
        if ($variableKeys->duplicates()->isNotEmpty() || $componentKeys->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['pricing_formula' => 'Le chiavi di variabili e componenti devono essere univoche.']);
        }
        foreach ($validated['variables'] as $variable) {
            if ($variable['type'] === 'distance_km' && blank($variable['origin_address'] ?? null)) {
                throw ValidationException::withMessages(['pricing_formula' => 'La variabile distanza '.$variable['label'].' richiede una località di partenza.']);
            }
        }
        $seenComponents = collect();
        foreach ($validated['components'] as $component) {
            if (in_array($component['operation'], ['multiply', 'tiered', 'lookup'], true)
                && ! $variableKeys->contains($component['quantity_variable'] ?? null)) {
                throw ValidationException::withMessages(['pricing_formula' => 'Il componente '.$component['label'].' usa una variabile inesistente.']);
            }
            if (($component['operation'] === 'fixed' && ! isset($component['amount']))
                || ($component['operation'] === 'multiply' && ! isset($component['unit_price']))
                || ($component['operation'] === 'tiered' && empty($component['tiers']))
                || ($component['operation'] === 'lookup' && empty($component['options']))
                || ($component['operation'] === 'percentage' && ! isset($component['rate_percent']))) {
                throw ValidationException::withMessages(['pricing_formula' => 'Il componente '.$component['label'].' non contiene i parametri richiesti per l’operazione '.$component['operation'].'.']);
            }
            foreach ($component['tiers'] ?? [] as $tier) {
                if (! isset($tier['unit_price']) && ! isset($tier['amount'])) {
                    throw ValidationException::withMessages(['pricing_formula' => 'Ogni scaglione di '.$component['label'].' deve avere un prezzo unitario o un importo fisso.']);
                }
                if (isset($tier['max']) && (float) $tier['max'] < (float) $tier['min']) {
                    throw ValidationException::withMessages(['pricing_formula' => 'Uno scaglione di '.$component['label'].' ha il limite massimo inferiore al minimo.']);
                }
            }
            if (($component['condition'] ?? null) && ! $variableKeys->contains($component['condition']['variable'])) {
                throw ValidationException::withMessages(['pricing_formula' => 'La condizione di '.$component['label'].' usa una variabile inesistente.']);
            }
            $unknownBases = collect($component['base_components'] ?? [])->diff($componentKeys);
            if ($unknownBases->isNotEmpty()) {
                throw ValidationException::withMessages(['pricing_formula' => 'Il componente '.$component['label'].' usa componenti base inesistenti.']);
            }
            if (collect($component['base_components'] ?? [])->diff($seenComponents)->isNotEmpty()) {
                throw ValidationException::withMessages(['pricing_formula' => 'Le percentuali di '.$component['label'].' devono riferirsi a componenti collocati prima nella ricetta.']);
            }
            $seenComponents->push($component['key']);
        }

        return $validated;
    }
}
