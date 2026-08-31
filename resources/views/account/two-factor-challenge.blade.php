@extends('layouts.app')
@section('title', 'Verifica sicurezza · Daria')
@section('content')
<div class="auth card"><h1>Verifica in due passaggi</h1><p class="muted">Inserisci il codice Authenticator oppure un codice di recupero.</p><form method="post" action="{{ route('account.two-factor.verify') }}">@csrf<label>Codice</label><input name="code" required autofocus autocomplete="one-time-code">@error('code')<div class="error">{{ $message }}</div>@enderror<br><button class="btn">Verifica</button></form></div>
@endsection
