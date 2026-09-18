@extends('errors.layout')
@section('code', '4xx')
@section('title', 'La richiesta non può essere completata')
@section('message', 'La pagina o l’operazione richiesta non è disponibile con i dati o i permessi correnti.')
@section('actions')<button class="button secondary" type="button" onclick="history.back()">Torna indietro</button>@endsection
