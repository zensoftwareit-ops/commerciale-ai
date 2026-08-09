@extends('layouts.app')
@section('title', 'Nuovo lead · Commerciale AI')
@section('content')
<div class="toolbar"><h1>Inserimento manuale</h1><a href="{{ route('leads.index') }}">Torna alla inbox</a></div>
<form class="card" method="post" action="{{ route('leads.store') }}">@csrf<div class="grid grid-2">
<div><label>Nome *</label><input name="name" value="{{ old('name') }}" required></div><div><label>Azienda</label><input name="company" value="{{ old('company') }}"></div>
<div><label>Email</label><input type="email" name="email" value="{{ old('email') }}"></div><div><label>Telefono</label><input name="phone" value="{{ old('phone') }}"></div>
</div><label>Servizio richiesto</label><input name="requested_service" value="{{ old('requested_service') }}"><label>Messaggio</label><textarea name="message" rows="6">{{ old('message') }}</textarea>
@foreach($errors->all() as $error)<div class="error">{{ $error }}</div>@endforeach<br><button class="btn">Crea lead</button></form>
@endsection
