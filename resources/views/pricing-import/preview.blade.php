@extends('layouts.app')
@section('title', 'Controlla i listini proposti · Daria')
@section('content')
<a class="back-link" href="{{ route('pricing-import.create') }}">← Nuova analisi</a>
<div class="toolbar"><div><div class="page-kicker">Anteprima · Nessuna modifica applicata</div><h1>Controlla listini e regole</h1><p class="muted">Modifica ogni voce, seleziona cosa salvare e conferma. I listini già presenti non verranno cambiati.</p></div></div>
@foreach($errors->all() as $error)<div class="error" role="alert">{{ $error }}</div>@endforeach
<div class="card"><h2>Fonti analizzate</h2><p>{{ implode(' · ', $run->input_context['files'] ?? []) }}</p>
    @foreach($draft['warnings'] as $warning)<p role="note">⚠ {{ $warning }}</p>@endforeach
    <p class="muted">Verifica soprattutto importi, IVA, periodicità e condizioni. Gli importi devono essere in EUR. La validità proposta di 15 giorni, se assente nelle fonti, va confermata.</p>
</div>
<form method="post" action="{{ route('pricing-import.apply', $run->id) }}">
    @csrf
    @forelse($draft['items'] as $index => $item)
    @php
        $prefix = 'items.'.$index.'.';
        $missingPrice = $item['minimum_price'] === null || $item['maximum_price'] === null;
        $tiersText = collect($item['daily_rate_tiers'] ?? [])->map(fn($tier) => $tier['min_days'].'-'.($tier['max_days'] ?? '*').': '.$tier['rate_per_day'])->implode("\n");
        $formulaText = ($item['pricing_formula'] ?? null) ? json_encode($item['pricing_formula'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '';
    @endphp
    <details class="card" @if($missingPrice || $errors->has('items.'.$index)) open @endif>
        <summary style="cursor:pointer"><strong>{{ $item['name'] }}</strong> — {{ $missingPrice ? 'Prezzo da completare' : 'Proposta da verificare' }}</summary>
        <p class="muted">{{ $item['evidence'] }}</p>
        <label><input style="width:auto" type="checkbox" name="items[{{ $index }}][selected]" value="1" @checked(old($prefix.'selected', session()->hasOldInput() ? false : !$missingPrice))> Importa questa voce</label>
        <div class="grid grid-2">
            <div><label for="name-{{ $index }}">Nome del servizio</label><input id="name-{{ $index }}" name="items[{{ $index }}][name]" maxlength="255" value="{{ old($prefix.'name', $item['name']) }}"></div>
            <div><label for="keywords-{{ $index }}">Parole chiave, separate da virgola</label><input id="keywords-{{ $index }}" name="items[{{ $index }}][keywords_text]" maxlength="2000" value="{{ old($prefix.'keywords_text', $item['keywords_text']) }}"></div>
            <div><label for="min-{{ $index }}">Prezzo minimo (€)</label><input id="min-{{ $index }}" type="number" min="0" max="99999999.99" step="0.01" name="items[{{ $index }}][minimum_price]" value="{{ old($prefix.'minimum_price', $item['minimum_price']) }}"></div>
            <div><label for="max-{{ $index }}">Prezzo massimo (€)</label><input id="max-{{ $index }}" type="number" min="0" max="99999999.99" step="0.01" name="items[{{ $index }}][maximum_price]" value="{{ old($prefix.'maximum_price', $item['maximum_price']) }}"></div>
            <div><label>Tariffe giornaliere per scaglione</label><textarea rows="5" name="items[{{ $index }}][daily_rate_tiers_text]" placeholder="1-3: 550&#10;4-8: 500&#10;9-*: 450">{{ old($prefix.'daily_rate_tiers_text', $tiersText) }}</textarea></div>
            <div><label>Località di partenza</label><input name="items[{{ $index }}][origin_address]" value="{{ old($prefix.'origin_address', $item['origin_address'] ?? '') }}"><label>Costo per km (€)</label><input type="number" min="0" step="0.01" name="items[{{ $index }}][distance_rate_per_km]" value="{{ old($prefix.'distance_rate_per_km', $item['distance_rate_per_km'] ?? '') }}"><label><input style="width:auto" type="checkbox" name="items[{{ $index }}][distance_round_trip]" value="1" @checked(old($prefix.'distance_round_trip', $item['distance_round_trip'] ?? true))> Calcola andata e ritorno</label></div>
            <div style="grid-column:1/-1"><label>Ricetta universale di calcolo</label><textarea rows="14" name="items[{{ $index }}][pricing_formula_text]" spellcheck="false">{{ old($prefix.'pricing_formula_text', $formulaText) }}</textarea><p class="muted">Contiene variabili, unità, scaglioni, opzioni, condizioni e percentuali che Daria eseguirà in modo deterministico. Se è vuota, resterà disponibile soltanto la fascia di prezzo descrittiva.</p></div>
            <div><label for="includes-{{ $index }}">Descrizione, inclusioni e condizioni</label><textarea id="includes-{{ $index }}" rows="5" maxlength="5000" name="items[{{ $index }}][includes]">{{ old($prefix.'includes', $item['includes']) }}</textarea></div>
            <div><label for="excludes-{{ $index }}">Esclusioni e limiti</label><textarea id="excludes-{{ $index }}" rows="5" maxlength="5000" name="items[{{ $index }}][excludes]">{{ old($prefix.'excludes', $item['excludes']) }}</textarea></div>
            <div><label for="validity-{{ $index }}">Validità del preventivo (giorni)</label><input id="validity-{{ $index }}" type="number" min="1" max="365" name="items[{{ $index }}][validity_days]" value="{{ old($prefix.'validity_days', $item['validity_days'] ?? 15) }}"></div>
            <div><label><input style="width:auto" type="checkbox" name="items[{{ $index }}][is_active]" value="1" @checked(old($prefix.'is_active', false))> Attiva subito questa regola</label><p class="muted">Se non selezionato, sarà salvata disattivata. Potrai attivarla dopo altre verifiche.</p></div>
        </div>
    </details>
    @empty
        <div class="card">Non sono state individuate voci di listino. Puoi salvare le eventuali regole commerciali qui sotto oppure riprovare con fonti più dettagliate.</div>
    @endforelse
    <details class="card" open><summary style="cursor:pointer"><strong>Regole commerciali e criteri di preventivazione</strong></summary>
        <label for="guidance">Indicazioni modificabili per Daria</label><textarea id="guidance" rows="10" name="guidance" maxlength="20000">{{ old('guidance', $draft['guidance']) }}</textarea>
        <label><input style="width:auto" type="checkbox" name="save_guidance" value="1" @checked(old('save_guidance', session()->hasOldInput() ? false : filled($draft['guidance'])))> Salva queste regole nella Knowledge base</label>
        <p class="muted">Saranno aggiunte come documento attivo e resteranno modificabili nella Knowledge base. Scaglioni giornalieri e costi chilometrici riconosciuti sopra vengono invece salvati come formule eseguibili dal motore di preventivazione. Le automazioni esistenti non vengono abilitate o cambiate.</p>
    </details>
    <button class="btn">Conferma e salva le voci selezionate</button>
</form>
@endsection
