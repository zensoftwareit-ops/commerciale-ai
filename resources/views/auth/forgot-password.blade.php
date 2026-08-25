@extends('layouts.app')
@section('content')
<div class="auth card"><h1>Recupera password</h1><p>Riceverai un link all'indirizzo indicato. In locale l'email viene scritta nei log.</p>
<form method="post" action="{{ route('password.email') }}">@csrf<label>Email</label><input type="email" name="email" required>@error('email')<div class="error">{{ $message }}</div>@enderror<br><button class="btn">Invia link</button></form></div>
@endsection
