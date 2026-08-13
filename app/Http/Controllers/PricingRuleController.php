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
            'is_active' => ['nullable', 'boolean'],
        ]);
        $lines = fn (string $value) => collect(preg_split('/[\r\n,]+/', $value))->map(fn ($line) => trim($line))->filter()->values()->all();
        $data['keywords'] = $lines($data['keywords_text']);
        $data['required_fields'] = $lines($data['required_fields_text'] ?? '');
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        unset($data['keywords_text'], $data['required_fields_text']);
        return $data;
    }
}
