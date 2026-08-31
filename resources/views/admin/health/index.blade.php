@extends('layouts.admin')
@section('title', 'Salute sistema · Daria')
@section('content')
<div class="toolbar"><div><h1>Salute sistema</h1><p class="muted">Controlli operativi calcolati in tempo reale sul server.</p></div><span class="badge">{{ $ready ? 'PRONTO' : 'DA COMPLETARE' }}</span></div>
<div class="grid">
@foreach($checks as $check)
<article class="card">
    <div class="toolbar"><h3>{{ $check['label'] }}</h3><span class="badge">{{ strtoupper($check['status']) }}</span></div>
    <p class="muted">{{ $check['detail'] }}</p>
</article>
@endforeach
</div>
<section class="card">
    <h2>Verifica backup</h2>
    <p class="muted">Prima scarica o individua un backup Plesk recente, verifica che contenga database e file applicativi e, idealmente, prova il ripristino in staging.</p>
    <form method="post" action="{{ route('admin.health.backup-confirm') }}" onsubmit="return confirm('Hai realmente verificato disponibilità e ripristinabilità del backup?')">@csrf<button>Conferma backup verificato oggi</button></form>
</section>
@if($settings->last_automation_summary)
<section class="card"><h2>Ultima automazione</h2><pre style="white-space:pre-wrap">{{ json_encode($settings->last_automation_summary, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>@if($settings->last_automation_error)<div class="error">{{ $settings->last_automation_error }}</div>@endif</section>
@endif
@endsection
