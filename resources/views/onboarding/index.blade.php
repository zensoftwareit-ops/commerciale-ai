@extends('layouts.app')
@section('title', 'Configura il workspace · Daria')
@section('content')
<div class="toolbar">
    <div><div class="page-kicker">Avvio guidato</div><h1>Configura {{ $organization->name }}</h1><p class="muted">Completa i passaggi essenziali per rendere operativo il workspace.</p></div>
    <span class="badge @if($organization->status==='active') success @elseif($organization->status==='suspended') hot @endif">{{ $organization->status }}</span>
</div>

@if(session('warning'))<div class="warning">{{ session('warning') }}</div>@endif
@if($organization->status === 'suspended')
    <div class="error"><strong>Workspace sospeso.</strong> La licenza non è utilizzabile ({{ str_replace('_', ' ', $organization->suspension_reason ?: 'stato non disponibile') }}). Contatta l’amministratore Daria per riattivarla.</div>
@endif

<section class="card setup-card">
    <div style="display:flex;justify-content:space-between;gap:16px;align-items:center"><div><h2>Stato configurazione</h2><p class="muted">{{ $onboarding['progress'] }}% dei requisiti obbligatori completato.</p></div><div class="stat">{{ $onboarding['progress'] }}%</div></div>
    <div style="height:8px;background:#eaecf0;border-radius:999px;overflow:hidden"><div style="width:{{ $onboarding['progress'] }}%;height:100%;background:var(--brand);border-radius:999px"></div></div>
</section>

<div class="grid grid-2" style="margin-top:16px">
    <section class="card"><span class="badge @if($onboarding['license']) success @endif">{{ $onboarding['license'] ? 'Completato' : 'Da completare' }}</span><h2 style="margin-top:12px">1. Licenza</h2><p class="muted">È necessaria una licenza attiva o in prova. La licenza viene assegnata dall’amministratore Daria.</p></section>
    <section class="card"><span class="badge @if($onboarding['profile']) success @endif">{{ $onboarding['profile'] ? 'Completato' : 'Da completare' }}</span><h2 style="margin-top:12px">2. Profilo aziendale</h2><p class="muted">Daria usa queste informazioni per analizzare i lead e scrivere risposte coerenti.</p><a class="btn" href="{{ route('settings.organization') }}">Configura azienda e AI</a></section>
    <section class="card"><span class="badge @if($onboarding['source']) success @endif">{{ $onboarding['source'] ? 'Completato' : 'Da completare' }}</span><h2 style="margin-top:12px">3. Sorgente lead</h2><p class="muted">Crea l’endpoint che il sito userà per inviare i nuovi contatti.</p><a class="btn" href="{{ route('settings.sources') }}">Configura sorgente</a></section>
    <section class="card"><span class="badge @if($onboarding['mailbox']) success @endif">{{ $onboarding['mailbox'] ? 'Configurata' : 'Consigliata' }}</span><h2 style="margin-top:12px">4. Casella email</h2><p class="muted">Non blocca l’attivazione, ma serve per acquisire e automatizzare le risposte dei clienti.</p><a class="btn btn-muted" href="{{ route('settings.mailboxes.index') }}">Configura IMAP</a></section>
</div>

@if($onboarding['ready'] && $organization->status === 'active')
    <div class="notice" style="margin-top:16px"><strong>Workspace pronto.</strong> Puoi iniziare a gestire i lead con Daria. <a href="{{ route('leads.index') }}">Apri Lead inbox</a></div>
@endif
@endsection
