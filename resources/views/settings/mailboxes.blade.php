@extends('layouts.app')
@section('title', 'Email Daria · Daria')
@section('content')
<div class="toolbar"><div><div class="page-kicker">Configurazione aziendale</div><h1>Email Daria</h1><p class="muted">Un’unica identità dedicata per inviare ai lead e acquisire le loro risposte.</p></div><span class="badge">Solo owner</span></div>
@if($mailbox)<div class="{{ $mailbox->domain_verification_status === 'verified' ? 'notice' : 'warning' }}"><strong>Dominio mittente: {{ $mailbox->domain_verification_status === 'verified' ? 'verificato' : 'in attesa di verifica' }}.</strong> @if($mailbox->domain_verification_status !== 'verified')Gli invii automatici esterni restano bloccati finché l’amministratore Daria non conferma SPF/DKIM.@else Verificato il {{ $mailbox->domain_verified_at?->format('d/m/Y H:i') }}.@endif</div>@endif

<section class="card" style="margin-bottom:16px">
    <div class="toolbar"><div><h2>Trasporto in uscita</h2><p class="muted">Il trasporto SMTP o Resend è gestito globalmente dalla piattaforma; qui configuri l’identità della tua organizzazione.</p></div><span class="badge {{ $mailTransport['deliverable'] ? 'success' : 'hot' }}">{{ strtoupper($mailTransport['mailer']) }}</span></div>
    <p><strong>Stato:</strong> {{ $mailTransport['message'] }}</p>
    @if($mailTransport['host'])<p class="muted">Server {{ $mailTransport['scheme'] ? $mailTransport['scheme'].'://' : '' }}{{ $mailTransport['host'] }}{{ $mailTransport['port'] ? ':'.$mailTransport['port'] : '' }}</p>@endif
</section>

<section class="card">
    <form method="post" action="{{ $mailbox ? route('settings.mailboxes.update',$mailbox) : route('settings.mailboxes.store') }}">
        @csrf
        @if($mailbox) @method('put') @endif
        @include('settings.partials.mailbox-fields',['mailbox'=>$mailbox,'creating'=>!$mailbox])
        @foreach($errors->all() as $error)<div class="error">{{ $error }}</div>@endforeach
        <button class="btn" style="margin-top:16px">{{ $mailbox ? 'Salva configurazione' : 'Configura Email Daria' }}</button>
    </form>
</section>

@if($mailbox)
<section class="card" style="margin-top:16px">
    <div class="toolbar">
        <div><h2>Verifica dominio con Resend</h2><p class="muted">Daria registra il dominio mittente, mostra i record DNS e acquisisce direttamente lo stato restituito da Resend.</p></div>
        @if($mailbox->resend_domain_status === 'verified')
            <span class="badge success">VERIFIED</span>
        @elseif($mailbox->resend_domain_status)
            <span class="badge warm">{{ strtoupper($mailbox->resend_domain_status) }}</span>
        @else
            <span class="badge">NON REGISTRATO</span>
        @endif
    </div>

    @if(!$resendDomainAutomation['enabled'])
        <div class="warning">La funzione è disabilitata sul server. Imposta <code>RESEND_DOMAIN_AUTOMATION_ENABLED=true</code> e ricrea la cache di configurazione.</div>
    @elseif(!$resendDomainAutomation['configured'])
        <div class="error">La chiave <code>RESEND_API_KEY</code> non è disponibile nella configurazione caricata da Laravel.</div>
    @else
        <div class="notice" style="margin-bottom:16px">La chiave API deve avere permesso <strong>Full access</strong>. Le chiavi Resend limitate al solo invio non possono creare o verificare domini.</div>
        @if($mailbox->resend_last_error)<div class="error">{{ $mailbox->resend_last_error }}</div>@endif

        @if(!$mailbox->resend_domain_id)
            <p>Daria registrerà su Resend il dominio ricavato da <strong>{{ $mailbox->from_address }}</strong>. La chiave API non viene mai mostrata né salvata nell’account cliente.</p>
            <form method="post" action="{{ route('settings.mailboxes.resend-domain.register',$mailbox) }}">@csrf<button class="btn" type="submit">Registra dominio su Resend</button></form>
        @else
            <p><strong>Dominio:</strong> {{ $mailbox->resend_domain_name }}<br><span class="muted">ID Resend: {{ $mailbox->resend_domain_id }} · Ultimo controllo: {{ $mailbox->resend_last_checked_at?->format('d/m/Y H:i:s') ?: 'mai' }}</span></p>

            @if($mailbox->resend_dns_records)
                <div class="notice" style="margin-bottom:16px">
                    <strong>Come leggere la tabella:</strong> “Scopo” spiega a cosa serve il record; “Tipo DNS” è il tipo da selezionare nel pannello DNS.
                    I record MX e TXT sul nome <code>send</code> sono entrambi necessari: configurano il Return-Path di Resend e non modificano la ricezione IMAP del dominio principale.
                </div>
                <div class="table-wrap"><table>
                    <thead><tr><th>Scopo</th><th>Tipo DNS</th><th>Nome da inserire</th><th>Destinazione / valore</th><th>Priorità</th><th>Stato</th></tr></thead>
                    <tbody>@foreach($mailbox->resend_dns_records as $record)
                    @php
                        $recordPurpose = $record['record'] ?? '';
                        $recordType = strtoupper($record['type'] ?? '');
                        $recordName = trim((string) ($record['name'] ?? ''), '.');
                        $domainName = trim((string) $mailbox->resend_domain_name, '.');
                        $fullHostname = $recordName === $domainName || str_ends_with($recordName, '.'.$domainName)
                            ? $recordName
                            : $recordName.'.'.$domainName;
                        $purposeLabel = match (true) {
                            $recordPurpose === 'DKIM' => 'Firma DKIM',
                            $recordPurpose === 'SPF' && $recordType === 'MX' => 'Return-Path / bounce',
                            $recordPurpose === 'SPF' && $recordType === 'TXT' => 'Autorizzazione SPF',
                            $recordPurpose === 'Tracking' => 'Tracciamento link',
                            default => $recordPurpose ?: 'Configurazione Resend',
                        };
                    @endphp
                    <tr>
                        <td><strong>{{ $purposeLabel }}</strong>@if($recordPurpose)<br><span class="muted">Categoria Resend: {{ $recordPurpose }}</span>@endif</td>
                        <td><code>{{ $record['type'] ?? '—' }}</code></td>
                        <td><code style="word-break:break-all">{{ $recordName ?: '—' }}</code>@if($recordName)<br><span class="muted">Hostname completo:<br><code style="word-break:break-all">{{ $fullHostname }}</code></span>@endif</td>
                        <td><code style="word-break:break-all">{{ $record['value'] ?? '—' }}</code></td>
                        <td>{{ $record['priority'] ?? '—' }}</td>
                        <td><span class="badge {{ ($record['status'] ?? '') === 'verified' ? 'success' : 'warm' }}">{{ strtoupper($record['status'] ?? 'da inserire') }}</span></td>
                    </tr>@endforeach</tbody>
                </table></div>
                <p class="muted">Se il pannello DNS aggiunge automaticamente il dominio della zona, inserisci il nome breve mostrato in alto (per esempio <code>send</code>). Se richiede un FQDN, usa l’hostname completo mostrato sotto.</p>
            @endif

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px">
                @if($mailbox->resend_domain_status !== 'verified')<form method="post" action="{{ route('settings.mailboxes.resend-domain.verify',$mailbox) }}">@csrf<button class="btn" type="submit">Verifica record DNS</button></form>@endif
                <form method="post" action="{{ route('settings.mailboxes.resend-domain.refresh',$mailbox) }}">@csrf<button class="btn btn-muted" type="submit">Aggiorna stato</button></form>
            </div>
            @if($mailbox->resend_domain_status !== 'verified')<p class="muted">La verifica è asincrona: dopo avere inserito i record nel DNS, avviala e usa “Aggiorna stato” dopo alcuni minuti.</p>@endif
        @endif
    @endif
</section>

<div class="grid grid-2" style="margin-top:16px">
    <section class="card">
        <div class="toolbar"><div><h2>Test ricezione IMAP</h2><p class="muted">Verifica credenziali, certificato e accesso alla cartella configurata.</p></div><span class="badge {{ $mailbox->last_error ? 'hot' : ($mailbox->last_tested_at ? 'success' : 'warm') }}">{{ $mailbox->last_error ? 'Errore' : ($mailbox->last_tested_at ? 'Verificata' : 'Da verificare') }}</span></div>
        @if($mailbox->last_error)<div class="error">{{ $mailbox->last_error }}</div>@endif
        <form method="post" action="{{ route('settings.mailboxes.test',$mailbox) }}">@csrf<button class="btn" type="submit">Verifica IMAP</button></form>
        <p class="muted">Ultimo test: {{ $mailbox->last_tested_at?->format('d/m/Y H:i') ?: 'mai' }} · Ultima sincronizzazione: {{ $mailbox->last_synced_at?->format('d/m/Y H:i') ?: 'mai' }}</p>
    </section>
    <section class="card">
        <div class="toolbar"><div><h2>Test invio</h2><p class="muted">Invia un messaggio usando mittente, Reply-To e trasporto effettivi.</p></div><span class="badge {{ $mailbox->last_outbound_error ? 'hot' : ($mailbox->last_outbound_tested_at ? 'success' : 'warm') }}">{{ $mailbox->last_outbound_error ? 'Errore' : ($mailbox->last_outbound_tested_at ? 'Verificato' : 'Da verificare') }}</span></div>
        @if($mailbox->last_outbound_error)<div class="error">{{ $mailbox->last_outbound_error }}</div>@endif
        <form method="post" action="{{ route('settings.mailboxes.test-outbound',$mailbox) }}">@csrf<label>Invia il test a</label><input type="email" name="test_recipient" required value="{{ old('test_recipient',auth()->user()->email) }}"><br><button class="btn" type="submit" @disabled(!$mailTransport['deliverable'])>Invia test</button></form>
        <p class="muted">Ultimo test: {{ $mailbox->last_outbound_tested_at?->format('d/m/Y H:i') ?: 'mai' }}</p>
    </section>
</div>
<form method="post" action="{{ route('settings.mailboxes.destroy',$mailbox) }}" onsubmit="return confirm('Rimuovere completamente la configurazione Email Daria?')" style="margin-top:16px">@csrf @method('delete')<button class="btn btn-danger" type="submit">Rimuovi configurazione</button></form>
@endif
@endsection
