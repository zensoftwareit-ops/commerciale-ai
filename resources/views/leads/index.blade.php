@extends('layouts.app')
@section('title', 'Lead inbox · Commerciale AI')
@section('content')
<div class="toolbar">
    <div><h1>Lead inbox</h1><div class="muted">{{ $leads->total() }} contatti nel periodo</div></div>
    <a class="btn" href="{{ route('leads.create') }}">+ Nuovo lead</a>
</div>
@php($readyCount = collect($pilotReadiness)->filter()->count())
<div class="card" style="margin-bottom:1rem">
    <div class="toolbar" style="margin:0">
        <div><strong>Prontezza pilot: {{ $readyCount }}/{{ count($pilotReadiness) }}</strong><div class="muted">Completa tutti i requisiti prima di collegare il form reale.</div></div>
        <span class="badge {{ $readyCount === count($pilotReadiness) ? '' : 'warm' }}">{{ $readyCount === count($pilotReadiness) ? 'Pronto' : 'Configurazione richiesta' }}</span>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.8rem">
        @foreach(['company_profile' => 'Profilo aziendale', 'knowledge_base' => 'Knowledge base', 'openai' => 'OpenAI', 'inbound_source' => 'Fonte webhook'] as $key => $label)
            <span class="badge {{ $pilotReadiness[$key] ? '' : 'hot' }}">{{ $pilotReadiness[$key] ? '✓' : '!' }} {{ $label }}</span>
        @endforeach
    </div>
</div>
<form class="card toolbar" method="get">
    <div><label for="status">Stato operativo</label><select id="status" name="status"><option value="">Tutti</option>@foreach(['needs_action' => 'Da lavorare', 'awaiting_approval' => 'In approvazione', 'awaiting_customer' => 'In attesa cliente', 'follow_up_scheduled' => 'Follow-up', 'closed' => 'Chiusi'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="source">Origine</label><input id="source" name="source" value="{{ request('source') }}" placeholder="es. preventivositoweb.it"></div>
    <button class="btn btn-muted" type="submit">Filtra</button>
</form>
<div class="card" style="margin-top:1rem">
    <table>
        <thead><tr><th>Contatto</th><th>Origine</th><th>Servizio</th><th>Score</th><th>Pipeline</th><th>Temperatura</th><th>Stato</th><th>Prossima azione</th><th>Ultima attività</th></tr></thead>
        <tbody>
        @forelse($leads as $lead)
            <tr>
                <td><a href="{{ route('leads.show', $lead) }}"><strong>{{ $lead->name }}</strong></a><div class="muted">{{ $lead->company ?: $lead->email }}</div></td>
                <td>{{ $lead->source_label }}</td><td>{{ $lead->requested_service ?: '—' }}</td><td>{{ $lead->score }}</td>
                <td><span class="badge">{{ $lead->stage->name }}</span></td><td><span class="badge {{ $lead->temperature }}">{{ $lead->temperature }}</span></td>
                <td>{{ str_replace('_', ' ', $lead->operational_status) }}</td>
                <td>{{ $lead->next_action_at?->format('d/m/Y H:i') ?: '—' }}</td><td>{{ $lead->last_activity_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="9">Nessun lead trovato.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $leads->links() }}</div>
</div>
@endsection
