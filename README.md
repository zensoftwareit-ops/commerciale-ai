# Daria — Pilot

Daria è l'assistente AI per le PMI che comprende, qualifica e prepara la risposta ai lead in ingresso. L'applicazione PHP/Laravel multi-tenant include autenticazione, lead inbox, ricezione adattiva dei payload, profilo aziendale, knowledge base, analisi strutturata, risposte email e sincronizzazione IMAP.

Il proprietario dell'istanza può eliminare definitivamente un lead dalla relativa scheda, insieme a tutti i dati collegati. L'operazione richiede la conferma testuale `ELIMINA` e non è disponibile agli utenti sales.

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
- [Esercizio del pilota su Plesk](docs/PILOT-OPERATIONS.md): deploy, 2FA, cron, salute, backup e ripristino.

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

Il seed crea un account dimostrativo esclusivamente locale. Prima di esporre
l'applicazione, impostare una password univoca e robusta dalla pagina **Account**;
non pubblicare credenziali nel repository.

## Processi in background

Code e cache usano il database. Le caselle IMAP si configurano dal pannello **Caselle email**. In produzione eseguire direttamente il comando unico di automazione tramite cron:

```bash
php artisan commerciale:run
```

Il comando elabora nuovi lead, posta IMAP e conversazioni senza passare dallo
scheduler Laravel. Un lock impedisce esecuzioni sovrapposte.

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

Con una casella IMAP attiva, le risposte riconosciute vengono importate, mostrate nella scheda del lead e contrassegnate come lette. Le credenziali sono cifrate nel database e ogni casella può accedere esclusivamente ai lead della propria organizzazione.

Durante il pilota l'invio usa un unico SMTP transazionale configurato sul server. Il
trasporto SMTP di Resend è già predisposto ma resta disattivato fino alla futura fase
SaaS, quando verrà aggiunto anche l'onboarding automatico SPF/DKIM per dominio.
Indirizzo e nome `From` sono configurati da ciascun utente nella propria pagina
**Account** e non sono presenti nel `.env`; SMTP e Resend sono soltanto trasporti globali.

Il modulo commerciale include un pannello Super Admin per registrare manualmente
clienti, owner e licenze su tre pacchetti. La vendita self-service con tema e plugin
WordPress, Stripe Checkout e Customer Portal è disponibile come modalità separata
e resta disattivata finché non viene configurata. Vedi [clienti e
licenze](docs/BILLING-AND-LICENSES.md) e [installazione
WordPress](wordpress/INSTALLATION.md).

Dal pannello Super Admin le licenze manuali possono essere rinnovate, sospese,
riattivate o eliminate; l'email di attivazione può essere reinviata. La cancellazione
definitiva di un cliente richiede la conferma testuale e rimuove l'intero tenant.

L'amministratore della piattaforma usa un account dedicato, creato con
`php artisan platform-admin:create admin@azienda.it`: non appartiene a nessuna
organizzazione cliente e non utilizza lead, caselle email o licenze.
Dal relativo pannello **Account** configura inoltre l'identità transazionale di
Daria, separata dal proprio indirizzo personale e usata per inviti, attivazioni
e recupero password.

Il pannello Super Admin dispone inoltre di autenticazione TOTP a due fattori,
registro delle operazioni e pagina **Salute**. Quest'ultima controlla cron, email,
OpenAI, IMAP, licenze, backup e configurazione essenziale del server.

Il listino strutturato genera fasce di preventivo deterministiche e versionate. L'AI può presentare la fascia ma non modificarla; quando mancano dati prepara al massimo due domande mirate. L'automazione via cron e l'invio automatico sono disattivati per impostazione predefinita; durante il collaudo possono operare esclusivamente sugli indirizzi interni autorizzati e solo se non esiste alcun blocco di sicurezza. Il numero massimo di interventi automatici per lead impedisce conversazioni senza fine.

Il flusso iniziale può essere automatizzato integralmente: i nuovi lead vengono analizzati, ricevono una bozza iniziale e, se autorizzati dalla modalità interna, la prima email viene inviata dal cron. L'attivazione non coinvolge lead storici e ogni errore tecnico è limitato a tre tentativi.

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
