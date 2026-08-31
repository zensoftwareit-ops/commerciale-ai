<h2>Identità commerciale</h2>
<p class="muted">È l’identità usata da tutte le email ai lead, indipendentemente dall’utente che lavora la richiesta.</p>
<div class="notice">Il dominio del mittente deve essere autorizzato sul provider SMTP tramite SPF/DKIM. Il Reply-To deve corrispondere a questa casella IMAP oppure a un suo alias.</div>
<div class="grid grid-2">
    <div><label>Indirizzo mittente</label><input type="email" name="from_address" required value="{{ old('from_address',$mailbox?->from_address) }}" placeholder="commerciale@azienda.it"></div>
    <div><label>Nome mittente</label><input name="from_name" required maxlength="255" value="{{ old('from_name',$mailbox?->from_name) }}" placeholder="Ufficio commerciale Azienda"></div>
    <div><label>Reply-To</label><input type="email" name="reply_to_address" value="{{ old('reply_to_address',$mailbox?->reply_to_address) }}" placeholder="Lascia vuoto per usare il mittente"></div>
</div>

<h2 style="margin-top:24px">Ricezione IMAP</h2>
<p class="muted">Questa casella riceve le risposte dei lead. Le credenziali sono cifrate nel database.</p>
<div class="grid grid-2">
    <div><label>Utente IMAP</label><input name="username" required value="{{ old('username',$mailbox?->username) }}" autocomplete="username" placeholder="commerciale@azienda.it"></div>
    <div><label>Server IMAP</label><input name="host" required value="{{ old('host',$mailbox?->host) }}" placeholder="imap.azienda.it"></div>
    <div><label>Porta</label><input type="number" min="1" max="65535" name="port" required value="{{ old('port',$mailbox?->port ?? 993) }}"></div>
    <div><label>Crittografia</label><select name="encryption"><option value="ssl" @selected(old('encryption',$mailbox?->encryption ?? 'ssl')==='ssl')>SSL</option><option value="tls" @selected(old('encryption',$mailbox?->encryption)==='tls')>TLS</option><option value="" @selected(old('encryption',$mailbox?->encryption)==='')>Nessuna</option></select></div>
    <div><label>Cartella</label><input name="folder" required value="{{ old('folder',$mailbox?->folder ?? 'INBOX') }}"></div>
    <div><label>Password {{ $creating ? '' : '(lascia vuoto per non modificarla)' }}</label><input type="password" name="password" @required($creating) autocomplete="new-password"></div>
    <div><label>Autenticazione</label><select name="authentication"><option value="">Automatica</option><option value="plain" @selected(old('authentication',$mailbox?->authentication)==='plain')>PLAIN</option><option value="login" @selected(old('authentication',$mailbox?->authentication)==='login')>LOGIN</option></select></div>
</div>
<label style="font-weight:400"><input style="width:auto" type="checkbox" name="validate_cert" value="1" @checked(old('validate_cert',$mailbox?->validate_cert ?? true))> Verifica il certificato TLS</label>
<label style="font-weight:400"><input style="width:auto" type="checkbox" name="is_active" value="1" @checked(old('is_active',$mailbox?->is_active ?? true))> Email Daria attiva</label>
