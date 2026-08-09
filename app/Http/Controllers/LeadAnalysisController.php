<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\AiAnalysis;
use App\Models\Lead;
use App\Services\Ai\AnalyzeLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class LeadAnalysisController extends Controller
{
    public function store(Request $request, string $lead, AnalyzeLead $analyzer): RedirectResponse
    {
        $lead = Lead::query()->findOrFail($lead);
        try {
            $analyzer->handle($lead, $request->user()->id);
        } catch (Throwable) {
            return back()->withErrors(['analysis' => 'Analisi non completata. Controlla la configurazione o riprova.']);
        }

        return back()->with('status', 'Analisi completata.');
    }

    public function update(Request $request, string $lead, string $analysis): RedirectResponse
    {
        $lead = Lead::query()->findOrFail($lead);
        $analysis = AiAnalysis::query()->where('lead_id', $lead->id)->findOrFail($analysis);
        $data = $request->validate([
            'summary' => ['required', 'string', 'max:5000'], 'intent' => ['required', 'string', 'max:255'],
            'urgency' => ['required', 'in:low,medium,high,unknown'], 'priority' => ['required', 'in:low,medium,high'],
            'final_score' => ['required', 'integer', 'between:0,100'], 'recommended_next_action' => ['required', 'string', 'max:3000'],
            'missing_information_text' => ['nullable', 'string', 'max:5000'], 'risk_flags_text' => ['nullable', 'string', 'max:5000'],
            'qualification_questions_text' => ['nullable', 'string', 'max:5000'],
        ]);
        foreach (['missing_information', 'risk_flags', 'qualification_questions'] as $key) {
            $data[$key] = collect(preg_split('/\r\n|\r|\n/', $data[$key.'_text'] ?? ''))->map(fn (string $line): string => trim($line))->filter()->values()->all();
            unset($data[$key.'_text']);
        }
        $analysis->update([...$data, 'corrected_by' => $request->user()->id, 'corrected_at' => now()]);
        $lead->update(['score' => $analysis->final_score, 'temperature' => $analysis->priority === 'high' ? 'hot' : ($analysis->priority === 'medium' ? 'warm' : 'cold'), 'last_activity_at' => now()]);
        Activity::create(['organization_id' => $lead->organization_id, 'lead_id' => $lead->id, 'actor_id' => $request->user()->id, 'type' => 'analysis_corrected', 'title' => 'Analisi corretta dall’operatore', 'data' => ['analysis_id' => $analysis->id], 'occurred_at' => now()]);

        return back()->with('status', 'Analisi corretta.');
    }
}
