@extends('layouts.app')
@section('title', 'Consumi AI · Daria')
@section('content')
<div class="toolbar">
    <div><div class="page-kicker">Controllo costi</div><h1>Consumi AI</h1><p class="muted">Periodo {{ \Illuminate\Support\Carbon::parse($summary['period_start'])->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($summary['period_end'])->format('d/m/Y') }}</p></div>
    @if($summary['limit'] === null)<span class="badge">Nessun limite configurato</span>@else<span class="badge @if($summary['percentage'] >= 100) hot @elseif($summary['percentage'] >= 80) warm @endif">{{ $summary['percentage'] }}% utilizzato</span>@endif
</div>

<div class="metric-grid">
    <div class="metric"><div class="metric-label">Token utilizzati</div><div class="metric-value">{{ number_format($summary['total_tokens'], 0, ',', '.') }}</div><div class="metric-detail">{{ number_format($summary['input_tokens'], 0, ',', '.') }} input · {{ number_format($summary['output_tokens'], 0, ',', '.') }} output</div></div>
    <div class="metric">
        <div class="metric-label">Budget del pacchetto</div>
        <div class="metric-value">{{ $summary['limit'] === null ? 'Illimitato' : number_format($summary['limit'], 0, ',', '.') }}</div>
        <div class="metric-detail">
            @if($summary['remaining'] !== null)
                {{ number_format($summary['remaining'], 0, ',', '.') }} token disponibili
            @endif
        </div>
    </div>
    <div class="metric"><div class="metric-label">Costo API stimato</div><div class="metric-value">€ {{ number_format($summary['estimated_cost'], 4, ',', '.') }}</div><div class="metric-detail">Stima basata sui prezzi configurati per i modelli</div></div>
</div>

@if($summary['limit'] !== null)
<section class="card" style="margin-bottom:16px"><div style="height:10px;background:#eaecf0;border-radius:999px;overflow:hidden"><div style="height:100%;width:{{ $summary['percentage'] }}%;background:{{ $summary['percentage'] >= 100 ? '#d92d20' : ($summary['percentage'] >= 80 ? '#f79009' : 'var(--brand)') }};border-radius:999px"></div></div><p class="muted" style="margin-bottom:0">Daria invia una notifica all’80%. Raggiunto il 100%, non avvia nuove richieste AI fino al mese successivo.</p></section>
@endif

<div class="grid grid-2">
    <section class="card"><h2>Consumo per attività</h2><div class="table-wrap"><table><thead><tr><th>Attività</th><th>Richieste</th><th>Token</th><th>Costo</th></tr></thead><tbody>@forelse($operations as $operation)<tr><td>{{ $operation->operation === 'lead_analysis' ? 'Analisi lead' : ($operation->operation === 'reply_draft' ? 'Bozze email' : $operation->operation) }}</td><td>{{ $operation->requests }}</td><td>{{ number_format($operation->input_tokens + $operation->output_tokens, 0, ',', '.') }}</td><td>€ {{ number_format($operation->estimated_cost, 4, ',', '.') }}</td></tr>@empty<tr><td colspan="4">Nessun utilizzo nel mese corrente.</td></tr>@endforelse</tbody></table></div></section>
    <section class="card"><h2>Ultime richieste</h2><div class="table-wrap"><table><thead><tr><th>Data</th><th>Operazione</th><th>Token</th><th>Costo</th></tr></thead><tbody>@forelse($recent as $record)<tr><td>{{ $record->occurred_at->format('d/m H:i') }}</td><td>{{ $record->operation }}</td><td>{{ number_format($record->input_units + $record->output_units, 0, ',', '.') }}</td><td>€ {{ number_format($record->estimated_cost, 4, ',', '.') }}</td></tr>@empty<tr><td colspan="4">Nessuna richiesta registrata.</td></tr>@endforelse</tbody></table></div></section>
</div>
@endsection
