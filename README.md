# Commerciale AI — Pilot

Applicazione PHP/Laravel multi-tenant per acquisire, qualificare e lavorare lead commerciali. Include autenticazione, lead inbox, webhook firmato, profilo aziendale, knowledge base e analisi strutturata con scoring misto AI/regole.

## Requisiti

- PHP 8.3 o superiore
- Composer 2
- estensioni PHP: `mbstring`, `openssl`, `pdo`, `pdo_sqlite`
- SQLite per sviluppo; PostgreSQL è supportato per la produzione

L’installazione richiede soltanto PHP e Composer; code, cache e database di sviluppo usano SQLite.

## Installazione locale

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Creare il database SQLite vuoto:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
php artisan serve
```

In alternativa, il bootstrap completo è disponibile come singolo comando:

```bash
composer run setup
```

Aprire `http://localhost:8000`.

Credenziali demo, esclusivamente locali:

- email: `demo@commerciale-ai.test`
- password: `CommercialeAI!2026`

## Processi in background

Code e cache usano il database. Quando verranno introdotti job programmati, avviare:

```bash
php artisan queue:work --tries=3
php artisan schedule:work
```

In produzione configurare questi comandi come servizi di sistema e impostare il document root della virtual host sulla directory `public/`.

## Webhook lead

`POST /api/v1/inbound/leads` richiede:

```text
X-Webhook-Source: preventivositoweb-demo
X-Webhook-Timestamp: <unix timestamp>
X-Webhook-Signature: <hex HMAC-SHA256(timestamp + "." + raw body)>
Idempotency-Key: <identificatore univoco evento>
Content-Type: application/json
```

Il segreto del seed è dimostrativo e deve essere sostituito prima dell’integrazione reale.

## Analisi AI con OpenAI

L'applicazione usa la Responses API con Structured Outputs. La chiave resta esclusivamente nel file `.env` del server:

```dotenv
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-5.6-terra
```

Dopo una modifica al file `.env`, eseguire `php artisan config:clear`. Per test e sviluppo offline è possibile impostare `AI_PROVIDER=fake`.

Ogni risultato viene validato, combinato con regole dichiarate e registrato con modello, utilizzi e costo stimato. Nomi e recapiti del contatto non vengono inviati al provider; i contenuti del lead sono trattati come dati non attendibili.

## Preparazione del pilota

Dopo il primo accesso:

1. cambiare la password demo dalla pagina **Account**;
2. completare **Azienda** e aggiungere almeno un documento alla **Knowledge base**;
3. aprire **Sorgenti**, ruotare il segreto demo oppure creare una sorgente nuova;
4. inviare un lead, aprirlo dalla inbox e selezionare **Analizza lead**.

La inbox mostra una checklist di prontezza. La guida completa è in [docs/PILOT.md](docs/PILOT.md).

## Test e qualità

```bash
php artisan test
vendor/bin/pint --test
```

Vedi [docs/PILOT.md](docs/PILOT.md), [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) e [docs/SPRINT-2.md](docs/SPRINT-2.md).
