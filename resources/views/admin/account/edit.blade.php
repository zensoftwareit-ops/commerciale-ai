@extends('layouts.admin')
@section('title', 'Account amministratore · Daria')
@section('content')
<div class="toolbar">
    <div><h1>Account amministratore</h1><p class="muted">{{ auth()->user()->email }} · account di piattaforma non associato ad alcun cliente</p></div>
</div>
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
