@extends('layouts.app')
@section('title', 'Sicurezza account · Daria')
@section('content')
<div class="toolbar"><div><h1>Autenticazione a due fattori</h1><p class="muted">Protegge il tuo account con un codice generato sul telefono.</p></div><span class="badge {{ auth()->user()->two_factor_confirmed_at ? 'success' : 'warm' }}">{{ auth()->user()->two_factor_confirmed_at ? 'ATTIVA' : 'FACOLTATIVA' }}</span></div>
@if(session('two_factor_recovery_codes'))<section class="card"><h2>Codici di recupero</h2><div class="error">Copiali ora e conservali offline: non verranno mostrati nuovamente.</div><pre>{{ implode("\n", session('two_factor_recovery_codes')) }}</pre></section>@endif
@if(!auth()->user()->two_factor_confirmed_at)
<section class="card" style="max-width:720px"><h2>1. Genera il segreto</h2><form method="post" action="{{ route('account.two-factor.setup') }}">@csrf<button>{{ $secret ? 'Rigenera segreto' : 'Inizia configurazione' }}</button></form>
@if($secret)<h2 style="margin-top:24px">2. Configura Authenticator</h2><label>Chiave manuale</label><input readonly value="{{ $secret }}" onclick="this.select()"><p><a class="btn btn-secondary" href="{{ $provisioningUri }}">Apri nell’app Authenticator</a></p><h2>3. Conferma</h2><form method="post" action="{{ route('account.two-factor.confirm') }}">@csrf<label>Codice a 6 cifre</label><input name="code" required inputmode="numeric" autocomplete="one-time-code"><br><button>Conferma e attiva</button></form>@endif</section>
@else
<section class="card" style="max-width:720px"><h2>2FA attiva</h2><p class="notice">A ogni nuovo accesso verrà richiesto il codice.</p><details><summary class="muted">Disattiva 2FA</summary><form method="post" action="{{ route('account.two-factor.disable') }}">@csrf @method('delete')<label>Password attuale</label><input type="password" name="current_password" required><label>Codice Authenticator</label><input name="code" required inputmode="numeric"><br><button class="btn-danger">Disattiva</button></form></details></section>
@endif
@endsection
