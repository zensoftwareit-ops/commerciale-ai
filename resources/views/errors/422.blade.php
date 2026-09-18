@extends('errors.layout')
@section('code', '422')
@section('title', 'Dati non validi')
@section('message', 'La richiesta contiene informazioni incomplete o non utilizzabili. Torna alla pagina precedente, controlla i campi evidenziati e riprova.')
@section('actions')<button class="button secondary" type="button" onclick="history.back()">Controlla i dati</button>@endsection
