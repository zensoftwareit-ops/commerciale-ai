@extends('layouts.admin')
@section('title', 'Account amministratore · Daria')
@section('content')
<div class="toolbar">
    <div><h1>Account amministratore</h1><p class="muted">{{ auth()->user()->email }} · account di piattaforma non associato ad alcun cliente</p></div>
</div>
<form class="card" method="post" action="{{ route('admin.account.system-mail-identity.update') }}" style="max-width:620px">
    @csrf @method('put')
    <h2>Email di sistema Daria</h2>
    <p class="muted">Identità transazionale dedicata a inviti, attivazioni e recupero password. Non è collegata all'account amministratore. Il trasporto SMTP o Resend rimane globale.</p>
    <label>Indirizzo mittente di sistema</label><input type="email" name="system_mail_from_address" value="{{ old('system_mail_from_address',$platformSettings->system_mail_from_address) }}" placeholder="assistenza@daria-ai.it" required>
    <label>Nome mittente di sistema</label><input name="system_mail_from_name" value="{{ old('system_mail_from_name',$platformSettings->system_mail_from_name) }}" placeholder="Daria" required maxlength="255">
    <br><button class="btn">Salva mittente</button>
</form>
<section class="card" style="max-width:620px">
    <div class="toolbar">
        <div>
            <h2>Trasporto email effettivo</h2>
            <p class="muted">Valori realmente caricati da Laravel, inclusa l'eventuale cache di configurazione.</p>
        </div>
        <span class="badge">{{ $mailTransport['mailer'] }}</span>
    </div>
    @if($mailTransport['deliverable'])
        <div class="notice">{{ $mailTransport['message'] }}</div>
    @else
        <div class="error">{{ $mailTransport['message'] }}</div>
    @endif
    <p><strong>Driver:</strong> {{ $mailTransport['transport'] }}</p>
    @if($mailTransport['host'])<p><strong>Server:</strong> {{ $mailTransport['scheme'] ? $mailTransport['scheme'].'://' : '' }}{{ $mailTransport['host'] }}{{ $mailTransport['port'] ? ':'.$mailTransport['port'] : '' }}</p>@endif
    @if($mailTransport['username'])<p><strong>Username SMTP:</strong> {{ $mailTransport['username'] }}</p>@endif
    @if($mailTransport['uses_url'])<p class="muted">È attivo anche MAIL_URL: può sostituire host e credenziali SMTP mostrati sopra.</p>@endif
    <form method="post" action="{{ route('admin.account.system-mail-test') }}">
        @csrf
        <button @disabled(!$mailTransport['deliverable'])>Invia test al mio indirizzo admin</button>
    </form>
</section>
<form class="card" method="post" action="{{ route('admin.account.password.update') }}" style="max-width:620px">
    @csrf @method('put')
    <h2>Cambia password</h2>
    <p class="muted">Questo account consente esclusivamente la gestione della piattaforma, dei clienti e delle licenze.</p>
    <label>Password attuale</label><input type="password" name="current_password" required autocomplete="current-password">
    <label>Nuova password</label><input type="password" name="password" required minlength="12" autocomplete="new-password">
    <label>Conferma nuova password</label><input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
    <br><button class="btn">Aggiorna password</button>
</form>
@endsection
