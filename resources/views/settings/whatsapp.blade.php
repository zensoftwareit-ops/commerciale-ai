@extends('layouts.app')
@section('title', 'WhatsApp beta · Daria')
@section('content')
<div class="toolbar"><div><div class="page-kicker">Canale beta</div><h1>WhatsApp Daria</h1><p class="muted">Collega un numero WhatsApp Business alla Cloud API ufficiale di Meta.</p></div><span class="badge warm">BETA</span></div>

<div class="warning"><strong>Per il collaudo usa un numero dedicato.</strong> Questa versione gestisce le conversazioni avviate dal cliente; l’invio proattivo tramite template Meta non è ancora incluso.</div>

<div class="grid grid-2" style="margin-top:16px">
    <section class="card">
        <h2>1. Configurazione in Meta</h2>
        <ol>
            <li>Crea o seleziona un Meta Business Portfolio e un account WhatsApp Business.</li>
            <li>Registra e verifica il numero dedicato.</li>
            <li>Crea un token di sistema con i permessi <code>whatsapp_business_management</code> e <code>whatsapp_business_messaging</code>.</li>
            <li>Configura il webhook e sottoscrivi il campo <code>messages</code>.</li>
        </ol>
        <label>Callback URL</label><input readonly value="{{ url('/api/v1/whatsapp/webhook') }}" onclick="this.select()">
        <p class="muted">Il token di verifica e l’App Secret vengono configurati globalmente sul server e non sono mostrati agli utenti.</p>
    </section>
    <section class="card">
        <div class="toolbar"><div><h2>Stato collegamento</h2><p class="muted">Verifica credenziali e accesso al numero tramite Graph API.</p></div><span class="badge {{ $account?->is_active ? 'success' : 'warm' }}">{{ $account?->is_active ? 'Attivo' : 'Non attivo' }}</span></div>
        @error('whatsapp')<div class="error">{{ $message }}</div>@enderror
        @if($account?->last_error)<div class="error">{{ $account->last_error }}</div>@endif
        @if($account)<p><strong>{{ $account->display_phone_number }}</strong><br><span class="muted">Ultimo test: {{ $account->last_tested_at?->format('d/m/Y H:i') ?: 'mai' }}</span></p><form method="post" action="{{ route('settings.whatsapp.test') }}">@csrf<button class="btn btn-muted">Verifica connessione</button></form>@else<p class="muted">Inserisci prima i dati ottenuti da Meta.</p>@endif
    </section>
</div>

<section class="card" style="margin-top:16px">
    <h2>2. Numero e credenziali</h2>
    <form method="post" action="{{ route('settings.whatsapp.update') }}">@csrf @method('put')
        <div class="grid grid-2"><div><label>Nome interno</label><input name="name" required value="{{ old('name',$account?->name ?? 'WhatsApp Daria') }}"></div><div><label>Numero visualizzato</label><input name="display_phone_number" required placeholder="+39 02 1234567" value="{{ old('display_phone_number',$account?->display_phone_number) }}"></div><div><label>WhatsApp Business Account ID</label><input name="waba_id" required value="{{ old('waba_id',$account?->waba_id) }}"></div><div><label>Phone Number ID</label><input name="phone_number_id" required value="{{ old('phone_number_id',$account?->phone_number_id) }}"></div></div>
        <label>Access token {{ $account ? '(lascia vuoto per non modificarlo)' : '' }}</label><input type="password" name="access_token" {{ $account ? '' : 'required' }} autocomplete="new-password">
        <div class="setting-checks" style="display:grid;gap:8px;margin-top:16px">
            <label style="font-weight:400"><input style="width:auto" type="checkbox" name="is_active" value="1" @checked(old('is_active',$account?->is_active))> Attiva ricezione e invio WhatsApp</label>
            <label style="font-weight:400"><input style="width:auto" type="checkbox" name="auto_reply_enabled" value="1" @checked(old('auto_reply_enabled',$account?->auto_reply_enabled))> Invia automaticamente le risposte che superano i controlli</label>
            <label style="font-weight:400"><input style="width:auto" type="checkbox" name="internal_test_only" value="1" @checked(old('internal_test_only',$account?->exists ? $account->internal_test_only : true))> Limita la beta ai numeri interni autorizzati</label>
        </div>
        <label>Numeri autorizzati per il test, uno per riga</label><textarea name="allowed_recipients_text" rows="4" placeholder="+39 333 1234567">{{ old('allowed_recipients_text',implode("\n",$account?->allowed_recipients ?? [])) }}</textarea>
        @foreach($errors->all() as $error)<div class="error">{{ $error }}</div>@endforeach
        <button class="btn" style="margin-top:16px">Salva configurazione WhatsApp</button>
    </form>
</section>
@endsection
