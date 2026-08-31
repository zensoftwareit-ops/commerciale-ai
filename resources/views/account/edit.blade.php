@extends('layouts.app')
@section('title', 'Account · Daria')
@section('content')
<div class="toolbar"><div><h1>Account</h1><p class="muted">{{ auth()->user()->email }}</p></div><a href="{{ route('leads.index') }}">Torna alla inbox</a></div>
<section class="card" style="max-width:620px;margin-bottom:16px"><div class="toolbar"><div><h2>Sicurezza account</h2><p class="muted">Aggiungi un secondo fattore oltre alla password.</p></div><span class="badge {{ auth()->user()->two_factor_confirmed_at ? 'success' : 'warm' }}">{{ auth()->user()->two_factor_confirmed_at ? '2FA attiva' : '2FA non attiva' }}</span></div><a class="btn btn-muted" href="{{ route('account.two-factor.enroll') }}">Gestisci autenticazione a due fattori</a></section>
<form class="card" method="post" action="{{ route('account.password.update') }}" style="max-width:620px">@csrf @method('put')
<h2>Cambia password</h2><p class="muted">Prima di usare Daria con dati reali, sostituisci la password fornita dal seed.</p>
<label>Password attuale</label><input type="password" name="current_password" required autocomplete="current-password">
<label>Nuova password</label><input type="password" name="password" required minlength="12" autocomplete="new-password">
<label>Conferma nuova password</label><input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
@foreach($errors->all() as $error)<div class="error">{{ $error }}</div>@endforeach<br><button class="btn">Aggiorna password</button></form>
@endsection
