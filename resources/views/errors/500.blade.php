@extends('errors.layout')
@section('code', '500')
@section('title', 'Qualcosa non ha funzionato')
@section('message', 'Daria ha incontrato un errore imprevisto. L’evento è stato registrato: puoi riprovare oppure tornare alla dashboard.')
@section('actions')<button class="button secondary" type="button" onclick="location.reload()">Riprova</button>@endsection
