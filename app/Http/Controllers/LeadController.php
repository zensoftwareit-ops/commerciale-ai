<?php

namespace App\Http\Controllers;

use App\Models\InboundSource;
use App\Models\KnowledgeDocument;
use App\Models\Lead;
use App\Models\OrganizationSetting;
use App\Models\PipelineStage;
use App\Services\Leads\CreateLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $leads = Lead::query()->with(['stage', 'assignee'])
            ->when($request->filled('status'), fn ($q) => $q->where('operational_status', $request->string('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('source_label', $request->string('source')))
            ->latest('last_activity_at')->paginate(25)->withQueryString();
        $settings = OrganizationSetting::query()->first();
        $pilotReadiness = [
            'company_profile' => ($settings?->completeness ?? 0) === 100,
            'knowledge_base' => KnowledgeDocument::query()->where('status', 'active')->exists(),
            'openai' => config('commerciale-ai.ai_provider') === 'openai' && filled(config('commerciale-ai.openai.api_key')),
            'inbound_source' => InboundSource::query()->where('is_active', true)->exists(),
        ];

        return view('leads.index', compact('leads', 'pilotReadiness'));
    }

    public function create(): View
    {
        return view('leads.create');
    }

    public function store(Request $request, CreateLead $creator): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'], 'company' => ['nullable', 'string', 'max:255'],
            'requested_service' => ['nullable', 'string', 'max:255'], 'message' => ['nullable', 'string', 'max:5000'],
        ]);
        $lead = $creator->handle([...$data, 'source_label' => 'manual', 'request_data' => ['message' => $data['message'] ?? null]], $request->user()->id);

        return redirect()->route('leads.show', $lead)->with('status', 'Lead creato.');
    }

    public function show(string $lead): View
    {
        $lead = Lead::query()->findOrFail($lead);
        $lead->load(['stage', 'assignee', 'activities.actor', 'analyses.run']);

        return view('leads.show', compact('lead'));
    }

    public function update(Request $request, string $lead): RedirectResponse
    {
        $lead = Lead::query()->findOrFail($lead);
        $data = $request->validate([
            'operational_status' => ['required', 'in:needs_action,awaiting_approval,awaiting_customer,follow_up_scheduled,paused,closed'],
            'pipeline_stage_id' => ['required', 'uuid'],
        ]);
        abort_unless(PipelineStage::query()->whereKey($data['pipeline_stage_id'])->exists(), 422);
        $lead->update($data);
        $lead->activities()->create(['organization_id' => $lead->organization_id, 'actor_id' => $request->user()->id, 'type' => 'lead_updated', 'title' => 'Stato del lead aggiornato', 'data' => $data, 'occurred_at' => now()]);

        return back()->with('status', 'Stato aggiornato.');
    }
}
