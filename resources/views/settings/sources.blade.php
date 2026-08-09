@extends('layouts.app')
@section('title', 'Sorgenti lead · Commerciale AI')
@section('content')
<div class="toolbar"><div><h1>Sorgenti lead</h1><p class="muted">Credenziali per collegare form, siti e gestionali tramite webhook firmato.</p></div><span class="badge">Solo owner</span></div>

@if($credentials=session('webhook_credentials'))
<div class="card" style="margin-bottom:1rem;border-color:#f79009">
    <strong>Credenziali da copiare ora</strong>
    <p class="muted">Il segreto viene mostrato una sola volta. Conservalo nel sistema che invia i lead.</p>
    <label>Chiave sorgente</label><input readonly value="{{ $credentials['key'] }}" onclick="this.select()">
    <label>Segreto HMAC</label><input readonly value="{{ $credentials['secret'] }}" onclick="this.select()">
</div>
@endif

<div class="grid grid-2">
    <section class="card">
        <h2>Endpoint</h2>
        <input readonly value="{{ $endpoint }}" onclick="this.select()">
        <p class="muted">Firma: HMAC-SHA256 del testo <code>timestamp.corpo_json_esatto</code>. Invia gli header <code>X-Webhook-Source</code>, <code>X-Webhook-Timestamp</code>, <code>X-Webhook-Signature</code> e <code>Idempotency-Key</code>.</p>
    </section>
    <form class="card" method="post" action="{{ route('settings.sources.store') }}">@csrf
        <h2>Nuova sorgente</h2>
        <label>Nome</label><input name="name" required maxlength="255" placeholder="Sito aziendale">
        @error('name')<div class="error">{{ $message }}</div>@enderror
        <br><button class="btn">Genera credenziali</button>
    </form>
</div>

<div class="card" style="margin-top:1rem">
    <h2>Sorgenti configurate</h2>
    <table><thead><tr><th>Nome</th><th>Chiave</th><th>Stato</th><th></th></tr></thead><tbody>
    @forelse($sources as $source)
        <tr><td>{{ $source->name }}</td><td><code>{{ $source->key }}</code></td><td><span class="badge">{{ $source->is_active ? 'Attiva' : 'Disattiva' }}</span></td><td><form method="post" action="{{ route('settings.sources.rotate',$source) }}" onsubmit="return confirm('Ruotare il segreto? Quello attuale smetterà subito di funzionare.')">@csrf @method('patch')<button class="btn btn-muted">Ruota segreto</button></form></td></tr>
    @empty<tr><td colspan="4">Nessuna sorgente configurata.</td></tr>@endforelse
    </tbody></table>
</div>
@endsection
