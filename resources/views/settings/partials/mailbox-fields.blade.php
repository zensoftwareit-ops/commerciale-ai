<div class="grid grid-2">
    <div><label>Nome della casella</label><input name="name" required value="{{ old('name',$mailbox?->name) }}" placeholder="Commerciale"></div>
    <div><label>Indirizzo/utente IMAP</label><input name="username" required value="{{ old('username',$mailbox?->username) }}" autocomplete="username" placeholder="commerciale@azienda.it"></div>
    <div><label>Server IMAP</label><input name="host" required value="{{ old('host',$mailbox?->host) }}" placeholder="imap.azienda.it"></div>
    <div><label>Porta</label><input type="number" min="1" max="65535" name="port" required value="{{ old('port',$mailbox?->port ?? 993) }}"></div>
    <div><label>Crittografia</label><select name="encryption"><option value="ssl" @selected(old('encryption',$mailbox?->encryption ?? 'ssl')==='ssl')>SSL</option><option value="tls" @selected(old('encryption',$mailbox?->encryption)==='tls')>TLS</option><option value="" @selected(old('encryption',$mailbox?->encryption)==='')>Nessuna</option></select></div>
    <div><label>Cartella</label><input name="folder" required value="{{ old('folder',$mailbox?->folder ?? 'INBOX') }}"></div>
    <div><label>Password {{ $creating ? '' : '(lascia vuoto per non modificarla)' }}</label><input type="password" name="password" @required($creating) autocomplete="new-password"></div>
    <div><label>Autenticazione</label><select name="authentication"><option value="">Automatica</option><option value="plain" @selected(old('authentication',$mailbox?->authentication)==='plain')>PLAIN</option><option value="login" @selected(old('authentication',$mailbox?->authentication)==='login')>LOGIN</option></select></div>
</div>
<label style="font-weight:400"><input style="width:auto" type="checkbox" name="validate_cert" value="1" @checked(old('validate_cert',$mailbox?->validate_cert ?? true))> Verifica il certificato TLS</label>
<label style="font-weight:400"><input style="width:auto" type="checkbox" name="is_active" value="1" @checked(old('is_active',$mailbox?->is_active ?? true))> Sincronizzazione attiva</label>

