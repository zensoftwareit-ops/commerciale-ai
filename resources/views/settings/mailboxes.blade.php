@extends('layouts.app')
@section('title', 'Email Daria · Daria')
@section('content')
<div class="toolbar"><div><div class="page-kicker">Configurazione aziendale</div><h1>Email Daria</h1><p class="muted">Un’unica identità dedicata per inviare ai lead e acquisire le loro risposte.</p></div><span class="badge">Solo owner</span></div>

<section class="card" style="margin-bottom:16px">
    <div class="toolbar"><div><h2>Trasporto in uscita</h2><p class="muted">Il trasporto SMTP o Resend è gestito globalmente dalla piattaforma; qui configuri l’identità della tua organizzazione.</p></div><span class="badge {{ $mailTransport['deliverable'] ? 'success' : 'hot' }}">{{ strtoupper($mailTransport['mailer']) }}</span></div>
    <p><strong>Stato:</strong> {{ $mailTransport['message'] }}</p>
    @if($mailTransport['host'])<p class="muted">Server {{ $mailTransport['scheme'] ? $mailTransport['scheme'].'://' : '' }}{{ $mailTransport['host'] }}{{ $mailTransport['port'] ? ':'.$mailTransport['port'] : '' }}</p>@endif
</section>

<section class="card">
    <form method="post" action="{{ $mailbox ? route('settings.mailboxes.update',$mailbox) : route('settings.mailboxes.store') }}">
        @csrf
        @if($mailbox) @method('put') @endif
        @include('settings.partials.mailbox-fields',['mailbox'=>$mailbox,'creating'=>!$mailbox])
        @foreach($errors->all() as $error)<div class="error">{{ $error }}</div>@endforeach
        <button class="btn" style="margin-top:16px">{{ $mailbox ? 'Salva configurazione' : 'Configura Email Daria' }}</button>
    </form>
</section>

@if($mailbox)
<div class="grid grid-2" style="margin-top:16px">
    <section class="card">
        <div class="toolbar"><div><h2>Test ricezione IMAP</h2><p class="muted">Verifica credenziali, certificato e accesso alla cartella configurata.</p></div><span class="badge {{ $mailbox->last_error ? 'hot' : ($mailbox->last_tested_at ? 'success' : 'warm') }}">{{ $mailbox->last_error ? 'Errore' : ($mailbox->last_tested_at ? 'Verificata' : 'Da verificare') }}</span></div>
        @if($mailbox->last_error)<div class="error">{{ $mailbox->last_error }}</div>@endif
        <form method="post" action="{{ route('settings.mailboxes.test',$mailbox) }}">@csrf<button class="btn" type="submit">Verifica IMAP</button></form>
        <p class="muted">Ultimo test: {{ $mailbox->last_tested_at?->format('d/m/Y H:i') ?: 'mai' }} · Ultima sincronizzazione: {{ $mailbox->last_synced_at?->format('d/m/Y H:i') ?: 'mai' }}</p>
    </section>
    <section class="card">
        <div class="toolbar"><div><h2>Test invio</h2><p class="muted">Invia un messaggio usando mittente, Reply-To e trasporto effettivi.</p></div><span class="badge {{ $mailbox->last_outbound_error ? 'hot' : ($mailbox->last_outbound_tested_at ? 'success' : 'warm') }}">{{ $mailbox->last_outbound_error ? 'Errore' : ($mailbox->last_outbound_tested_at ? 'Verificato' : 'Da verificare') }}</span></div>
        @if($mailbox->last_outbound_error)<div class="error">{{ $mailbox->last_outbound_error }}</div>@endif
        <form method="post" action="{{ route('settings.mailboxes.test-outbound',$mailbox) }}">@csrf<label>Invia il test a</label><input type="email" name="test_recipient" required value="{{ old('test_recipient',auth()->user()->email) }}"><br><button class="btn" type="submit" @disabled(!$mailTransport['deliverable'])>Invia test</button></form>
        <p class="muted">Ultimo test: {{ $mailbox->last_outbound_tested_at?->format('d/m/Y H:i') ?: 'mai' }}</p>
    </section>
</div>
<form method="post" action="{{ route('settings.mailboxes.destroy',$mailbox) }}" onsubmit="return confirm('Rimuovere completamente la configurazione Email Daria?')" style="margin-top:16px">@csrf @method('delete')<button class="btn btn-danger" type="submit">Rimuovi configurazione</button></form>
@endif
@endsection
