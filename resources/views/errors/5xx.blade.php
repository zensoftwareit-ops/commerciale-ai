@extends('errors.layout')
@section('code', '5xx')
@section('title', 'Il servizio ha incontrato un problema')
@section('message', 'Non è stato possibile completare l’operazione. L’errore è stato registrato e puoi riprovare tra qualche istante.')
@section('actions')<button class="button secondary" type="button" onclick="location.reload()">Riprova</button>@endsection
