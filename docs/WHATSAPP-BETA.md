# WhatsApp Daria — beta

La beta collega a ogni organizzazione un numero WhatsApp Business registrato sulla Cloud API ufficiale di Meta. Per il primo collaudo è raccomandato un numero dedicato, distinto dai numeri personali e già operativi.

## Perimetro della beta

- ricezione di messaggi testuali tramite webhook Meta firmato;
- deduplicazione usando il `wamid` del messaggio;
- associazione a un lead esistente tramite numero telefonico o creazione di un nuovo lead;
- analisi e generazione della risposta con lo stesso motore commerciale delle email;
- bozza visibile nella scheda del lead;
- invio manuale oppure automatico verso una lista di numeri interni autorizzati;
- aggiornamento degli stati di consegna ricevuti dal webhook;
- passaggio al commerciale per contenuti non supportati o conversazioni non affidabili.

Non sono ancora compresi l'Embedded Signup self-service, i template Meta per iniziare conversazioni, allegati, audio e WhatsApp Flows. La beta deve quindi essere collaudata con conversazioni iniziate dal telefono del tester.

## Configurazione globale del server

Generare un token casuale lungo per la verifica del webhook e recuperare l'App Secret dalla Meta App:

```ini
WHATSAPP_GRAPH_URL=https://graph.facebook.com
WHATSAPP_GRAPH_VERSION=v23.0
WHATSAPP_WEBHOOK_VERIFY_TOKEN=UN_TOKEN_CASUALE_LUNGO
WHATSAPP_APP_SECRET=APP_SECRET_META
WHATSAPP_API_TIMEOUT=20
WHATSAPP_BETA_EXTERNAL_SEND_ENABLED=false
```

Dopo la modifica:

```bash
/opt/plesk/php/8.3/bin/php artisan optimize:clear
```

## Configurazione Meta

1. Creare una Meta App di tipo Business e aggiungere WhatsApp.
2. Collegare un Business Portfolio, creare o selezionare il WABA e registrare un numero.
3. Creare un token di sistema con `whatsapp_business_management` e `whatsapp_business_messaging`.
4. In Webhooks impostare come callback `https://app.daria-ai.it/api/v1/whatsapp/webhook` e usare lo stesso valore di `WHATSAPP_WEBHOOK_VERIFY_TOKEN`.
5. Sottoscrivere il campo `messages` per il WABA.
6. In Daria aprire **WhatsApp (beta)** e inserire WABA ID, Phone Number ID, numero visualizzato e access token.
7. Aggiungere il numero del tester alla whitelist, attivare account e risposte automatiche, quindi usare **Verifica connessione**.

Il token di accesso viene cifrato nel database tramite `APP_KEY`. App Secret e verify token restano globali nel file `.env` perché appartengono alla Meta App della piattaforma.

## Elaborazione

La cron esistente è sufficiente:

```bash
/opt/plesk/php/8.3/bin/php artisan commerciale:run
```

Per verificare soltanto WhatsApp:

```bash
/opt/plesk/php/8.3/bin/php artisan whatsapp:process
/opt/plesk/php/8.3/bin/php artisan conversations:automate
```

Il primo comando trasforma i webhook in lead e bozze; il secondo invia le bozze autorizzate. `commerciale:run` esegue entrambi nello stesso ciclo.

## Sicurezza del collaudo

Lasciare `WHATSAPP_BETA_EXTERNAL_SEND_ENABLED=false`. In questa modalità vengono inviati messaggi automatici esclusivamente ai numeri presenti nella whitelist dell'organizzazione, anche se la casella “Limita la beta ai numeri interni” viene disattivata accidentalmente.
