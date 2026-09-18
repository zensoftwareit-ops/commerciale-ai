@extends('layouts.app')
@section('title', 'Analisi listini · Daria')
@section('content')
<a class="back-link" href="{{ route('pricing-import.create') }}">← Nuova analisi</a>
<div class="toolbar"><div><div class="page-kicker">Elaborazione asincrona</div><h1>Analisi dei documenti</h1><p class="muted">Puoi lasciare questa pagina: i documenti vengono elaborati in background.</p></div></div>
<div class="card" style="max-width:760px">
    @if($run->status === 'failed')
        <span class="badge hot">Non completata</span>
        <h2 style="margin-top:16px">L’analisi non è riuscita</h2>
        <p>Non è stata applicata alcuna modifica ai listini. Puoi riprovare con meno allegati o con indicazioni più precise.</p>
        <div class="error">Riferimento: {{ $run->id }}</div>
        <a class="btn" href="{{ route('pricing-import.create') }}">Riprova</a>
    @else
        <span class="badge {{ $run->status === 'running' ? 'warm' : '' }}">{{ $run->status === 'running' ? 'Analisi in corso' : 'In coda' }}</span>
        <h2 style="margin-top:16px">Daria sta leggendo le fonti</h2>
        <p>Sta identificando prezzi, unità, scaglioni, condizioni e formule di calcolo. Al termine verrai portato automaticamente all’anteprima.</p>
        <div style="height:8px;border-radius:999px;background:#f2f4f7;overflow:hidden;margin:22px 0"><div style="width:42%;height:100%;border-radius:inherit;background:linear-gradient(90deg,#ff6b5e,#45d6b5);animation:pricing-progress 1.5s ease-in-out infinite alternate"></div></div>
        <p class="muted">Avviata {{ $run->started_at?->diffForHumans() }} · riferimento {{ $run->id }}</p>
        @if($run->status === 'queued' && $run->started_at?->lt(now()->subMinutes(2)))<div class="warning">L’analisi è ancora in coda. Verifica che il worker <code>ai</code> sia attivo sul server.</div>@endif
        @push('styles')<style>@keyframes pricing-progress{from{transform:translateX(-55%)}to{transform:translateX(175%)}}</style>@endpush
        <script>setTimeout(function(){ location.reload(); }, 4000);</script>
    @endif
</div>
@endsection
