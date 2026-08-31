@extends('layouts.app')
@section('title', 'Lead inbox · Daria')
@section('content')
@php
    $readyCount = collect($systemReadiness)->filter()->count();
    $readinessTotal = count($systemReadiness);
    $statusLabels = [
        'needs_action' => 'Da lavorare', 'awaiting_approval' => 'In approvazione',
        'awaiting_customer' => 'In attesa cliente', 'follow_up_scheduled' => 'Follow-up',
        'paused' => 'In pausa', 'closed' => 'Chiuso',
    ];
    $temperatureLabels = ['hot' => 'Caldo', 'warm' => 'Tiepido', 'cold' => 'Freddo'];
@endphp

<div class="toolbar">
    <div>
        <div class="page-kicker">Workspace commerciale</div>
        <h1>Lead inbox</h1>
        <div class="muted">Priorità, conversazioni e prossime azioni in un’unica vista.</div>
    </div>
    <a class="btn" href="{{ route('leads.create') }}"><span aria-hidden="true">＋</span> Nuovo lead</a>
</div>

<div class="metric-grid">
    <div class="metric"><div class="metric-label">Lead visualizzati</div><div class="metric-value">{{ $leads->total() }}</div><div class="metric-detail">Nel filtro corrente</div></div>
    <div class="metric"><div class="metric-label">Configurazione</div><div class="metric-value">{{ $readyCount }}/{{ $readinessTotal }}</div><div class="metric-detail">Requisiti operativi completati</div></div>
    <div class="metric"><div class="metric-label">Stato sistema</div><div class="metric-value" style="font-size:18px;color:{{ $readyCount === $readinessTotal ? '#067647' : '#b54708' }}">{{ $readyCount === $readinessTotal ? 'Operativo' : 'Da completare' }}</div><div class="metric-detail">Configurazione dell'organizzazione</div></div>
</div>

<section class="card setup-card" style="margin-bottom:16px">
    <div class="toolbar" style="margin:0">
        <div><strong>Configurazione operativa</strong><div class="muted">I servizi necessari al flusso automatico.</div></div>
        <span class="badge {{ $readyCount === $readinessTotal ? 'success' : 'warm' }}">{{ $readyCount === $readinessTotal ? 'Tutto pronto' : 'Configurazione richiesta' }}</span>
    </div>
    <div class="check-list">
        @foreach(['company_profile' => 'Profilo aziendale', 'knowledge_base' => 'Knowledge base', 'openai' => 'OpenAI', 'inbound_source' => 'Fonte webhook'] as $key => $label)
            <span class="badge {{ $systemReadiness[$key] ? 'success' : 'hot' }}">{{ $systemReadiness[$key] ? '✓' : '!' }} {{ $label }}</span>
        @endforeach
    </div>
</section>

<form class="card filter-bar" method="get" style="margin-bottom:16px">
    <div><label for="status">Stato operativo</label><select id="status" name="status"><option value="">Tutti gli stati</option>@foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="source">Origine del lead</label><input id="source" name="source" value="{{ request('source') }}" placeholder="es. sito-aziendale.it"></div>
    <button class="btn btn-muted" type="submit">Applica filtri</button>
</form>

<section class="card table-card">
    <div class="table-wrap">
        <table class="lead-table">
            <thead><tr><th>Contatto</th><th>Origine</th><th>Servizio</th><th>Score</th><th>Pipeline</th><th>Temperatura</th><th>Stato</th><th>Prossima azione</th><th>Ultima attività</th></tr></thead>
            <tbody>
            @forelse($leads as $lead)
                <tr>
                    <td><a href="{{ route('leads.show', $lead) }}"><strong>{{ $lead->name }}</strong></a><div class="muted">{{ $lead->company ?: $lead->email }}</div></td>
                    <td><span class="muted">{{ $lead->source_label }}</span></td>
                    <td>{{ $lead->requested_service ?: '—' }}</td>
                    <td><strong>{{ $lead->score }}</strong><span class="muted">/100</span></td>
                    <td><span class="badge">{{ $lead->stage->name }}</span></td>
                    <td><span class="badge {{ $lead->temperature }}">{{ $temperatureLabels[$lead->temperature] ?? ucfirst($lead->temperature) }}</span></td>
                    <td><span class="badge">{{ $statusLabels[$lead->operational_status] ?? str_replace('_', ' ', $lead->operational_status) }}</span></td>
                    <td>{{ $lead->next_action_at?->format('d/m/Y H:i') ?: '—' }}</td>
                    <td><span class="muted">{{ $lead->last_activity_at?->diffForHumans() ?: '—' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="9" style="padding:40px;text-align:center"><strong>Nessun lead trovato</strong><div class="muted">Modifica i filtri oppure inserisci un nuovo contatto.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $leads->links() }}</div>
</section>
@endsection
