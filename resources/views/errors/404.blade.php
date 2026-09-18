@extends('errors.layout')
@section('code', '404')
@section('title', 'Pagina non trovata')
@section('message', 'L’indirizzo potrebbe essere errato oppure la risorsa è stata spostata o eliminata.')
@section('actions')<button class="button secondary" type="button" onclick="history.back()">Torna indietro</button>@endsection
