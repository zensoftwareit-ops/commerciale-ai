@extends('layouts.app')
@section('title', 'Centro configurazione · Daria')
@section('content')
<div class="toolbar">
    <div><div class="page-kicker">Centro configurazione</div><h1>Porta Daria online, con controlli reali</h1><p class="muted">Un solo percorso: raccogli le fonti, verifica ciò che Daria ha capito, collega i canali e solo dopo abilita le automazioni.</p></div>
    <span class="badge {{ $onboarding['automation_ready'] ? 'success' : ($onboarding['ready'] ? 'warm' : '') }}">{{ $onboarding['automation_ready'] ? 'Automazioni pronte' : ($onboarding['ready'] ? 'Modalità protetta' : 'Configurazione') }}</span>
</div>

@if(session('warning'))<div class="warning">{{ session('warning') }}</div>@endif
@if($organization->status === 'suspended')<div class="error"><strong>Workspace sospeso.</strong> La licenza non è utilizzabile. Contatta l’amministratore Daria.</div>@endif
@if($latestSetup && in_array($latestSetup->status, ['queued','running'], true))
    <div class="notice"><strong>Configurazione in elaborazione.</strong> Puoi continuare da dove avevi lasciato. <a href="{{ route('setup-wizard.status', $latestSetup->id) }}">Vedi avanzamento</a></div>
@elseif($latestSetup && $latestSetup->status === 'failed')
    <div class="warning"><strong>L’ultima elaborazione si è interrotta senza modificare il workspace.</strong> <a href="{{ route('setup-wizard.status', $latestSetup->id) }}">Riprova con le stesse fonti</a></div>
@elseif($latestSetup && $latestSetup->status === 'completed' && !data_get($latestSetup->output, 'applied_at'))
    <div class="notice"><strong>La configurazione è pronta per la verifica.</strong> <a href="{{ route('setup-wizard.preview', $latestSetup->id) }}">Apri l’anteprima</a></div>
@endif

<section class="card readiness-hero">
    <div>
        <div class="readiness-score">{{ $onboarding['progress'] }}%</div>
        <div><h2>{{ $onboarding['ready'] ? 'Base operativa verificata' : 'Completa la base operativa' }}</h2><p class="muted">La percentuale cresce soltanto quando un requisito è presente e controllabile, non quando l’AI ha semplicemente prodotto del testo.</p></div>
    </div>
    <div class="readiness-bar"><span style="width:{{ $onboarding['progress'] }}%"></span></div>
</section>

@foreach($onboarding['warnings'] as $warning)<div class="warning">{{ $warning }}</div>@endforeach

<div class="setup-path">
    <section class="card setup-step {{ $onboarding['profile'] && $onboarding['knowledge'] ? 'is-complete' : 'is-current' }}">
        <div class="step-number">1</div>
        <div class="step-copy"><div class="step-title"><h2>Conoscenza aziendale</h2><span class="badge {{ $onboarding['profile'] && $onboarding['knowledge'] ? 'success' : 'warm' }}">{{ $onboarding['profile'] && $onboarding['knowledge'] ? 'Verificata' : 'Da completare' }}</span></div>
            <p>Inserisci sito e descrizione una sola volta. L’elaborazione avviene in background, conserva le fonti e non applica nulla senza anteprima.</p>
            <div class="step-facts"><span>Profilo {{ $onboarding['profile'] ? 'completo' : 'incompleto' }}</span><span>{{ $onboarding['knowledge_documents'] }} documenti utilizzabili</span></div>
            <a class="btn" href="{{ route('setup-wizard.create') }}">{{ $onboarding['profile'] ? 'Aggiorna conoscenza' : 'Avvia configurazione assistita' }}</a>
        </div>
    </section>

    <section class="card setup-step {{ $onboarding['source'] ? 'is-complete' : '' }}">
        <div class="step-number">2</div><div class="step-copy"><div class="step-title"><h2>Ingresso dei lead</h2><span class="badge {{ $onboarding['source'] ? 'success' : '' }}">{{ $onboarding['source'] ? 'Collegato' : 'Da collegare' }}</span></div>
            <p>Crea un endpoint e invia un lead di prova. Daria accetta payload differenti e conserva i dati originali.</p><a class="btn btn-muted" href="{{ route('settings.sources') }}">Configura sorgente</a></div>
    </section>

    <section class="card setup-step {{ $onboarding['mailbox_tested'] ? 'is-complete' : '' }}">
        <div class="step-number">3</div><div class="step-copy"><div class="step-title"><h2>Email dedicata</h2><span class="badge {{ $onboarding['mailbox_tested'] ? 'success' : ($onboarding['mailbox'] ? 'warm' : '') }}">{{ $onboarding['mailbox_tested'] ? 'Testata' : ($onboarding['mailbox'] ? 'Da testare' : 'Da configurare') }}</span></div>
            <p>Configura una sola identità Daria per invio e ricezione. Il controllo richiede un test riuscito in entrambe le direzioni.</p><a class="btn btn-muted" href="{{ route('settings.mailboxes.index') }}">Configura e testa email</a></div>
    </section>

    <section class="card setup-step {{ !$onboarding['quote_mode'] || $onboarding['pricing_executable'] ? 'is-complete' : '' }}">
        <div class="step-number">4</div><div class="step-copy"><div class="step-title"><h2>Preventivi e calcoli</h2><span class="badge {{ $onboarding['pricing_executable'] ? 'success' : ($onboarding['pricing'] ? 'warm' : '') }}">{{ $onboarding['pricing_executable'] ? 'Calcoli eseguibili' : ($onboarding['pricing'] ? 'Solo riferimento' : 'Facoltativo') }}</span></div>
            <p>Per generare preventivi non basta una fascia di prezzo: serve almeno una ricetta strutturata che Daria possa calcolare e controllare.</p>
            <div class="step-facts"><span>{{ $onboarding['pricing_rules'] }} listini attivi</span><span>{{ $onboarding['executable_pricing_rules'] }} ricette eseguibili</span></div>
            <a class="btn btn-muted" href="{{ route('pricing-import.create') }}">Importa listini e regole</a></div>
    </section>
</div>

<section class="card activation-gate">
    <div><div class="page-kicker">Gate di sicurezza</div><h2>{{ $onboarding['automation_ready'] ? 'Il workspace può essere automatizzato' : 'Invii automatici ancora protetti' }}</h2>
        <p class="muted">{{ $onboarding['automation_ready'] ? 'I requisiti tecnici minimi sono verificati. Mantieni inizialmente la revisione umana dei PDF.' : 'Daria può essere configurata e provata, ma gli invii non sono considerati pronti finché i controlli sopra non risultano superati.' }}</p></div>
    <a class="btn {{ $onboarding['automation_ready'] ? '' : 'btn-muted' }}" href="{{ route('settings.organization') }}">Controlla modalità operative</a>
</section>

@push('styles')
<style>
.readiness-hero{margin-bottom:16px}.readiness-hero>div:first-child{display:flex;align-items:center;gap:18px}.readiness-score{font-size:40px;font-weight:800;letter-spacing:-.05em;color:#101828}.readiness-hero h2{margin:0}.readiness-bar{height:9px!important;background:#eef1f5;border-radius:999px;overflow:hidden;margin-top:18px}.readiness-bar span{display:block;height:100%;background:linear-gradient(90deg,var(--brand),#45d6b5);border-radius:inherit}.setup-path{display:grid;gap:12px}.setup-step{display:grid;grid-template-columns:44px 1fr;gap:16px;box-shadow:none}.setup-step.is-current{border-color:#fda29b;box-shadow:0 0 0 3px rgba(255,107,94,.08)}.setup-step.is-complete .step-number{background:#ecfdf3;color:#067647}.step-number{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:#f2f4f7;color:#475467;font-weight:800}.step-title{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.step-title h2{margin:2px 0 0}.step-copy p{max-width:820px}.step-facts{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px}.step-facts span{font-size:12px;color:#475467;background:#f8fafc;border:1px solid #eaecf0;border-radius:999px;padding:5px 9px}.activation-gate{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-top:16px;border-left:4px solid {{ $onboarding['automation_ready'] ? '#12b76a' : '#f79009' }}.activation-gate h2{margin-bottom:4px}@media(max-width:760px){.activation-gate{align-items:flex-start;flex-direction:column}.setup-step{grid-template-columns:1fr}.step-title{display:block}.step-title .badge{margin-top:8px}}
</style>
@endpush
@endsection
