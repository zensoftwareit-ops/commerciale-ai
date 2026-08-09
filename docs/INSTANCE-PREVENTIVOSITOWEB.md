# Configurazione pilota: preventivositoweb.it

Questa guida parte da un'installazione completata seguendo [INSTALLATION.md](INSTALLATION.md). L'obiettivo è ricevere in Commerciale AI le richieste generate dal questionario di `preventivositoweb.it` e qualificarle usando le informazioni commerciali del sito.

I testi suggeriti sono stati ricavati dalla pagina pubblica [preventivositoweb.it](https://preventivositoweb.it/) il 9 agosto 2026. Ragione sociale, criteri di esclusione e condizioni commerciali devono essere verificati dal titolare prima dell'uso reale.

## 1. Dominio e configurazione server

Si consiglia un sottodominio dedicato, per esempio:

```text
https://commerciale.preventivositoweb.it
```

Il sito pubblico e l'applicazione commerciale restano così separati. Nel `.env` dell'istanza:

```dotenv
APP_NAME="Commerciale AI - PreventivoSitoWeb.it"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://commerciale.preventivositoweb.it

APP_LOCALE=it
APP_FALLBACK_LOCALE=it
APP_FAKER_LOCALE=it_IT

SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

AI_PROVIDER=openai
OPENAI_API_KEY=CHIAVE_CONFIGURATA_SOLO_SUL_SERVER
OPENAI_MODEL=gpt-5.6-terra
```

Se viene scelto un dominio diverso, sostituirlo in tutta la guida. Dopo ogni modifica:

```bash
php artisan optimize:clear
php artisan config:cache
```

## 2. Primo accesso

1. Accedere con l'utente demo.
2. Aprire **Account** e impostare una password nuova di almeno 12 caratteri.
3. Non condividere l'account: per ora sarà l'account owner del pilota.
4. Verificare che la inbox segnali correttamente lo stato OpenAI.

Prima di aprire l'istanza ad altri utenti sarà opportuno aggiungere dal pannello la gestione utenti; questa funzione non è ancora presente nel pilot corrente.

## 3. Profilo Azienda

Aprire **Azienda** e usare questa bozza iniziale.

| Campo | Valore suggerito |
|---|---|
| Ragione sociale | Inserire la ragione sociale e la partita IVA effettive del soggetto che eroga il servizio |
| Nome commerciale | `preventivositoweb.it` |
| Settore | `Realizzazione di siti web, e-commerce e servizi digitali` |
| Area servita | `Italia` — confermare eventuali limitazioni geografiche |
| Descrizione | `Servizio online che raccoglie i requisiti del cliente e prepara una stima chiara e personalizzata per la realizzazione o il rifacimento di un sito web professionale.` |
| Prodotti e servizi | `Siti vetrina; siti professionali aziendali; e-commerce; blog e news; form avanzati; prenotazioni; aree riservate; multilingua; integrazioni CRM o gestionali; copywriting; immagini; hosting e assistenza.` |
| Cliente ideale | `Liberi professionisti, consulenti, PMI, negozi locali, attività e-commerce, startup e nuovi brand che desiderano una presenza online professionale.` |
| Elementi distintivi | `Preventivo guidato in pochi minuti; prezzi comprensibili; linguaggio non tecnico; supporto umano; soluzione ampliabile nel tempo.` |
| Tono di voce | `Professionale, chiaro, concreto, rassicurante e privo di tecnicismi non necessari.` |
| Tempo di risposta | `1440` minuti, coerente con la promessa pubblica di una prima indicazione entro 24 ore |
| Mittente autorizzato | `info@zensoftware.it`, salvo scelta di una casella dedicata |
| Modalità appuntamento | `Call di approfondimento concordata via email.` |
| Firma | Inserire nome del referente, `preventivositoweb.it`, recapiti e dati aziendali corretti |

Regole prezzo iniziali, da inserire come indicazioni e non come offerta vincolante:

```text
Sito vetrina Start: circa 700–1.200 € + IVA.
Sito professionale Business: circa 1.500–2.500 € + IVA.
E-commerce: circa 2.500–5.000 € + IVA.
I range sono indicativi. Il prezzo finale dipende da pagine, funzioni, contenuti, integrazioni, numero di prodotti e assistenza richiesta.
Non promettere mai un prezzo definitivo senza verifica umana dei requisiti.
```

Domande di qualificazione, una per riga:

```text
Si tratta di un nuovo sito o del rifacimento di un sito esistente?
Qual è l'obiettivo principale del progetto?
Quale tipologia di sito è richiesta?
Quante pagine o sezioni sono previste?
Qual è il budget indicativo?
Qual è la data desiderata per la pubblicazione?
Servono blog, e-commerce, prenotazioni, area riservata o multilingua?
Sono necessarie integrazioni con CRM, gestionali o servizi esterni?
Testi, immagini, dominio e hosting sono già disponibili?
Chi partecipa alla decisione finale?
```

Criteri di esclusione proposti, da confermare:

```text
Richieste illecite, abusive o incompatibili con le policy aziendali.
Assenza del consenso privacy.
Recapiti falsi o non utilizzabili.
Richieste fuori servizio o con tempi/budget manifestamente incompatibili, da sottoporre comunque a verifica umana prima della chiusura.
```

## 4. Knowledge base iniziale

Aprire **Knowledge base** e creare almeno questi documenti con stato **Attivo**:

1. **Servizi e dotazione base** (`service`): responsive, design, pagine principali, form antispam, Maps se necessario, SEO base, implementazione cookie/GDPR di base, social, installazione, formazione e supporto all'avvio.
2. **Fasce di prezzo** (`pricing`): i tre range indicativi e tutte le variabili che possono modificarli.
3. **Questionario e qualificazione** (`text`): opzioni del form, domande aggiuntive e criteri per passare a una call.
4. **FAQ commerciali** (`faq`): assenza di impegno, precisione della stima, aggiornamento autonomo, testi/foto, dominio e hosting.
5. **Limiti e approvazioni** (`text`): ciò che l'AI non può promettere e i casi che richiedono approvazione umana.

Non inserire password, chiavi API, dati personali dei lead o istruzioni tecniche riservate nella knowledge base.

## 5. Creazione della sorgente webhook

1. Aprire **Sorgenti** nell'applicazione.
2. Creare una sorgente chiamata `preventivositoweb.it produzione`.
3. Copiare subito **chiave sorgente** e **segreto HMAC**: il segreto è mostrato una sola volta.
4. Conservare il segreto soltanto nella configurazione server del sito, mai nel JavaScript del browser o nel repository.
5. La sorgente demo `preventivositoweb-demo` non deve essere usata: il suo segreto è pubblico. Può rimanere inutilizzata durante il test; appena sarà disponibile la disattivazione dal pannello andrà disattivata o rimossa.

Endpoint previsto:

```text
POST https://commerciale.preventivositoweb.it/api/v1/inbound/leads
```

## 6. Mappatura del questionario

Payload consigliato:

```json
{
  "external_id": "psw-20260809-000123",
  "source": "preventivositoweb.it",
  "received_at": "2026-08-09T10:30:00+02:00",
  "contact": {
    "name": "Mario Rossi",
    "email": "mario@example.it",
    "phone": "+39 333 1234567",
    "company": "Rossi Srl"
  },
  "request": {
    "project_type": "Sito professionale",
    "project_status": "Rifacimento sito esistente",
    "page_range": "7-12",
    "budget": "2.500-5.000 EUR",
    "primary_goal": "Generare nuovi clienti",
    "current_site_url": "https://example.it",
    "features": ["Blog / News", "Multilingua"],
    "message": "Richiesta pubblicazione entro novembre"
  },
  "consent": {
    "privacy_accepted": true,
    "marketing_accepted": false
  }
}
```

`external_id` deve essere un identificatore stabile e univoco della richiesta. Lo stesso evento ritentato deve mantenere sia `external_id` sia `Idempotency-Key`.

## 7. Invio PHP dal sito

Esempio essenziale da eseguire **sul server** dopo che il form è stato validato e salvato:

```php
<?php

$endpoint = getenv('COMMERCIALE_AI_WEBHOOK_URL');
$sourceKey = getenv('COMMERCIALE_AI_SOURCE_KEY');
$secret = getenv('COMMERCIALE_AI_WEBHOOK_SECRET');
$eventId = 'psw-'.bin2hex(random_bytes(12));

$payload = [
    'external_id' => $eventId,
    'source' => 'preventivositoweb.it',
    'received_at' => date(DATE_ATOM),
    'contact' => [
        'name' => $form['name'],
        'email' => $form['email'],
        'phone' => $form['phone'] ?? null,
        'company' => $form['company'] ?? null,
    ],
    'request' => [
        'project_type' => $form['project_type'],
        'project_status' => $form['project_status'],
        'page_range' => $form['page_range'],
        'budget' => $form['budget'],
        'primary_goal' => $form['primary_goal'],
        'current_site_url' => $form['current_site_url'] ?? null,
        'features' => $form['features'] ?? [],
        'message' => $form['notes'] ?? null,
    ],
    'consent' => [
        'privacy_accepted' => (bool) $form['privacy_accepted'],
        'marketing_accepted' => (bool) ($form['marketing_accepted'] ?? false),
    ],
];

$json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$timestamp = (string) time();
$signature = hash_hmac('sha256', $timestamp.'.'.$json, $secret);

$curl = curl_init($endpoint);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $json,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Webhook-Source: '.$sourceKey,
        'X-Webhook-Timestamp: '.$timestamp,
        'X-Webhook-Signature: '.$signature,
        'Idempotency-Key: '.$eventId,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
]);

$responseBody = curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$error = curl_error($curl);
curl_close($curl);

if ($responseBody === false || ! in_array($status, [200, 201], true)) {
    // Registrare l'errore senza includere il segreto e programmare un nuovo tentativo
    throw new RuntimeException("Invio lead fallito: HTTP {$status} {$error}");
}
```

La firma deve essere calcolata sullo stesso identico `$json` passato a `CURLOPT_POSTFIELDS`. Non ricodificare o formattare il JSON tra firma e invio.

## 8. Collaudo end-to-end

Eseguire il collaudo con dati chiaramente fittizi e consenso privacy selezionato:

1. compilare il questionario pubblico;
2. verificare risposta HTTP `201` alla prima consegna;
3. verificare che il lead compaia nella inbox;
4. controllare che tutti i campi del questionario siano visibili nella scheda;
5. avviare **Analizza lead**;
6. confrontare score, riepilogo e prossima azione con i dati inviati;
7. reinviare lo stesso evento con la stessa `Idempotency-Key` e verificare HTTP `200` senza duplicati;
8. provare una firma errata e verificare HTTP `401`;
9. correggere manualmente un'analisi e verificare la timeline.

## 9. Criteri per iniziare il pilota

Il pilota può ricevere lead reali quando:

- HTTPS è valido sull'app e sul sito;
- password demo sostituita;
- profilo aziendale approvato;
- prezzi e limiti verificati;
- knowledge base attiva;
- chiave OpenAI configurata sul server;
- sorgente di produzione creata con segreto non pubblico;
- webhook collaudato anche in caso di ritentativo;
- informativa privacy aggiornata per descrivere correttamente trattamento e fornitori;
- backup del database disponibile;
- una persona controlla sempre le analisi prima di azioni verso il cliente.

## 10. Funzioni non ancora incluse

Questa configurazione copre acquisizione, deduplicazione, analisi, scoring e lavoro manuale sul lead. Non sono ancora incluse la gestione utenti dal pannello, la disattivazione delle sorgenti, l'invio automatico delle email, le bozze commerciali, gli appuntamenti e la fatturazione/licenza. Durante il pilota le comunicazioni al cliente devono quindi essere inviate manualmente.
