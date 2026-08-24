@extends('layouts.app')
@section('title', 'Accedi · Daria')
@section('content')
<div class="auth card"><h1>Bentornato</h1><p class="muted">Accedi a Daria e riprendi le conversazioni della tua azienda.</p>
<form method="post" action="/login">@csrf
<label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>@error('email')<div class="error">{{ $message }}</div>@enderror
<label for="password">Password</label><input id="password" type="password" name="password" required>
<label style="font-weight:400"><input style="width:auto" type="checkbox" name="remember" value="1"> Ricordami</label>
<button class="btn" type="submit">Entra</button> <a href="{{ route('password.request') }}">Password dimenticata?</a>
</form></div>
@endsection
