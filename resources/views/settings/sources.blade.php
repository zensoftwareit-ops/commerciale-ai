@extends('layouts.app')
@section('title', 'Sorgenti lead · Daria')
@section('content')
<div class="toolbar"><div><h1>Sorgenti lead</h1><p class="muted">Crea un endpoint dedicato: il sito deve soltanto inviare il proprio payload, senza trasformazioni.</p></div><span class="badge">Solo owner</span></div>

@if($credentials=session('webhook_credentials'))
<div class="card" style="margin-bottom:1rem;border-color:#f79009">
    <strong>Credenziali da copiare ora</strong>
    <p class="muted">L’endpoint contiene un token segreto e viene mostrato una sola volta. Usalo soltanto nel backend del sito, mai nel JavaScript pubblico.</p>
    <label>Endpoint</label><input readonly value="{{ $credentials['endpoint'] }}" onclick="this.select()"><p>Invia un <code>POST</code> con qualsiasi oggetto JSON o form. Non servono firma, mapping o header personalizzati.</p>
</div>
@endif

<div class="grid grid-2">
    <section class="card">
        <h2>Come funziona</h2>
        <p>Ogni sorgente ha un URL segreto. Daria riconosce automaticamente contatti, servizio, messaggio, consensi e campi commerciali anche con nomi italiani o inglesi.</p>
        <p class="muted">I domini configurati vengono verificati rispetto a Origin, Referer o URL presenti nel payload. Nei POST server-to-server senza questi dati, l’autenticazione è garantita dal token segreto dell’endpoint.</p>
    </section>
    <form class="card" method="post" action="{{ route('settings.sources.store') }}">@csrf
        <h2>Nuova sorgente</h2>
        <label>Nome</label><input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="preventivositoweb.it">
        <label>Domini consentiti, uno per riga</label><textarea name="allowed_domains_text" rows="4" required placeholder="preventivositoweb.it&#10;www.preventivositoweb.it">{{ old('allowed_domains_text') }}</textarea>
        @foreach($errors->all() as $error)<div class="error">{{ $error }}</div>@endforeach
        <br><button class="btn">Crea endpoint</button>
    </form>
</div>

<div class="grid" style="margin-top:1rem">
@forelse($sources as $source)
    <section class="card">
        <div class="toolbar"><h2 style="margin:0">{{ $source->name }}</h2><div><span class="badge">{{ $source->is_active ? 'Attiva' : 'Disattiva' }}</span> <span class="badge {{ $source->endpoint_token_hash ? '' : 'warm' }}">{{ $source->endpoint_token_hash ? 'Endpoint configurato' : 'Endpoint da generare' }}</span></div></div>
        <div class="grid grid-2">
            <form method="post" action="{{ route('settings.sources.update',$source) }}">@csrf @method('put')
                <label>Domini consentiti</label><textarea name="allowed_domains_text" rows="4" required>{{ implode("\n",$source->allowed_domains ?? []) }}</textarea>
                <button class="btn btn-muted">Salva domini</button>
            </form>
            <div>
                <p class="muted">Per sicurezza il token non è recuperabile. Rigenerando l’endpoint, quello precedente smette immediatamente di funzionare.</p>
                <form method="post" action="{{ route('settings.sources.rotate-endpoint',$source) }}" onsubmit="return confirm('Generare un nuovo endpoint? Quello precedente smetterà subito di funzionare.')">@csrf @method('patch')<button class="btn">{{ $source->endpoint_token_hash ? 'Rigenera endpoint' : 'Genera endpoint' }}</button></form>
            </div>
        </div>
    </section>
@empty<div class="card">Nessuna sorgente configurata.</div>@endforelse
</div>
@endsection
