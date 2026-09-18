@extends('errors.layout')
@section('code', '429')
@section('title', 'Troppe richieste ravvicinate')
@section('message', 'Daria ha temporaneamente rallentato le richieste per proteggere il servizio. Attendi qualche istante e riprova.')
@section('actions')<button class="button secondary" type="button" onclick="location.reload()">Riprova</button>@endsection
