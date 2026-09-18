@extends('errors.layout')
@section('code', '419')
@section('title', 'La sessione è scaduta')
@section('message', 'Per proteggere i tuoi dati la sessione è terminata. Ricarica la pagina e ripeti l’operazione; i dati non confermati potrebbero dover essere reinseriti.')
@section('actions')<button class="button secondary" type="button" onclick="location.reload()">Ricarica la pagina</button>@endsection
