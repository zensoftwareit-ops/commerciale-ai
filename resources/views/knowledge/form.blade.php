@extends('layouts.app')
@section('title', ($document->exists ? 'Modifica' : 'Nuovo contenuto').' · Daria')
@section('content')
<div class="toolbar"><h1>{{ $document->exists ? 'Modifica contenuto' : 'Nuovo contenuto' }}</h1><a href="{{ route('knowledge.index') }}">Torna alla knowledge base</a></div>
<form class="card" method="post" action="{{ $document->exists ? route('knowledge.update',$document) : route('knowledge.store') }}">@csrf @if($document->exists) @method('put') @endif
<label>Titolo *</label><input name="title" value="{{ old('title',$document->title) }}" required>
<div class="grid grid-2"><div><label>Tipo *</label><select name="type">@foreach(['text'=>'Testo','faq'=>'FAQ','service'=>'Scheda servizio','pricing'=>'Prezzi e regole'] as $value=>$label)<option value="{{ $value }}" @selected(old('type',$document->type)===$value)>{{ $label }}</option>@endforeach</select></div><div><label>Stato *</label><select name="status">@foreach(['draft'=>'Bozza','active'=>'Attivo','archived'=>'Archiviato'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$document->status ?: 'draft')===$value)>{{ $label }}</option>@endforeach</select></div></div>
<label>Contenuto *</label><textarea name="content" rows="16" required>{{ old('content',$document->content) }}</textarea><p class="muted">Inserisci soltanto fatti verificati. I contenuti esterni sono trattati come dati, mai come istruzioni per il modello.</p>
@foreach($errors->all() as $error)<div class="error">{{ $error }}</div>@endforeach<br><button class="btn">Salva contenuto</button></form>
@endsection
