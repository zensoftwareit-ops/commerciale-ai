@extends('layouts.admin')
@section('title', 'Sicurezza Super Admin · Daria')
@section('content')
<div class="toolbar"><div><h1>Autenticazione a due fattori</h1><p class="muted">Protegge il pannello Super Admin con un codice TOTP generato sul tuo dispositivo.</p></div><span class="badge">{{ auth()->user()->two_factor_confirmed_at ? 'ATTIVA' : ($required ? 'OBBLIGATORIA' : 'DA ATTIVARE') }}</span></div>

@if(session('two_factor_recovery_codes'))
<section class="card"><h2>Codici di recupero</h2><div class="error">Copiali ora e conservali offline: non verranno mostrati nuovamente.</div><pre>{{ implode("\n", session('two_factor_recovery_codes')) }}</pre></section>
@endif

@if(!auth()->user()->two_factor_confirmed_at)
<section class="card" style="max-width:720px">
    <h2>1. Genera il segreto</h2>
    <form method="post" action="{{ route('admin.two-factor.setup') }}">@csrf<button>{{ $secret ? 'Rigenera segreto' : 'Inizia configurazione' }}</button></form>
    @if($secret)
        <h2 style="margin-top:24px">2. Aggiungilo all’app Authenticator</h2>
        <label>Chiave manuale</label><input readonly value="{{ $secret }}" onclick="this.select()">
        <p><a class="btn btn-secondary" href="{{ $provisioningUri }}">Apri nell’app Authenticator</a></p>
        <h2 style="margin-top:24px">3. Conferma il codice</h2>
        <form method="post" action="{{ route('admin.two-factor.confirm') }}">@csrf<label>Codice a 6 cifre</label><input name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="20"><br><button>Conferma e attiva</button></form>
    @endif
</section>
@else
<section class="card" style="max-width:720px"><h2>2FA attiva</h2><p class="notice">Il pannello amministrativo richiederà un codice a ogni nuovo accesso.</p><a class="btn" href="{{ route('admin.licensing') }}">Vai al pannello</a>
@unless($required)<details style="margin-top:24px"><summary class="muted">Disattiva 2FA</summary><form method="post" action="{{ route('admin.two-factor.disable') }}">@csrf @method('delete')<label>Password attuale</label><input type="password" name="current_password" required><label>Codice Authenticator</label><input name="code" required inputmode="numeric"><br><button class="btn-danger">Disattiva</button></form></details>@endunless
</section>
@endif
@endsection
