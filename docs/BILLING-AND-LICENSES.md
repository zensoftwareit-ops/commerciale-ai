# Clienti, licenze e futura vendita self-service

Il progetto è diviso in due fasi indipendenti. La prima è già utilizzabile senza
WordPress, Stripe o API di billing. La seconda rimane disattivata fino alla messa
in vendita pubblica.

## Step 1 — attivazione manuale dal pannello Super Admin

Il Super Admin configura fino a tre pacchetti e registra personalmente ogni nuovo
cliente. Dal modulo **Registra un nuovo cliente** vengono creati in una sola
operazione:

1. l'account dell'utente owner;
2. l'organizzazione del cliente;
3. pipeline e impostazioni iniziali;
4. la licenza annuale del pacchetto selezionato;
5. l'email con il link per impostare la password.

Se l'invio dell'email non riesce, il cliente può usare **Password dimenticata**
nella pagina di login dopo che la configurazione SMTP è stata verificata.

Per clienti già presenti è disponibile una sezione secondaria che assegna una
nuova licenza a un'organizzazione e al relativo owner esistenti.

### Attivazione dello Step 1

Nel `.env` mantenere:

```ini
BILLING_SELF_SERVICE_ENABLED=false
BILLING_INTEGRATION_KEY=
LICENSE_ENFORCEMENT_ENABLED=false
```

Poi eseguire:

```bash
php artisan migrate --force
php artisan admin:grant email@azienda.it
php artisan optimize:clear
php artisan config:cache
```

Accedere a `/admin/licensing`, creare i pacchetti e usare il modulo di registrazione
cliente. `LICENSE_ENFORCEMENT_ENABLED` può restare `false` durante il pilota; dovrà
essere portato a `true` soltanto quando ogni organizzazione da mantenere operativa
avrà una licenza valida.

Per ogni pacchetto sono configurabili prezzo annuale, utenti inclusi (owner
compreso), limite mensile lead, budget mensile token AI e funzioni disponibili.
Lo Stripe Price ID può restare vuoto nello Step 1.

## Step 2 — vendita self-service WordPress e Stripe

Questa fase comprenderà registrazione e area cliente WordPress, scelta del
pacchetto, Stripe Checkout, Customer Portal, cancellazione dell'abbonamento e
provisioning automatico della licenza. Solo l'owner acquistante accederà all'area
cliente WordPress; gli eventuali sottoutenti inclusi nel pacchetto resteranno legati
alla sua organizzazione e verranno gestiti nel software.

Le API `/api/v1/billing/*` sono oggi nascoste con risposta HTTP 404. Quando lo Step 2
sarà pronto, si potranno esporre impostando una chiave condivisa robusta e attivando
esplicitamente il relativo interruttore:

```ini
BILLING_SELF_SERVICE_ENABLED=true
BILLING_INTEGRATION_KEY=SEGRETO_CASUALE_DI_ALMENO_32_BYTE
```

Il webhook Stripe firmato arriverà al plugin WordPress, che riconcilierà il pagamento
con il software tramite API idempotenti. Stripe sarà la fonte dello stato economico;
Daria conserverà licenze, organizzazioni, owner, limiti e cronologia degli
eventi.

Gli stati `active` e `trialing` consentono l'uso. Gli stati `past_due`, `unpaid`,
`canceled`, `paused` e `suspended` non rendono utilizzabile una licenza quando
l'enforcement è attivo.

## Controllo dei consumi

Il conteggio dei posti comprende l'owner. I limiti mensili di lead e token AI
ripartono all'inizio del mese. Il costo effettivo delle API OpenAI va monitorato e
deve essere incorporato nel prezzo e nei margini dei tre pacchetti prima del lancio
commerciale.
