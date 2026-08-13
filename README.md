# Commerciale AI — Pilot

Applicazione PHP/Laravel multi-tenant per acquisire, qualificare e lavorare lead commerciali. Include autenticazione, lead inbox, ricezione adattiva dei payload, profilo aziendale, knowledge base, analisi strutturata, risposte email con approvazione umana e sincronizzazione IMAP.

## Requisiti

- PHP 8.3 o superiore
- Composer 2
- estensioni PHP: `mbstring`, `openssl`, `pdo`, `pdo_sqlite`
- SQLite per sviluppo; PostgreSQL è supportato per la produzione

L’installazione richiede soltanto PHP e Composer; code, cache e database di sviluppo usano SQLite.

## Guide

- [Installazione base](docs/INSTALLATION.md): ambiente locale, server, database, HTTPS, SMTP e aggiornamenti.
- [Istanza pilota preventivositoweb.it](docs/INSTANCE-PREVENTIVOSITOWEB.md): configurazione aziendale, knowledge base, webhook e collaudo.
- [Checklist pilota sintetica](docs/PILOT.md).

## Installazione locale rapida

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

Code e cache usano il database. La sincronizzazione IMAP viene programmata ogni cinque minuti quando `IMAP_ENABLED=true`. In produzione avviare lo scheduler Laravel:

```bash
php artisan schedule:work
```

In produzione configurare questi comandi come servizi di sistema e impostare il document root della virtual host sulla directory `public/`.

## Ricezione lead semplificata

Dal pannello **Sorgenti** si configurano nome e domini consentiti. L’applicazione genera un endpoint segreto dedicato:

```text
POST /api/v1/inbound/leads/<token-segreto>
```

Il sito invia direttamente il proprio oggetto JSON o form, senza mapping, firma o header personalizzati. Il normalizzatore riconosce automaticamente i campi più comuni, conserva i dati commerciali non riconosciuti e genera l’idempotenza internamente.

Il token nell’URL autentica la sorgente. Se sono presenti `Origin`, `Referer` o URL sorgente nel payload, il dominio viene confrontato con la allowlist salvata nel database. L’endpoint va usato esclusivamente dal backend e trattato come una password.

## Analisi AI con OpenAI

L'applicazione usa la Responses API con Structured Outputs. La chiave resta esclusivamente nel file `.env` del server:

```dotenv
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-5.6-terra
```

Dopo una modifica al file `.env`, eseguire `php artisan config:clear`. Per test e sviluppo offline è possibile impostare `AI_PROVIDER=fake`.

Ogni risultato viene validato, combinato con regole dichiarate e registrato con modello, utilizzi e costo stimato. I contenuti del lead sono trattati come dati non attendibili.

Dopo l'analisi viene generata una bozza email modificabile. Un operatore deve salvarla, approvarla e inviarla; l'applicazione non invia autonomamente messaggi al cliente. È possibile associare una data di follow-up, registrata come prossima azione e nella timeline del lead.

Con IMAP attivo, le risposte riconosciute vengono importate, mostrate nella scheda del lead e contrassegnate come lette nella casella. Il follow-up pendente viene annullato e viene preparata una nuova bozza da approvare. Un riferimento certo alla conversazione consente l'associazione anche da un indirizzo diverso, mostrando un avviso all'operatore. I messaggi incerti entrano nella pagina **Email da associare** e non vengono mai collegati automaticamente; dopo la verifica è possibile associarli a un lead e memorizzare il mittente come contatto secondario.

## Preparazione del pilota

Dopo il primo accesso:

1. cambiare la password demo dalla pagina **Account**;
2. completare **Azienda** e aggiungere almeno un documento alla **Knowledge base**;
3. aprire **Sorgenti**, inserire i domini consentiti e generare l’endpoint dedicato;
4. configurare SMTP per gli invii reali;
5. inviare un lead, aprirlo dalla inbox e selezionare **Analizza lead**;
6. controllare la bozza, salvarla e usare **Approva e invia**.

La inbox mostra una checklist di prontezza. La guida completa è in [docs/PILOT.md](docs/PILOT.md).

## Test e qualità

```bash
php artisan test
vendor/bin/pint --test
```

Vedi [docs/INSTALLATION.md](docs/INSTALLATION.md), [docs/INSTANCE-PREVENTIVOSITOWEB.md](docs/INSTANCE-PREVENTIVOSITOWEB.md), [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) e [docs/SPRINT-2.md](docs/SPRINT-2.md).
