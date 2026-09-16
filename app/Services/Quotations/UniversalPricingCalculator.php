<?php

namespace App\Services\Quotations;

use App\Models\Lead;
use App\Models\PricingRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class UniversalPricingCalculator
{
    public function __construct(private readonly RoadDistanceCalculator $distances, private readonly PricingFormulaValidator $validator) {}

    public function handle(Lead $lead, PricingRule $rule): array
    {
        $formula = $this->validator->validate($rule->pricing_formula);
        if (! $formula) return ['applicable' => false, 'total' => null, 'line_items' => [], 'assumptions' => [], 'missing' => [], 'calculation' => []];

        $variables = []; $missing = []; $assumptions = [];
        foreach ($formula['variables'] as $definition) {
            $value = $this->value($lead, $definition);
            if ($definition['type'] === 'distance_km' && filled($value)) {
                if (blank($definition['origin_address'] ?? null)) {
                    $missing[] = $definition['label'].' (località di partenza non configurata)';
                    $value = null;
                } else {
                    $oneWay = $this->distances->kilometers($definition['origin_address'], (string) $value);
                    $value = ($definition['round_trip'] ?? false) ? $oneWay * 2 : $oneWay;
                    $assumptions[] = $definition['label'].': '.number_format($value, 1, ',', '.').' km calcolati su strada'.(($definition['round_trip'] ?? false) ? ' A/R' : '').'.';
                }
            }
            if ($value !== null && isset($definition['conversion_factor']) && is_numeric($value)) {
                $value = (float) $value * (float) $definition['conversion_factor'];
            }
            if (($definition['required'] ?? false) && ($value === null || $value === '' || $value === [])) $missing[] = $definition['label'];
            $variables[$definition['key']] = $value;
        }

        $components = []; $lines = []; $subtotal = 0.0;
        foreach ($formula['components'] as $component) {
            if (! $this->conditionPasses($component['condition'] ?? null, $variables)) continue;
            $amount = $this->componentAmount($component, $variables, $components, $subtotal);
            if ($amount === null) {
                $missing[] = $component['label'];
                continue;
            }
            $amount = round($amount, 2); $components[$component['key']] = $amount; $subtotal += $amount;
            $lines[] = $this->line($component, $variables, $amount);
        }

        return ['applicable' => true, 'total' => $missing === [] ? round($subtotal, 2) : null,
            'line_items' => $lines, 'assumptions' => $assumptions, 'missing' => array_values(array_unique($missing)),
            'calculation' => ['engine' => 'universal-v1', 'variables' => $variables, 'components' => $components, 'total' => round($subtotal, 2)]];
    }

    private function value(Lead $lead, array $definition): mixed
    {
        $aliases = collect([$definition['key'], $definition['label'], ...($definition['aliases'] ?? [])])->map(fn ($value) => $this->normalize((string) $value));
        $fields = ['name' => $lead->name, 'email' => $lead->email, 'phone' => $lead->phone, 'company' => $lead->company, 'requested service' => $lead->requested_service];
        foreach ([...$fields, ...Arr::dot($lead->request_data ?? [])] as $path => $value) {
            if (! filled($value)) continue;
            $normalizedPath = $this->normalize((string) $path);
            if (! $aliases->contains(fn ($alias) => $alias !== '' && ($normalizedPath === $alias || str_contains($normalizedPath, $alias) || str_contains($alias, $normalizedPath)))) continue;
            return $this->cast($value, $definition['type']);
        }
        return null;
    }

    private function cast(mixed $value, string $type): mixed
    {
        if ($type === 'text' || $type === 'distance_km') return is_array($value) ? array_values($value) : trim((string) $value);
        if ($type === 'boolean') return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($type === 'number') {
            if (is_numeric($value)) return (float) $value;
            $clean = preg_replace('/[^0-9,.-]+/', '', (string) $value);
            if (substr_count($clean, ',') === 1 && substr_count($clean, '.') === 0) $clean = str_replace(',', '.', $clean);
            else $clean = str_replace(',', '', $clean);
            return is_numeric($clean) ? (float) $clean : null;
        }
        return $value;
    }

    private function componentAmount(array $component, array $variables, array $components, float $subtotal): ?float
    {
        return match ($component['operation']) {
            'fixed' => isset($component['amount']) ? (float) $component['amount'] : null,
            'multiply' => is_numeric($variables[$component['quantity_variable']] ?? null) && isset($component['unit_price'])
                ? (float) $variables[$component['quantity_variable']] * (float) $component['unit_price'] : null,
            'tiered' => $this->tiered($component, $variables),
            'lookup' => $this->lookup($component, $variables),
            'percentage' => ((float) ($component['rate_percent'] ?? 0) / 100) * (
                ($component['base_components'] ?? []) !== []
                    ? collect($component['base_components'])->sum(fn ($key) => $components[$key] ?? 0)
                    : $subtotal
            ),
            default => throw new RuntimeException('Operazione di prezzo non supportata.'),
        };
    }

    private function tiered(array $component, array $variables): ?float
    {
        $quantity = $variables[$component['quantity_variable']] ?? null;
        if (! is_numeric($quantity)) return null;
        $tier = collect($component['tiers'] ?? [])->first(fn ($item) => $quantity >= (float) $item['min']
            && (($item['max'] ?? null) === null || $quantity <= (float) $item['max']));
        if (! $tier) return null;
        return isset($tier['amount']) ? (float) $tier['amount'] : (float) $quantity * (float) $tier['unit_price'];
    }

    private function lookup(array $component, array $variables): ?float
    {
        $raw = $variables[$component['quantity_variable']] ?? null;
        if ($raw === null || $raw === '' || $raw === []) return 0.0;
        $selected = is_array($raw) ? $raw : preg_split('/\R|,|;/u', (string) $raw);
        $selected = collect($selected)->map(fn ($value) => $this->normalize((string) $value))->filter();
        return collect($component['options'] ?? [])->sum(function ($option) use ($selected): float {
            $aliases = collect([$option['value'], ...($option['aliases'] ?? [])])->map(fn ($value) => $this->normalize((string) $value));
            return $selected->contains(fn ($choice) => $aliases->contains(fn ($alias) => $choice === $alias || str_contains($choice, $alias))) ? (float) $option['amount'] : 0.0;
        });
    }

    private function conditionPasses(?array $condition, array $variables): bool
    {
        if (! $condition) return true;
        $actual = $variables[$condition['variable']] ?? null; $expected = $condition['value'] ?? null;
        return match ($condition['operator']) {
            'eq' => $actual == $expected, 'neq' => $actual != $expected,
            'in' => in_array($actual, (array) $expected, true),
            'contains' => str_contains($this->normalize(is_array($actual) ? implode(' ', $actual) : (string) $actual), $this->normalize((string) $expected)),
            'gt' => $actual > $expected, 'gte' => $actual >= $expected, 'lt' => $actual < $expected, 'lte' => $actual <= $expected,
            'truthy' => (bool) $actual, default => false,
        };
    }

    private function line(array $component, array $variables, float $amount): string
    {
        $suffix = '';
        $quantity = $variables[$component['quantity_variable'] ?? ''] ?? null;
        if ($component['operation'] === 'multiply' && is_numeric($quantity)) {
            $suffix = ': '.number_format((float) $quantity, 2, ',', '.').' × € '.number_format((float) $component['unit_price'], 2, ',', '.');
        } elseif ($component['operation'] === 'tiered' && is_numeric($quantity)) {
            $tier = collect($component['tiers'] ?? [])->first(fn ($item) => $quantity >= (float) $item['min']
                && (($item['max'] ?? null) === null || $quantity <= (float) $item['max']));
            $suffix = isset($tier['amount'])
                ? ': fascia '.number_format((float) $quantity, 2, ',', '.')
                : ': '.number_format((float) $quantity, 2, ',', '.').' × € '.number_format((float) ($tier['unit_price'] ?? 0), 2, ',', '.');
        } elseif ($component['operation'] === 'percentage') {
            $suffix = ': '.number_format((float) ($component['rate_percent'] ?? 0), 2, ',', '.').'%';
        }
        return $component['label'].$suffix.' = € '.number_format($amount, 2, ',', '.');
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();
    }
}
