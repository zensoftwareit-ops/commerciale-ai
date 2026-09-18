@extends('errors.layout')
@section('code', '503')
@section('title', 'Servizio temporaneamente non disponibile')
@section('message', 'Daria è in aggiornamento oppure uno dei servizi necessari non sta rispondendo. Attendi qualche minuto e riprova: i dati già salvati non vengono persi.')
@section('actions')<button class="button secondary" type="button" onclick="location.reload()">Riprova</button>@endsection
