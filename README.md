# Commerciale AI — Sprint 1

Fondamenta multi-tenant e lead inbox per il pilot Zen Software. Include autenticazione e recupero password, ruoli minimi, lead manuali, webhook firmato/idempotente, timeline, provider AI fake e dati demo.

## Requisiti

- PHP 8.3+ con PDO PostgreSQL (oppure PDO SQLite per i test)
- Composer 2
- PostgreSQL 16+
- Redis 7+
- opzionale: Docker Compose

## Avvio locale

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Aprire `http://localhost:8000`. Credenziali demo (solo seed locale):

- email: `demo@commerciale-ai.test`
- password: `CommercialeAI!2026`

Per test rapidi senza PostgreSQL, impostare `DB_CONNECTION=sqlite` e creare `database/database.sqlite`.

## Webhook lead

`POST /api/v1/inbound/leads` con header:

```text
X-Webhook-Source: preventivositoweb-demo
X-Webhook-Timestamp: <unix timestamp>
X-Webhook-Signature: <hex HMAC-SHA256(timestamp + "." + raw body)>
Idempotency-Key: <identificatore univoco evento>
Content-Type: application/json
```

Il segreto demo è `change-me-in-local-env` e deve essere sostituito prima di qualunque integrazione. La finestra anti-replay è 5 minuti.

## Test e qualità

```bash
php artisan test
vendor/bin/pint --test
```

La suite usa SQLite in memoria e non chiama servizi AI o email reali.

## Docker

`docker compose up --build` prepara app, PostgreSQL e Redis. Al primo avvio eseguire `docker compose exec app php artisan migrate --seed`. La macchina su cui è stato creato lo Sprint 1 non disponeva di Docker, quindi la build Compose è documentata ma non è stata eseguita localmente.

Vedi [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) per decisioni, rischi e threat model.
