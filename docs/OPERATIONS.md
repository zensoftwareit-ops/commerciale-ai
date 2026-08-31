# Gestione operativa di Daria su Plesk

Questa procedura descrive la messa in esercizio di Daria su `app.daria-ai.it`.
I comandi vanno eseguiti nella directory che contiene `artisan`, usando PHP 8.3
di Plesk.

## Prima messa in produzione

Nel repository Plesk selezionare il branch `software`, impostare il document root
sulla sua cartella `public/` e distribuire il commit piu recente. Poi eseguire:

```bash
cd /var/www/vhosts/daria-ai.it/app.daria-ai.it
/opt/plesk/php/8.3/bin/php /usr/lib64/plesk-9.0/composer.phar install --no-dev --optimize-autoloader --no-interaction
/opt/plesk/php/8.3/bin/php artisan optimize:clear
/opt/plesk/php/8.3/bin/php artisan migrate --force
/opt/plesk/php/8.3/bin/php artisan config:cache
/opt/plesk/php/8.3/bin/php artisan route:cache
/opt/plesk/php/8.3/bin/php artisan view:cache
```

Se il server usa `/usr/lib/plesk-9.0/composer.phar`, sostituire soltanto quel
percorso. Il pannello PHP Composer di Plesk resta equivalente e puo essere usato
al posto del comando Composer.

Verificare nel `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.daria-ai.it
SESSION_SECURE_COOKIE=true
AI_PROVIDER=openai
AUTOMATION_EXTERNAL_SEND_ENABLED=false
BILLING_SELF_SERVICE_ENABLED=false
PLATFORM_2FA_REQUIRED=false
```

Accedere come Super Admin, aprire **Sicurezza**, configurare l'app Authenticator e
salvare i codici di recupero fuori dal server. Solo dopo questa operazione impostare:

```dotenv
PLATFORM_2FA_REQUIRED=true
```

e ricaricare la configurazione:

```bash
/opt/plesk/php/8.3/bin/php artisan config:clear
/opt/plesk/php/8.3/bin/php artisan config:cache
```

## Cron diretto

In **Plesk > Attivita pianificate** creare un'attivita di tipo comando, ogni minuto:

```bash
cd /var/www/vhosts/daria-ai.it/app.daria-ai.it && /opt/plesk/php/8.3/bin/php artisan commerciale:run
```

Non usare `schedule:run`. `commerciale:run` elabora direttamente nuovi lead,
posta IMAP e conversazioni; un lock impedisce le sovrapposizioni. Eseguire una volta
il comando a mano e controllare che mostri i tre riepiloghi.

Il pannello Super Admin **Salute** segnala un errore se l'ultimo completamento ha
piu di dieci minuti o e fallito. La stessa diagnosi e disponibile da terminale:

```bash
/opt/plesk/php/8.3/bin/php artisan daria:system-status
```

## Backup e prova di ripristino

In **Plesk > Backup Manager** configurare un backup giornaliero che includa:

- file del dominio, compreso `.env`;
- database MariaDB dell'applicazione;
- configurazione della sottoscrizione.

Conservare almeno 7 copie giornaliere e 4 settimanali, preferibilmente anche in un
archivio remoto. Il backup contiene credenziali e dati dei lead: deve essere cifrato
e accessibile soltanto agli amministratori.

Almeno una volta al mese ripristinare l'ultima copia in un sottodominio o database
temporaneo non pubblico, verificare login, conteggio clienti/lead e apertura di una
scheda lead. Soltanto dopo una prova riuscita premere **Backup verificato ora** nella
pagina **Salute**. Il pulsante registra la verifica, non esegue il backup.

## Controllo operativo

Ogni giorno controllare **Salute**. Prima di aprire il servizio a utenti reali tutti i
controlli obbligatori devono essere verdi; gli avvisi vanno valutati. In particolare:

- nessun job fallito o errore IMAP persistente;
- nessun errore OpenAI ripetuto nelle ultime 24 ore;
- trasporto SMTP reale e mittente di sistema configurato;
- cron completato di recente;
- account Super Admin separato con 2FA obbligatoria;
- backup verificato negli ultimi sette giorni.

Il registro **Audit** mostra tutte le modifiche effettuate nel pannello Super Admin
senza conservare password o valori dei campi sensibili.

## Aggiornamento e rollback

Prima di ogni deploy avviare un backup Plesk manuale. Quindi mettere brevemente
l'applicazione in manutenzione, distribuire il branch `software` ed eseguire:

```bash
/opt/plesk/php/8.3/bin/php artisan down
/opt/plesk/php/8.3/bin/php /usr/lib64/plesk-9.0/composer.phar install --no-dev --optimize-autoloader --no-interaction
/opt/plesk/php/8.3/bin/php artisan migrate --force
/opt/plesk/php/8.3/bin/php artisan optimize:clear
/opt/plesk/php/8.3/bin/php artisan config:cache
/opt/plesk/php/8.3/bin/php artisan route:cache
/opt/plesk/php/8.3/bin/php artisan view:cache
/opt/plesk/php/8.3/bin/php artisan up
/opt/plesk/php/8.3/bin/php artisan daria:system-status
```

Le migrazioni non vanno annullate alla cieca. Se il deploy fallisce, mantenere la
modalita manutenzione e ripristinare insieme codice e database dal backup creato
prima del rilascio.

## Funzioni abilitate gradualmente

Durante il collaudo mantenere `AUTOMATION_EXTERNAL_SEND_ENABLED=false` e usare la
allowlist interna. Resend, onboarding DNS automatico, WordPress/Stripe self-service
e invio automatico a destinatari esterni restano disattivati inizialmente. La loro presenza
nel codice non autorizza l'attivazione in produzione.
