@extends('layouts.app')
@section('title', 'Caselle email · Daria')
@section('content')
<div class="toolbar"><div><div class="page-kicker">Configurazione</div><h1>Caselle email</h1><p class="muted">Collega una o più caselle IMAP. Le password sono cifrate e non vengono mai mostrate.</p></div><span class="badge">Solo owner</span></div>
@error('imap')<div class="error">{{ $message }}</div>@enderror

<div class="grid">
@foreach($mailboxes as $mailbox)
<section class="card">
    <div class="toolbar"><div><h2>{{ $mailbox->name }}</h2><div class="muted">{{ $mailbox->username }} · {{ $mailbox->host }}:{{ $mailbox->port }}</div></div><span class="badge {{ $mailbox->last_error ? 'hot' : ($mailbox->last_tested_at ? 'success' : 'warm') }}">{{ $mailbox->last_error ? 'Errore' : ($mailbox->last_tested_at ? 'Verificata' : 'Da verificare') }}</span></div>
    @if($mailbox->last_error)<div class="error">{{ $mailbox->last_error }}</div>@endif
    <form method="post" action="{{ route('settings.mailboxes.update',$mailbox) }}">@csrf @method('put')
        @include('settings.partials.mailbox-fields',['mailbox'=>$mailbox,'creating'=>false])
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px"><button class="btn btn-muted">Salva modifiche</button></div>
    </form>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <form method="post" action="{{ route('settings.mailboxes.test',$mailbox) }}">@csrf<button class="btn" type="submit">Verifica connessione</button></form>
        <form method="post" action="{{ route('settings.mailboxes.destroy',$mailbox) }}" onsubmit="return confirm('Rimuovere questa casella IMAP?')">@csrf @method('delete')<button class="btn btn-danger" type="submit">Rimuovi</button></form>
    </div>
    <p class="muted">Ultimo test: {{ $mailbox->last_tested_at?->format('d/m/Y H:i') ?: 'mai' }} · Ultima sincronizzazione: {{ $mailbox->last_synced_at?->format('d/m/Y H:i') ?: 'mai' }}</p>
</section>
@endforeach
</div>

<form class="card" style="margin-top:16px" method="post" action="{{ route('settings.mailboxes.store') }}">@csrf
    <h2>Aggiungi casella IMAP</h2>
    <p class="muted">Per Gmail e Microsoft 365 può essere necessaria una password per applicazioni. OAuth sarà aggiunto nella fase SaaS.</p>
    @include('settings.partials.mailbox-fields',['mailbox'=>null,'creating'=>true])
    @foreach($errors->all() as $error)<div class="error">{{ $error }}</div>@endforeach
    <button class="btn" style="margin-top:16px">Salva casella</button>
</form>
@endsection
