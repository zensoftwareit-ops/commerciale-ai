@extends('errors.layout')
@section('code', '403')
@section('title', 'Non hai accesso a questa pagina')
@section('message', 'Il tuo account non dispone dei permessi necessari oppure questa funzione non è inclusa nel ruolo o nella licenza attiva.')
@section('actions')<button class="button secondary" type="button" onclick="history.back()">Torna indietro</button>@endsection
