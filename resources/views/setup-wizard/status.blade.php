@extends('layouts.app')
@section('title', 'Preparazione workspace · Daria')
@section('content')
<a class="back-link" href="{{ route('onboarding') }}">← Avvio guidato</a>
<div class="toolbar"><div><div class="page-kicker">Configurazione protetta</div><h1>Daria sta preparando il workspace</h1><p class="muted">Il lavoro prosegue sul server: puoi chiudere questa pagina e tornare più tardi.</p></div></div>
<section class="card" style="max-width:780px">
    @if($run->status === 'failed')
        <span class="badge hot">Interrotta senza modifiche</span>
        <h2 style="margin-top:16px">La configurazione non è stata applicata</h2>
        <p>Le fonti inserite sono rimaste disponibili. Puoi riavviare l’elaborazione senza ricompilare tutto.</p>
        <div class="error">Non siamo riusciti a completare l’analisi. Riferimento assistenza: {{ $run->id }}</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <form method="post" action="{{ route('setup-wizard.retry', $run->id) }}">@csrf<button class="btn" type="submit">Riprova con le stesse fonti</button></form>
            <a class="btn btn-muted" href="{{ route('setup-wizard.create') }}">Modifica le fonti</a>
        </div>
    @else
        <span class="badge {{ $run->status === 'running' ? 'warm' : '' }}">{{ $run->status === 'running' ? 'Analisi in corso' : 'In coda' }}</span>
        <h2 style="margin-top:16px">Acquisizione e controllo delle informazioni</h2>
        <p>Daria sta leggendo il sito, separando fatti e ipotesi e preparando una configurazione da verificare. Nessuna automazione viene attivata in questa fase.</p>
        <div class="setup-progress"><span></span></div>
        <p class="muted">Riferimento {{ $run->id }}</p>
        @if($run->status === 'queued' && $run->started_at?->lt(now()->subMinutes(2)))<div class="warning">Il lavoro attende ancora il worker della coda <code>ai</code>. La configurazione non è persa.</div>@endif
        <script>setTimeout(function(){ location.reload(); }, 4000);</script>
    @endif
</section>
@push('styles')<style>.setup-progress{height:8px;border-radius:999px;background:#f2f4f7;overflow:hidden;margin:24px 0}.setup-progress span{display:block;width:42%;height:100%;border-radius:inherit;background:linear-gradient(90deg,#ff6b5e,#45d6b5);animation:setup-progress 1.5s ease-in-out infinite alternate}@keyframes setup-progress{from{transform:translateX(-55%)}to{transform:translateX(175%)}}</style>@endpush
@endsection
