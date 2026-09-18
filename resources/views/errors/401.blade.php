@extends('errors.layout')
@section('code', '401')
@section('title', 'Accesso richiesto')
@section('message', 'La sessione non è disponibile oppure non hai ancora effettuato l’accesso. Accedi nuovamente per continuare.')
@section('actions')<a class="button secondary" href="/login">Vai al login</a>@endsection
