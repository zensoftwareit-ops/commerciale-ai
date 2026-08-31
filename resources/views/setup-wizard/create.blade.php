@extends('layouts.app')
@section('title', 'Setup automatico · Daria')
@section('content')
<a class="back-link" href="{{ route('onboarding') }}">← Torna all’avvio guidato</a>
<div class="toolbar">
    <div><div class="page-kicker">Configurazione assistita</div><h1>Racconta la tua attività a Daria</h1><p class="muted">Da una descrizione completa prepareremo profilo aziendale, criteri commerciali e knowledge base.</p></div>
    <span class="badge">{{ strtoupper($aiStatus['provider']) }}</span>
</div>

<form class="card" method="post" action="{{ route('setup-wizard.generate') }}" style="max-width:920px">
    @csrf
    <label>URL del sito aziendale</label>
    <input type="url" name="website_url" maxlength="2048" value="{{ $websiteUrl }}" placeholder="https://www.azienda.it">
    <p class="muted">Daria legge fino a quattro pagine pubbliche dello stesso sito, privilegiando servizi, chi siamo, FAQ e prezzi.</p>
    <h2>Descrizione dell’attività</h2>
    <p class="muted">Indica cosa vendi, a chi, dove lavori, punti di forza, processo commerciale, prezzi o fasce se esistono, domande frequenti e quando una richiesta deve passare a una persona.</p>
    <textarea name="description" rows="12" minlength="80" maxlength="10000" placeholder="Esempio: Siamo uno studio che realizza siti web per PMI italiane. Offriamo...">{{ $description }}</textarea>
    <p class="muted">Inserisci almeno il sito oppure una descrizione di 80 caratteri. Se li compili entrambi, la descrizione ha la precedenza in caso di informazioni discordanti.</p>
    <div class="notice">La descrizione e il testo pubblico estratto dal sito vengono inviati alle API OpenAI configurate sul server per generare la bozza. Non inserire password, chiavi API o dati personali non necessari.</div>
    <div class="warning"><strong>Importante:</strong> Daria non inventerà listini o condizioni mancanti. Evidenzierà nell’anteprima ciò che deve essere confermato.</div>
    @unless($aiStatus['configured'])<div class="error">OpenAI non è configurato sul server. Imposta OPENAI_API_KEY prima di usare il wizard.</div>@endunless
    @foreach($errors->all() as $error)<div class="error">{{ $error }}</div>@endforeach
    <button class="btn" type="submit" @disabled(!$aiStatus['configured']) onclick="this.disabled=true;this.textContent='Generazione in corso…';this.form.submit()">Genera configurazione</button>
</form>
@endsection
