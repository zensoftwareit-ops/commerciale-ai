# Installazione sito WordPress Commerciale AI

Per l'installazione completa senza Docker seguire prima
[`INSTALL-NO-DOCKER.md`](INSTALL-NO-DOCKER.md). Gli ZIP descritti sotto servono
solo quando WordPress è già installato sull'hosting.

## Prerequisiti

- WordPress 6.5 o successivo;
- PHP 8.1 o successivo e HTTPS attivo;
- backend Commerciale AI raggiungibile via HTTPS;
- account Stripe con Customer Portal abilitato;
- tre Price Stripe ricorrenti annuali, uno per pacchetto.

## 1. Installare tema e plugin

Da WordPress caricare i tre archivi presenti in `wordpress/dist`:

1. **Aspetto > Temi > Aggiungi nuovo**: installare e attivare
   `commerciale-ai-theme.zip`;
2. **Plugin > Aggiungi plugin > Carica plugin**: installare e attivare
   `commerciale-ai-client.zip`;
3. dalla stessa sezione installare e attivare `commerciale-ai-forms.zip`.

Il plugin crea automaticamente le pagine `/prezzi/` e `/area-cliente/`. La home
del tema usa direttamente il listino del plugin e non richiede Elementor o altri
page builder.

Il plugin Forms sostituisce automaticamente il contatto email statico con un
modulo commerciale. Le richieste restano disponibili in **Richieste sito** anche
se la notifica email non viene consegnata. Configurare destinatario, conservazione
ed evento GA4 da **Richieste sito > Impostazioni**.

## 2. Configurare il backend

Creare in Stripe tre prezzi ricorrenti annuali in EUR: Starter €490,
Professional €990 e Business €1.790. Copiare i rispettivi Price ID nel `.env` del
backend:

Nel `.env`:

```ini
BILLING_SELF_SERVICE_ENABLED=true
BILLING_INTEGRATION_KEY=SEGRETO_CASUALE_DI_ALMENO_32_BYTE
STRIPE_PRICE_STARTER=price_...
STRIPE_PRICE_PROFESSIONAL=price_...
STRIPE_PRICE_BUSINESS=price_...
```

Eseguire quindi `php artisan optimize:clear`,
`php artisan db:seed --class=LicensePlanSeeder --force` e
`php artisan config:cache`.

## 3. Configurare il plugin

Aprire **Impostazioni > Commerciale AI** e compilare:

- URL API Commerciale AI: URL base del backend, senza `/api` finale;
- chiave integrazione billing: valore di `BILLING_INTEGRATION_KEY`;
- Stripe Secret Key: inizialmente una `sk_test_…`;
- Stripe Webhook Secret: valore `whsec_…` dell'endpoint;
- URL accesso software: pagina login del backend.

Lasciare abilitata la raccolta dell'identificativo fiscale se si vende ad aziende.
L'indirizzo di fatturazione è sempre richiesto. Abilitare Stripe Tax soltanto dopo
aver completato la registrazione e la configurazione fiscale nell'account Stripe.

Le password già salvate non vengono ristampate e un campo lasciato vuoto non le
cancella. Premere **Testa API e Stripe**: il controllo deve confermare tutti e tre
i prezzi, inclusi importo, valuta e ricorrenza annuale.

## 4. Configurare Stripe

In Stripe Workbench creare un webhook con l'URL mostrato nelle impostazioni del
plugin. Selezionare:

- `checkout.session.completed`;
- `customer.subscription.created`;
- `customer.subscription.updated`;
- `customer.subscription.deleted`.

Configurare il Customer Portal consentendo almeno consultazione fatture, modifica
metodo di pagamento e cancellazione a fine periodo.

## 5. Collaudo obbligatorio

In modalità test:

1. registrare un nuovo account dal sito;
2. acquistare ciascun piano con una carta test Stripe;
3. verificare la comparsa della licenza nell'area cliente e nel backend;
4. reinviare lo stesso webhook e verificare che non nascano licenze duplicate;
5. simulare `past_due`, ritorno ad `active` e cancellazione;
6. aprire Customer Portal e verificare il rientro nell'area cliente;
7. verificare email di impostazione password del backend;
8. solo dopo il collaudo sostituire le chiavi test con quelle live.

`LICENSE_ENFORCEMENT_ENABLED=true` va attivato solo quando tutte le organizzazioni
che devono restare operative dispongono di una licenza valida.
