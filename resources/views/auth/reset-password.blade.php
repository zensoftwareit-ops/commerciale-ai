@extends('layouts.app')
@section('content')
<div class="auth card"><h1>Nuova password</h1><form method="post" action="{{ route('password.update') }}">@csrf
<input type="hidden" name="token" value="{{ $token }}"><label>Email</label><input type="email" name="email" value="{{ $email }}" required>
<label>Password</label><input type="password" name="password" required><label>Conferma password</label><input type="password" name="password_confirmation" required>
@foreach($errors->all() as $error)<div class="error">{{ $error }}</div>@endforeach<br><button class="btn">Salva password</button></form></div>
@endsection
