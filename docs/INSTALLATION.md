# Guida base di installazione

Questa guida installa una singola istanza di Daria senza Docker. La stessa applicazione è multi-tenant, ma per il progetto pilota si utilizza una sola organizzazione.

## 1. Requisiti

- PHP 8.3 o superiore;
- Composer 2;
- estensioni PHP `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pdo`, `session`, `tokenizer`, `xml` e `zip`;
- `pdo_sqlite` per una prova locale oppure `pdo_mysql`/`pdo_pgsql` per il server;
- HTTPS per qualsiasi installazione accessibile da Internet;
- possibilità di impostare il document root del dominio sulla cartella `public/`.

Verifica rapida:

```bash
php -v
composer --version
php -m
```

## 2. Installazione locale

Clonare il progetto e preparare l'applicazione:

```bash
git clone https://github.com/zensoftwareit-ops/commerciale-ai.git
cd commerciale-ai
composer install
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
php artisan serve
```

Su Windows PowerShell, sostituire `cp .env.example .env` con:

```powershell
Copy-Item .env.example .env
```

Aprire `http://127.0.0.1:8000`. Il seed crea un account dimostrativo per il solo
sviluppo locale. Prima di rendere raggiungibile l'applicazione, impostare una
password univoca e robusta dalla pagina **Account**. Non conservare credenziali
iniziali nella documentazione o nel repository.

## 3. Installazione su server

### Procedura Plesk con MariaDB

Questa è la sequenza da seguire quando il repository è già stato collegato a Plesk e `git pull` è terminato correttamente. **Non usare `composer run setup` su Plesk**: quel comando prepara l'ambiente locale con SQLite e dipendenze di sviluppo.

#### 1. Selezionare PHP 8.3

In **Siti Web e Domini > PHP** selezionare PHP 8.3 o superiore, preferibilmente con PHP-FPM. Verificare che siano abilitate almeno le estensioni:

```text
ctype, curl, dom, fileinfo, filter, hash, iconv, json, libxml,
mbstring, openssl, pdo, pdo_mysql, session, tokenizer, xml, zip
```

Impostare `memory_limit` ad almeno `512M` per l'installazione. Plesk consente di cambiare versione e impostazioni PHP dalla pagina PHP del dominio.

#### 2. Installare le dipendenze Composer

Dopo il `git pull`, eseguire **Install**, non **Update**: `install` rispetta le versioni bloccate in `composer.lock`, mentre `update` potrebbe cambiarle.

Dal pannello Plesk, a seconda della versione dell'interfaccia:

1. aprire **Siti Web e Domini > dominio > PHP Composer**;
2. verificare che la cartella dell'applicazione sia quella contenente `composer.json`;
3. premere **Scan/Scansiona** se l'applicazione non è elencata;
4. premere **Install Dependencies/Installa dipendenze**.

In alternativa, via terminale SSH, entrare nella radice del repository ed eseguire su Debian/Ubuntu:

```bash
/opt/plesk/php/8.3/bin/php /usr/lib/plesk-9.0/composer.phar install --no-dev --optimize-autoloader --no-interaction
```

Su sistemi RHEL/AlmaLinux/Rocky il percorso Plesk può essere `/usr/lib64/plesk-9.0/composer.phar`:

```bash
/opt/plesk/php/8.3/bin/php /usr/lib64/plesk-9.0/composer.phar install --no-dev --optimize-autoloader --no-interaction
```

Se non sai quale percorso è presente, usa il pulsante PHP Composer di Plesk. Al termine deve esistere la directory `vendor/`.

#### 3. Creare database e utente MariaDB

In **Siti Web e Domini > Database > Aggiungi database**:

1. creare un database, per esempio `commerciale_ai`;
2. associarlo al dominio dell'applicazione nel campo **Sito correlato**;
3. selezionare **Crea un utente database**;
4. usare una password casuale robusta;
5. assegnare all'utente accesso in lettura e scrittura al database;
6. annotare i nomi completi mostrati da Plesk, che potrebbero avere un prefisso;
7. aprire **Informazioni di connessione** e annotare l'host, normalmente `localhost` o `127.0.0.1`.

Non è necessario creare manualmente le tabelle: lo farà Laravel con le migrazioni.

#### 4. Creare e configurare `.env`

Nella radice del repository, allo stesso livello di `artisan`, creare `.env` copiando `.env.example`. Da terminale:

```bash
cp .env.example .env
```

Impostare almeno:

```dotenv
APP_NAME="Daria - PreventivoSitoWeb.it"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMINIO-APP
APP_TIMEZONE=Europe/Rome

APP_LOCALE=it
APP_FALLBACK_LOCALE=it

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=NOME_ESATTO_DATABASE_PLESK
DB_USERNAME=NOME_ESATTO_UTENTE_PLESK
DB_PASSWORD="PASSWORD_DATABASE"

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database

AI_PROVIDER=openai
OPENAI_API_KEY=CHIAVE_OPENAI
OPENAI_MODEL=gpt-5.6-terra
```

Se `DB_HOST=127.0.0.1` non funziona, usare esattamente l'host indicato da **Informazioni di connessione** in Plesk. Racchiudere la password tra virgolette se contiene `#`, spazi o caratteri speciali.

Il file `.env` non deve essere aggiunto a Git né collocato nella cartella pubblica.

#### 5. Generare la chiave e creare le tabelle

Dalla radice del progetto:

```bash
/opt/plesk/php/8.3/bin/php artisan key:generate --force
/opt/plesk/php/8.3/bin/php artisan config:clear
/opt/plesk/php/8.3/bin/php artisan migrate --seed --force
```

Il seed crea l'organizzazione iniziale, la pipeline, una knowledge base dimostrativa
e l'utente iniziale. Prima di rendere pubblico il dominio, impostare per l'utente una
password univoca e robusta dalla pagina **Account**. Non usare credenziali note o
documentate in un ambiente di produzione.

#### 6. Configurare il document root

In **Siti Web e Domini > Impostazioni di hosting**, impostare **Document root** sulla cartella `public` del progetto.

Esempi:

```text
Repository in httpdocs/commerciale-ai  ->  httpdocs/commerciale-ai/public
Repository direttamente in httpdocs   ->  httpdocs/public
```

Non impostare mai come document root la radice contenente `.env`, `composer.json` e `artisan`.

#### 7. Permessi e HTTPS

Con File Manager verificare che l'utente della sottoscrizione possa scrivere in:

```text
storage/
bootstrap/cache/
```

Abilitare un certificato valido in **SSL/TLS Certificates** e il reindirizzamento permanente da HTTP a HTTPS. `SESSION_SECURE_COOKIE=true` richiede HTTPS.

#### 8. Ottimizzare e verificare

```bash
/opt/plesk/php/8.3/bin/php artisan optimize:clear
/opt/plesk/php/8.3/bin/php artisan config:cache
/opt/plesk/php/8.3/bin/php artisan route:cache
/opt/plesk/php/8.3/bin/php artisan view:cache
/opt/plesk/php/8.3/bin/php artisan migrate:status
```

Aprire `APP_URL`, accedere e cambiare immediatamente la password. Se compare un errore 500, controllare **Siti Web e Domini > Log** e `storage/logs/laravel.log`.

Per il pilota `preventivositoweb.it`, proseguire con [INSTANCE-PREVENTIVOSITOWEB.md](INSTANCE-PREVENTIVOSITOWEB.md).

### Installazione server generica

Scaricare il codice nella directory dell'applicazione e installare le dipendenze ottimizzate:

```bash
git clone https://github.com/zensoftwareit-ops/commerciale-ai.git
cd commerciale-ai
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Il file `.env` non deve essere pubblicato, versionato o servito dal web server. Un'impostazione minima di produzione è:

```dotenv
APP_NAME="Daria"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMINIO-DELL-APP
APP_TIMEZONE=Europe/Rome

APP_LOCALE=it
APP_FALLBACK_LOCALE=it

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=commerciale_ai
DB_USERNAME=commerciale_ai
DB_PASSWORD=PASSWORD_DATABASE

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database

AI_PROVIDER=openai
OPENAI_API_KEY=CHIAVE_OPENAI
OPENAI_MODEL=gpt-5.6-terra
OPENAI_REASONING_EFFORT=low
OPENAI_TIMEOUT=45
```

Sono supportati anche PostgreSQL (`DB_CONNECTION=pgsql`) e SQLite. Per un'istanza pubblica è consigliato MySQL/MariaDB o PostgreSQL; SQLite è più adatto al collaudo locale.

Dopo aver creato il database vuoto:

```bash
php artisan migrate --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Il seed non sovrascrive i dati esistenti, ma crea l'utente demo se manca. Accedere immediatamente e cambiare la password.

## 4. Web server e permessi

Il document root del dominio deve essere la directory assoluta `commerciale-ai/public`, mai la radice del repository. Il processo PHP deve poter scrivere soltanto in:

```text
storage/
bootstrap/cache/
```

Su un server Linux con utente web `www-data`, adattando il percorso:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
```

Laravel include già `public/.htaccess` per Apache. Con Nginx tutte le richieste non corrispondenti a file reali devono essere inoltrate a `public/index.php`.

## 5. Posta elettronica

Senza SMTP il recupero password e le risposte ai lead vengono scritti nei log e non arrivano al destinatario. Per abilitare gli invii reali:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=SMTP_HOST
MAIL_PORT=587
MAIL_USERNAME=SMTP_USERNAME
MAIL_PASSWORD=SMTP_PASSWORD
MAIL_FROM_ADDRESS=EMAIL_MITTENTE
MAIL_FROM_NAME="Daria"
```

Su Plesk è possibile usare la casella del dominio. In genere `MAIL_HOST` è il nome del server di posta mostrato nel pannello, la porta è `587` con TLS oppure `465` con SMTPS. Usare sempre i valori forniti dal proprio servizio email.

Dopo la modifica eseguire:

```bash
/opt/plesk/php/8.3/bin/php artisan optimize:clear
/opt/plesk/php/8.3/bin/php artisan config:cache
```

Verificare prima con un reset password di prova, poi con un lead di test: analizzarlo, salvare la bozza e premere **Approva e invia**. Non inserire credenziali SMTP nel repository.

#### Strategia SMTP del pilota e predisposizione Resend

Per il pilota e per il primo gruppo ristretto di clienti l'invio resta monolitico:
un unico account SMTP transazionale configurato sul server con `MAIL_MAILER=smtp`.
I domini mittenti, SPF e DKIM vengono configurati manualmente presso il provider.

Il progetto include anche il mailer `resend_smtp`, lasciato inattivo e senza dipendenze
Composer aggiuntive. Non abilitarlo durante il pilota. Quando inizierà la vendita
self-service, dopo avere verificato almeno un dominio su Resend, il passaggio potrà
essere effettuato impostando:

```ini
MAIL_MAILER=resend_smtp
RESEND_API_KEY=re_CHIAVE_SERVER
RESEND_DOMAIN_AUTOMATION_ENABLED=false
```

La chiave resta globale e solo sul server. `RESEND_DOMAIN_AUTOMATION_ENABLED` deve
rimanere `false` finché il pannello di onboarding DNS non sarà stato implementato.
In quella fase la REST API di Resend verrà usata per creare i domini dei tenant,
mostrare SPF/DKIM e aggiornarne lo stato; il trasporto delle email resterà separato
da questa procedura amministrativa.

### Modulo licenze: Step 1 manuale

Per la prima fase non servono WordPress, Stripe o una chiave di integrazione.
Il Super Admin registra il cliente, crea owner e organizzazione e attiva la licenza
da `/admin/licensing`. Nel `.env` mantenere:

```ini
BILLING_SELF_SERVICE_ENABLED=false
BILLING_INTEGRATION_KEY=
LICENSE_ENFORCEMENT_ENABLED=false
```

Creare l'account amministrativo separato con
`php artisan platform-admin:create admin@azienda.it`. Il comando genera una
password temporanea mostrata una sola volta. L'indirizzo non deve appartenere a
un'organizzazione cliente. Lo Step 2 con WordPress e Stripe è implementato, ma
resta inattivo finché non viene configurato e collaudato.

Il modulo licenze viene installato con le normali migrazioni ma non blocca il pilota:
`LICENSE_ENFORCEMENT_ENABLED` è `false` per impostazione predefinita. La procedura
completa per Super Admin, pacchetti, Stripe e plugin WordPress è descritta in
[`BILLING-AND-LICENSES.md`](BILLING-AND-LICENSES.md).

## 6. Posta in ingresso IMAP

Per importare le risposte dei lead, accedere come owner e aprire **Caselle email**. Inserire nome, host, porta, cifratura, utente, password e cartella IMAP, quindi premere **Verifica connessione**. È possibile collegare più caselle alla stessa organizzazione. Le password sono cifrate nel database con `APP_KEY` e non vengono mai mostrate nell'interfaccia.

Usare i parametri indicati dal fornitore e non disabilitare la verifica del certificato in produzione. Dopo aver salvato almeno una casella attiva:

```bash
/opt/plesk/php/8.3/bin/php artisan mail:sync --test
/opt/plesk/php/8.3/bin/php artisan mail:sync
```

Il comando mostra soltanto i conteggi. Le risposte con un riferimento certo a una conversazione inviata vengono associate anche quando il cliente usa una casella diversa; nella scheda del lead compare un avviso e la bozza resta indirizzata all'email principale. Gli indirizzi secondari già confermati vengono riconosciuti. I messaggi senza prove sufficienti vengono conservati nella pagina **Email da associare**, dove un operatore può scegliere il lead e, facoltativamente, salvare il mittente come contatto secondario.

In **Plesk > Siti Web e Domini > Attività pianificate**, creare un'unica attività
diretta ogni minuto, eseguita dalla radice del progetto:

```bash
cd /PERCORSO/ASSOLUTO/DEL/PROGETTO && /opt/plesk/php/8.3/bin/php artisan commerciale:run
```

Non configurare `schedule:run`: il comando diretto esegue nuovi lead, sincronizzazione
IMAP e conversazioni nello stesso ciclo. Un lock di cinque minuti evita
sovrapposizioni se un'esecuzione precedente è ancora in corso.

### Automazione preventivi (solo collaudo interno)

In **Azienda** configurare prima il listino strutturato. Ogni regola richiede parole chiave, fascia minima/massima ed eventuali campi obbligatori del payload. Poi inserire gli indirizzi di prova nella lista interna e mantenere selezionato **Limita l'automazione ai destinatari interni autorizzati**.

Per una verifica manuale delle sole conversazioni è ancora disponibile:

```bash
/opt/plesk/php/8.3/bin/php artisan conversations:automate
```

Per una verifica manuale dei soli nuovi lead è ancora disponibile:

```bash
/opt/plesk/php/8.3/bin/php artisan leads:automate-new
```

Quando nel pannello è abilitato **Analizza automaticamente i nuovi lead**, il
comando analizza tutti i lead creati dopo l'attivazione e prepara la bozza iniziale.
La modalità test interno e la lista consentita limitano soltanto l'invio: le bozze
verso destinatari non autorizzati restano disponibili per l'approvazione manuale.
In caso di errore il sistema riprova al massimo tre volte riutilizzando analisi e
bozza già create.

Quando mancano dati obbligatori può inviare soltanto una domanda di qualificazione, con massimo due quesiti. Non invia automaticamente un preventivo se: la regola è ambigua, mancano dati, la fascia supera il limite, il mittente richiede verifica, è stato raggiunto il numero massimo di risposte, il destinatario non è nella lista interna oppure l'invio preventivi è spento. Per un test controllato lanciare manualmente il comando e verificare i conteggi prima di affidarlo al cron.

Nel file `.env` lasciare:

```dotenv
AUTOMATION_EXTERNAL_SEND_ENABLED=false
```

Questo interruttore server impedisce invii automatici a destinatari esterni anche in caso di errore nel pannello. Non abilitarlo durante il pilota interno.

## 7. Aggiornamenti

Prima di aggiornare eseguire un backup di database e file `.env`, quindi:

```bash
php artisan down
git pull --ff-only
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

## 8. Controlli finali

```bash
php artisan about
php artisan migrate:status
```

In un ambiente di sviluppo, dove sono presenti anche le dipendenze `require-dev`, eseguire inoltre `php artisan test`.

Dal browser verificare inoltre:

- login e cambio password;
- pagina Azienda;
- creazione di un lead manuale;
- analisi OpenAI;
- generazione, modifica e invio di una bozza email;
- pianificazione di un follow-up;
- ricezione di una risposta nella casella IMAP, annullamento del follow-up e nuova bozza;
- creazione e rotazione di una sorgente webhook;
- reset password via email, se SMTP è attivo.

Il worker delle code non è indispensabile per i flussi attuali. Quando saranno introdotti invii o elaborazioni asincrone, avviare stabilmente `php artisan queue:work --tries=3` tramite il sistema di process management del server.

## 9. Problemi frequenti

- **Errore 500 dopo l'installazione:** controllare `storage/logs/laravel.log`, `APP_KEY` e i permessi di `storage/`.
- **Database non trovato:** verificare `DB_*`, creare il database e rieseguire `php artisan config:clear`.
- **OpenAI non configurato:** valorizzare `OPENAI_API_KEY` sul server e rieseguire `php artisan config:cache`.
- **Pagina iniziale del server invece dell'app:** il document root non punta a `public/`.
- **Email non ricevuta:** verificare che `MAIL_MAILER=smtp`, ricreare la cache di configurazione e controllare `storage/logs/laravel.log`.
- **Connessione IMAP fallita:** controllare host, porta, cifratura, credenziali e certificato con `php artisan mail:sync --test`.
- **Risposta non visibile nel lead:** controllare la pagina **Email da associare**. Se il messaggio non contiene riferimenti alla conversazione e il mittente non è già noto, richiede una verifica manuale.
- **Webhook 401/403:** verificare il token dell'endpoint e che il dominio sorgente sia tra quelli consentiti.
