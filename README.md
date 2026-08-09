# Commerciale AI — Sprint 2

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

## Analisi AI

`LeadAnalyzer` è indipendente dal provider. In locale viene usato un provider fake deterministico; ogni risultato viene validato, combinato con regole dichiarate e registrato con metadati, utilizzi e costi. Nessuna chiamata AI reale viene eseguita finché non viene configurato un adapter specifico.

## Test e qualità

```bash
php artisan test
vendor/bin/pint --test
```

Vedi [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) e [docs/SPRINT-2.md](docs/SPRINT-2.md).
