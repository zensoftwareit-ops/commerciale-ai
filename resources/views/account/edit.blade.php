@extends('layouts.app')
@section('title', 'Account · Daria')
@section('content')
<div class="toolbar"><div><h1>Account</h1><p class="muted">{{ auth()->user()->email }}</p></div><a href="{{ route('leads.index') }}">Torna alla inbox</a></div>
<form class="card" method="post" action="{{ route('account.mail-identity.update') }}" style="max-width:620px;margin-bottom:16px">@csrf @method('put')
<h2>Mittente email</h2><p class="muted">Questa identità viene usata come campo From nelle risposte inviate da te e, se sei owner, nelle email automatiche dell'azienda. SMTP o Resend forniscono soltanto il trasporto.</p>
<label>Indirizzo mittente</label><input type="email" name="mail_from_address" value="{{ old('mail_from_address',auth()->user()->mail_from_address ?: auth()->user()->email) }}" required>
<label>Nome mittente</label><input name="mail_from_name" value="{{ old('mail_from_name',auth()->user()->mail_from_name ?: auth()->user()->name) }}" required maxlength="255">
<br><button class="btn">Salva mittente</button></form>
<form class="card" method="post" action="{{ route('account.password.update') }}" style="max-width:620px">@csrf @method('put')
<h2>Cambia password</h2><p class="muted">Prima di usare Daria con dati reali, sostituisci la password fornita dal seed.</p>
<label>Password attuale</label><input type="password" name="current_password" required autocomplete="current-password">
<label>Nuova password</label><input type="password" name="password" required minlength="12" autocomplete="new-password">
<label>Conferma nuova password</label><input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
@foreach($errors->all() as $error)<div class="error">{{ $error }}</div>@endforeach<br><button class="btn">Aggiorna password</button></form>
@endsection
