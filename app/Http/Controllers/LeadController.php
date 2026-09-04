<?php

namespace App\Http\Controllers;

use App\Models\InboundSource;
use App\Models\KnowledgeDocument;
use App\Models\Lead;
use App\Models\OrganizationSetting;
use App\Models\PipelineStage;
use App\Services\Ai\GenerateLeadReply;
use App\Services\Leads\CreateLead;
use App\Services\Leads\DeleteLead;
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
        $systemReadiness = [
            'company_profile' => ($settings?->completeness ?? 0) === 100,
            'knowledge_base' => KnowledgeDocument::query()->where('status', 'active')->exists(),
            'openai' => config('commerciale-ai.ai_provider') === 'openai' && filled(config('commerciale-ai.openai.api_key')),
            'inbound_source' => InboundSource::query()->where('is_active', true)->exists(),
        ];

        return view('leads.index', compact('leads', 'systemReadiness'));
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
        $lead->load(['stage', 'assignee', 'activities.actor', 'analyses.run', 'replies.approver', 'inboundEmails', 'whatsappMessages', 'quotations.rule', 'quotations.reply']);

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

    public function retryConversation(Request $request, string $lead, GenerateLeadReply $replyGenerator): RedirectResponse
    {
        $lead = Lead::query()->findOrFail($lead);
        $inbound = $lead->inboundEmails()->where('status', 'linked')->latest('received_at')->first();
        $analysis = $lead->analyses()->first();

        if (! $inbound || ! $analysis) {
            return back()->withErrors(['reply' => 'Non ci sono una risposta ricevuta e un’analisi utilizzabili per riprendere la conversazione.']);
        }
        $existingDraft = $lead->replies()->where('status', 'draft')->where('parent_message_id', $inbound->message_id)->first();
        if ($existingDraft) {
            $existingDraft->delete();
        }

        try {
            $reply = $replyGenerator->handle($lead, $analysis, $request->user()->id, [
                'incoming_email' => [
                    'message_id' => $inbound->message_id,
                    'from' => $inbound->from_address,
                    'subject' => $inbound->subject,
                    'body' => mb_substr((string) $inbound->body, 0, 12000),
                    'received_at' => $inbound->received_at->toIso8601String(),
                ],
            ]);
        } catch (\App\Exceptions\ConversationHandoffRequired $exception) {
            return back()->withErrors(['reply' => 'Daria conferma che è necessario l’intervento umano per questa risposta.']);
        }

        return back()->with('status', $reply->automation_eligible
            ? 'Conversazione ripresa. La nuova bozza verrà inviata dalla cron di automazione.'
            : 'Conversazione ripresa e nuova bozza preparata; controllala prima dell’invio.');
    }

    public function destroy(Request $request, string $lead, DeleteLead $deleter): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', 'in:ELIMINA'],
        ], [
            'confirmation.in' => 'Per confermare la cancellazione definitiva devi scrivere ELIMINA.',
        ]);
        $lead = Lead::query()->findOrFail($lead);
        $name = $lead->name;
        $deleter->handle($lead);

        return redirect()->route('leads.index')->with('status', 'Lead “'.$name.'” eliminato definitivamente.');
    }
}
