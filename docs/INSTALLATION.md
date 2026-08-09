# Guida base di installazione

Questa guida installa una singola istanza di Commerciale AI senza Docker. La stessa applicazione è multi-tenant, ma per il progetto pilota si utilizza una sola organizzazione.

## 1. Requisiti

- PHP 8.3 o superiore;
- Composer 2;
- estensioni PHP `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pdo`, `session`, `tokenizer` e `xml`;
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

Aprire `http://127.0.0.1:8000`. Le credenziali iniziali create dal seed sono:

```text
Email: demo@commerciale-ai.test
Password: CommercialeAI!2026
```

Cambiare subito la password dalla pagina **Account**. Queste credenziali sono pubbliche e servono esclusivamente al primo accesso.

## 3. Installazione su server

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
APP_NAME="Commerciale AI"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMINIO-DELL-APP

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

Senza SMTP il recupero password viene scritto nei log e non arriva all'utente. Per abilitarlo:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=SMTP_HOST
MAIL_PORT=587
MAIL_USERNAME=SMTP_USERNAME
MAIL_PASSWORD=SMTP_PASSWORD
MAIL_FROM_ADDRESS=EMAIL_MITTENTE
MAIL_FROM_NAME="Commerciale AI"
```

Verificare la configurazione con un reset password di prova. Non inserire credenziali SMTP nel repository.

## 6. Aggiornamenti

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

## 7. Controlli finali

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
- creazione e rotazione di una sorgente webhook;
- reset password via email, se SMTP è attivo.

Il worker delle code non è indispensabile per i flussi attuali. Quando saranno introdotti invii o elaborazioni asincrone, avviare stabilmente `php artisan queue:work --tries=3` tramite il sistema di process management del server.

## 8. Problemi frequenti

- **Errore 500 dopo l'installazione:** controllare `storage/logs/laravel.log`, `APP_KEY` e i permessi di `storage/`.
- **Database non trovato:** verificare `DB_*`, creare il database e rieseguire `php artisan config:clear`.
- **OpenAI non configurato:** valorizzare `OPENAI_API_KEY` sul server e rieseguire `php artisan config:cache`.
- **Pagina iniziale del server invece dell'app:** il document root non punta a `public/`.
- **Webhook 401:** verificare timestamp, segreto e firma calcolata sugli stessi identici byte del JSON inviato.
