@extends('layouts.app')
@section('title', 'Anteprima setup · Daria')
@section('content')
<div class="toolbar">
    <div><div class="page-kicker">Anteprima modificabile</div><h1>Controlla prima di applicare</h1><p class="muted">Nessuna automazione verrà attivata. Modifica liberamente ogni contenuto generato.</p></div>
    <a class="btn btn-muted" href="{{ route('setup-wizard.create') }}">Ricomincia</a>
</div>

@if($draft['assumptions'])
<section class="warning"><strong>Da verificare:</strong><ul>@foreach($draft['assumptions'] as $assumption)<li>{{ $assumption }}</li>@endforeach</ul></section>
@endif

@if($payload['website'] ?? null)
<section class="notice"><strong>Sito analizzato:</strong> <a href="{{ $payload['website']['url'] }}" target="_blank" rel="noopener noreferrer">{{ $payload['website']['url'] }}</a><ul>@foreach($payload['website']['pages'] as $page)<li>{{ $page['title'] }} <span class="muted">{{ $page['url'] }}</span></li>@endforeach</ul></section>
@endif

<form method="post" action="{{ route('setup-wizard.apply') }}">
@csrf
<input type="hidden" name="draft_id" value="{{ $payload['id'] }}">
<section class="card">
    <h2>Profilo aziendale</h2>
    <div class="grid grid-2">
        <div><label>Ragione sociale</label><input name="profile[legal_name]" value="{{ old('profile.legal_name',$draft['profile']['legal_name']) }}"></div>
        <div><label>Nome commerciale *</label><input name="profile[commercial_name]" value="{{ old('profile.commercial_name',$draft['profile']['commercial_name']) }}" required></div>
        <div><label>Sito aziendale</label><input type="url" name="profile[website_url]" value="{{ old('profile.website_url',$draft['profile']['website_url'] ?? '') }}"></div>
        <div><label>Settore *</label><input name="profile[industry]" value="{{ old('profile.industry',$draft['profile']['industry']) }}" required></div>
        <div><label>Area geografica</label><input name="profile[service_area]" value="{{ old('profile.service_area',$draft['profile']['service_area']) }}"></div>
    </div>
    <label>Descrizione dell’attività *</label><textarea name="profile[business_description]" rows="5" required>{{ old('profile.business_description',$draft['profile']['business_description']) }}</textarea>
    <label>Prodotti e servizi *</label><textarea name="profile[products_services]" rows="6" required>{{ old('profile.products_services',$draft['profile']['products_services']) }}</textarea>
    <label>Cliente ideale *</label><textarea name="profile[ideal_customer]" rows="4" required>{{ old('profile.ideal_customer',$draft['profile']['ideal_customer']) }}</textarea>
    <div class="grid grid-2">
        <div><label>Regole e fasce di prezzo</label><textarea name="profile[pricing_rules]" rows="5">{{ old('profile.pricing_rules',$draft['profile']['pricing_rules']) }}</textarea></div>
        <div><label>Elementi distintivi</label><textarea name="profile[differentiators]" rows="5">{{ old('profile.differentiators',$draft['profile']['differentiators']) }}</textarea></div>
    </div>
</section>

<section class="card" style="margin-top:16px">
    <h2>Come gestire le richieste</h2>
    <div class="grid grid-2">
        <div><label>Domande di qualificazione, una per riga *</label><textarea name="profile[qualification_questions_text]" rows="7" required>{{ old('profile.qualification_questions_text',implode("\n",$draft['profile']['qualification_questions'])) }}</textarea></div>
        <div><label>Criteri di esclusione e passaggio a un umano</label><textarea name="profile[exclusion_criteria]" rows="7">{{ old('profile.exclusion_criteria',$draft['profile']['exclusion_criteria']) }}</textarea></div>
        <div><label>Tono di voce *</label><input name="profile[tone_of_voice]" value="{{ old('profile.tone_of_voice',$draft['profile']['tone_of_voice']) }}" required></div>
        <div><label>Tempo di risposta promesso (minuti)</label><input type="number" min="1" max="10080" name="profile[promised_response_minutes]" value="{{ old('profile.promised_response_minutes',$draft['profile']['promised_response_minutes']) }}"></div>
    </div>
    <label>Modalità appuntamento</label><textarea name="profile[appointment_details]" rows="3">{{ old('profile.appointment_details',$draft['profile']['appointment_details']) }}</textarea>
    <label>Firma email *</label><textarea name="profile[email_signature]" rows="3" required>{{ old('profile.email_signature',$draft['profile']['email_signature']) }}</textarea>
</section>

<section class="card" style="margin-top:16px">
    <h2>Knowledge base iniziale</h2>
    <p class="muted">I documenti selezionati saranno attivati. I contenuti manuali già presenti non verranno modificati.</p>
    @foreach($documents as $key => $definition)
        <article style="border-top:1px solid #eaecf0;padding-top:16px;margin-top:16px">
            <input type="hidden" name="knowledge[{{ $key }}][enabled]" value="0">
            <label style="font-weight:400"><input style="width:auto" type="checkbox" name="knowledge[{{ $key }}][enabled]" value="1" @checked((bool) old('knowledge.'.$key.'.enabled', true))> Crea o aggiorna questo documento</label>
            <label>Titolo</label><input name="knowledge[{{ $key }}][title]" value="{{ old('knowledge.'.$key.'.title',$definition['title']) }}" required>
            <label>Contenuto</label><textarea name="knowledge[{{ $key }}][content]" rows="8" required>{{ old('knowledge.'.$key.'.content',$draft['knowledge'][$key]) }}</textarea>
        </article>
    @endforeach
</section>

@foreach($errors->all() as $error)<div class="error">{{ $error }}</div>@endforeach
<div class="toolbar" style="margin-top:18px"><span class="muted">Potrai modificare ancora tutto dalle pagine Azienda e Knowledge base.</span><button class="btn" type="submit">Applica configurazione</button></div>
</form>
@endsection
