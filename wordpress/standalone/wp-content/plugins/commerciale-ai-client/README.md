# Commerciale AI Client Area

Plugin WordPress per registrazione clienti, listino dinamico, Stripe Checkout
annuale, Customer Portal e sincronizzazione idempotente delle licenze con il
backend Commerciale AI.

## Flusso applicativo

1. il visitatore crea un account WordPress;
2. sceglie un piano restituito dalle API Commerciale AI;
3. il plugin apre Stripe Checkout senza trattare dati carta;
4. un webhook Stripe firmato comunica lo stato dell'abbonamento al backend;
5. il backend crea o aggiorna owner, organizzazione e licenza;
6. l'area cliente mostra piano, stato, rinnovo e accesso al software.

Stripe rimane la fonte dello stato economico. La licenza non viene mai attivata
dal ritorno del browser dopo Checkout, ma esclusivamente da un webhook verificato.

## Installazione

1. usare lo ZIP prodotto in `wordpress/dist`;
2. installare il plugin da **Plugin > Aggiungi plugin > Carica plugin**;
3. attivarlo: vengono create automaticamente le pagine **Prezzi** e
   **Area cliente**;
4. aprire **Impostazioni > Commerciale AI**;
5. inserire URL del backend, `BILLING_INTEGRATION_KEY`, chiave segreta Stripe,
   signing secret del webhook e URL del login al software;
6. usare **Testa API e Stripe** prima di pubblicare il listino.

## Configurazione Stripe

Creare in Stripe un Price ricorrente annuale per ciascun pacchetto. Inserire il
relativo `price_…` nel piano corrispondente del pannello licenze Commerciale AI.

Creare quindi un endpoint webhook verso l'URL mostrato nella pagina impostazioni
del plugin e abilitare:

- `checkout.session.completed`;
- `customer.subscription.created`;
- `customer.subscription.updated`;
- `customer.subscription.deleted`.

Attivare e configurare anche lo Stripe Customer Portal, che gestisce metodi di
pagamento, fatture, rinnovi e cancellazione.

## Configurazione backend

Nel `.env` del backend:

```ini
BILLING_SELF_SERVICE_ENABLED=true
BILLING_INTEGRATION_KEY=SEGRETO_CASUALE_DI_ALMENO_32_BYTE
LICENSE_ENFORCEMENT_ENABLED=true
```

La chiave deve coincidere con quella configurata nel plugin. Prima di attivare
l'enforcement verificare che tutte le organizzazioni da mantenere operative
abbiano una licenza valida.

## Shortcode

- `[commerciale_ai_pricing]` mostra i piani e avvia Checkout;
- `[commerciale_ai_account]` mostra registrazione, login e area cliente.

Gli shortcode funzionano con qualunque tema; il tema `commerciale-ai-theme`
fornisce l'integrazione grafica completa.

## Sicurezza e affidabilità

- nonce su tutte le azioni browser;
- verifica HMAC del webhook con tolleranza di 5 minuti;
- chiamate di provisioning idempotenti tramite Stripe Event ID;
- chiavi segrete mai ristampate nel pannello;
- Idempotency-Key sulla creazione della Checkout Session;
- fallback di riconciliazione tramite subscription ID e customer ID;
- nessun dato carta memorizzato in WordPress.
