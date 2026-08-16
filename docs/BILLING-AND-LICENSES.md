# Billing, licenze e area cliente WordPress

## Responsabilità

- **Commerciale AI** conserva pacchetti, licenze, owner, organizzazioni, limiti e
  cronologia degli eventi.
- **Stripe** è la fonte dello stato economico dell'abbonamento.
- **WordPress** ospita registrazione, listino, area cliente, Checkout e collegamento
  al Customer Portal; non può generare una licenza senza un webhook Stripe valido.

## Pacchetti

Il Super Admin configura al massimo tre pacchetti. Per ognuno sono disponibili:
prezzo annuale, Stripe Price ID, utenti inclusi (owner compreso), limite lead,
budget token AI, funzioni e stato pubblicabile. Prezzi e limiti non sono codificati
nel plugin: WordPress li legge dall'API.

## Provisioning

Il webhook Stripe firmato arriva al plugin WordPress. Il plugin recupera lo stato
dell'abbonamento e chiama `POST /api/v1/billing/provision` con Bearer token. Ogni
evento usa lo Stripe Event ID come chiave idempotente. Il software:

1. riconcilia il pacchetto dallo Stripe Price ID;
2. crea o collega l'utente owner;
3. crea l'organizzazione e le fasi iniziali;
4. emette o aggiorna la licenza;
5. invia all'owner il link per impostare la password al primo acquisto.

Gli stati `active` e `trialing` consentono l'uso. La cancellazione a fine periodo
mantiene l'accesso fino alla scadenza comunicata da Stripe. `past_due`, `unpaid`,
`canceled`, `paused` e `suspended` non sono licenze utilizzabili.

## Sicurezza

- usare una `BILLING_INTEGRATION_KEY` casuale di almeno 32 byte;
- conservarla soltanto nel `.env` del software e nelle opzioni protette di WordPress;
- non inserire chiavi Stripe nel repository;
- configurare il webhook Stripe con signing secret e tolleranza temporale;
- non attivare `LICENSE_ENFORCEMENT_ENABLED` finché pilota e licenze manuali non sono
  stati verificati;
- ruotare la chiave di integrazione se il sito WordPress viene compromesso.

## Sottoutenti

Solo l'owner acquistante accede all'area cliente WordPress. Nel software può aprire
**Utenti** e aggiungere commerciali o viewer. Tutte le membership appartengono alla
stessa organizzazione e il conteggio comprende l'owner. L'owner della licenza non può
essere rimosso da un sottoutente.

Con enforcement attivo vengono applicati anche il limite mensile di nuovi lead e il
budget mensile di token AI. I conteggi ripartono all'inizio del mese nel fuso orario
dell'applicazione.

## Attivazione

Sul software:

```ini
BILLING_INTEGRATION_KEY=SEGRETO_CASUALE_CONDIVISO_CON_WORDPRESS
LICENSE_ENFORCEMENT_ENABLED=false
```

Migrare il database e concedere il Super Admin a un utente esistente:

```bash
php artisan migrate --force
php artisan admin:grant email@azienda.it
```

Accedere a `/admin/licensing`, configurare i tre pacchetti e copiare per ciascuno lo
Stripe Price ID annuale. Installare poi `wordpress/commerciale-ai-client` come plugin
ZIP e seguire il relativo README.

Abilitare `LICENSE_ENFORCEMENT_ENABLED=true` soltanto quando ogni organizzazione che
deve restare operativa possiede una licenza attiva.

