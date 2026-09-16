<?php

namespace App\Http\Controllers;

use App\Models\PricingRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PricingRuleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        PricingRule::create($this->validated($request));
        return back()->with('status', 'Regola di prezzo aggiunta.');
    }

    public function update(Request $request, string $rule): RedirectResponse
    {
        PricingRule::query()->findOrFail($rule)->update($this->validated($request));
        return back()->with('status', 'Regola di prezzo aggiornata.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'keywords_text' => ['required', 'string', 'max:2000'],
            'required_fields_text' => ['nullable', 'string', 'max:2000'], 'minimum_price' => ['required', 'numeric', 'min:0'],
            'maximum_price' => ['required', 'numeric', 'gte:minimum_price'], 'includes' => ['nullable', 'string', 'max:5000'],
            'excludes' => ['nullable', 'string', 'max:5000'], 'validity_days' => ['required', 'integer', 'min:1', 'max:365'],
            'daily_rate_tiers_text' => ['nullable', 'string', 'max:5000'], 'origin_address' => ['nullable', 'string', 'max:500'],
            'distance_rate_per_km' => ['nullable', 'numeric', 'min:0', 'max:10000'], 'distance_round_trip' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $lines = fn (string $value) => collect(preg_split('/[\r\n,]+/', $value))->map(fn ($line) => trim($line))->filter()->values()->all();
        $data['keywords'] = $lines($data['keywords_text']);
        $data['required_fields'] = $lines($data['required_fields_text'] ?? '');
        $data['daily_rate_tiers'] = $this->tiers($data['daily_rate_tiers_text'] ?? '');
        $data['distance_round_trip'] = (bool) ($data['distance_round_trip'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        unset($data['keywords_text'], $data['required_fields_text'], $data['daily_rate_tiers_text']);
        return $data;
    }

    private function tiers(string $text): array
    {
        return collect(preg_split('/\R/u', $text))->map(function ($line, $index): ?array {
            if (trim((string) $line) === '') return null;
            if (! preg_match('/^\s*(\d+)\s*(?:-|a)\s*(\d+|\*)\s*[:=]\s*([0-9]+(?:[.,][0-9]+)?)\s*$/iu', (string) $line, $match)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'daily_rate_tiers_text' => 'Scaglione non valido alla riga '.($index + 1).'. Usa il formato 1-3: 550 oppure 9-*: 450.',
                ]);
            }
            return ['min_days' => (int) $match[1], 'max_days' => $match[2] === '*' ? null : (int) $match[2],
                'rate_per_day' => (float) str_replace(',', '.', $match[3])];
        })->filter()->values()->all();
    }
}
