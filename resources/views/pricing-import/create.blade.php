@extends('layouts.app')
@section('title', 'Listini da documenti · Daria')
@section('content')
<a class="back-link" href="{{ route('settings.organization') }}">← Azienda e AI</a>
<div class="toolbar"><div><div class="page-kicker">Configurazione assistita</div><h1>Listini e regole dai tuoi documenti</h1><p class="muted">Carica le fonti, spiega come usarle e controlla la proposta prima di salvarla.</p></div></div>
<div class="card" style="max-width:960px">
    <form method="post" action="{{ route('pricing-import.generate') }}" enctype="multipart/form-data" id="pricing-import-form">
        @csrf
        @foreach($errors->all() as $error)<div class="error" role="alert">{{ $error }}</div>@endforeach
        <h2>1. Documenti e immagini</h2>
        <label for="attachments">Seleziona uno o più allegati</label>
        <input id="attachments" type="file" name="attachments[]" multiple required accept=".pdf,.docx,.txt,.csv,.png,.jpg,.jpeg,.webp" aria-describedby="file-help">
        <p class="muted" id="file-help">PDF, Word DOCX, TXT, CSV, JPG, PNG e WEBP. Fino a 5 file, 10 MB ciascuno e 20 MB complessivi. Per tabelle, diagrammi o immagini contenuti in Word, esporta il documento in PDF: da Word viene letto solo il testo.</p>
        <h2>2. Spiega cosa contengono</h2>
        <label for="explanation">Contesto e indicazioni</label>
        <textarea id="explanation" name="explanation" rows="7" minlength="10" maxlength="10000" required placeholder="Esempio: il PDF contiene il listino aggiornato, gli screenshot spiegano gli extra. I prezzi sono in euro, IVA esclusa, per singolo progetto. Crea le fasce di prezzo e le regole per decidere quali extra applicare.">{{ old('explanation') }}</textarea>
        <p class="muted">Indica valuta, IVA, unità di misura, eventuali prezzi mensili e quale fonte preferire in caso di differenze.</p>
        <h2>3. Genera e controlla</h2>
        <p>Nessuna modifica viene applicata automaticamente. Potrai correggere i dati, scegliere le voci da importare e decidere quali attivare. Prezzi mancanti o ambigui resteranno da compilare.</p>
        <label style="display:flex;gap:10px;align-items:flex-start"><input style="width:auto" type="checkbox" name="consent" value="1" required>Autorizzo l’invio degli allegati e della spiegazione alle API OpenAI configurate per Daria. L’analisi consuma il budget AI del mio account.</label>
        <p class="muted">Non caricare password, segreti o dati personali non necessari. Gli originali vengono conservati in area privata solo per il tempo necessario all’analisi e poi eliminati; restano la bozza generata e i nomi delle fonti.</p>
        <button class="btn" id="generate-button">Analizza e proponi listini</button>
        <span id="generation-status" role="status"></span>
    </form>
</div>
<script>
document.getElementById('pricing-import-form').addEventListener('submit', function (event) {
    const files = [...document.getElementById('attachments').files];
    const status = document.getElementById('generation-status');
    if (files.length > 5 || files.some(file => file.size > 10 * 1024 * 1024) || files.reduce((total, file) => total + file.size, 0) > 20 * 1024 * 1024) {
        event.preventDefault(); status.textContent = 'Massimo 5 file, 10 MB ciascuno e 20 MB totali.'; return;
    }
    document.getElementById('generate-button').disabled = true;
    status.textContent = 'Caricamento in corso…';
});
</script>
@endsection
