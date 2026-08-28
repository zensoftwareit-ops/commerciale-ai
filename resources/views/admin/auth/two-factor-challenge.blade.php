@extends('layouts.app')
@section('title', 'Verifica sicurezza · Daria')
@section('content')
<div class="auth card"><h1>Verifica in due passaggi</h1><p class="muted">Inserisci il codice dell’app Authenticator oppure uno dei codici di recupero.</p>
<form method="post" action="{{ route('admin.two-factor.verify') }}">@csrf
<label for="code">Codice</label><input id="code" name="code" required autofocus autocomplete="one-time-code">@error('code')<div class="error">{{ $message }}</div>@enderror
<button class="btn">Verifica</button>
</form></div>
@endsection
