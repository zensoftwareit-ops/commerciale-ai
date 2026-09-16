# Preventivi PDF

Daria genera un PDF privato quando una risposta è classificata come preventivo. Le semplici richieste di informazioni e le domande di qualificazione non producono allegati.

## Configurazione cliente

Il prodotto usa un unico modello commerciale classico, ispirato alla carta intestata del preventivo di riferimento: riga colorata, logo a sinistra, contatti a destra, contenuto lineare, offerta in grassetto, condizioni e spazio firma. In **Azienda e AI > Preventivi PDF** l'owner può impostare:

- logo aziendale JPEG, massimo 2 MB;
- colore principale;
- intestazione superiore su più righe;
- formula introduttiva con i segnaposto `{{cliente}}` e `{{azienda_cliente}}`;
- tre campi indipendenti del piè di pagina;
- testo dello spazio di accettazione;
- dati societari e fiscali;
- condizioni economiche e di pagamento;
- nota finale facoltativa.

Il listino definisce i limiti economici autorizzati. Le regole semplici possono ancora usare una fascia minima/massima. Le regole evolute contengono invece una **ricetta universale di prezzo**: variabili lette dal lead, unità e conversioni, importi fissi, quantità per prezzo unitario, scaglioni, opzioni selezionabili, condizioni, maggiorazioni e sconti percentuali. Una variabile può anche rappresentare una distanza stradale, con origine configurata e scelta fra sola andata o andata/ritorno.

Lo stesso motore può quindi comporre, per esempio, un noleggio a giorni con trasporto e accessori oppure una vendita a kg/quintali con imballaggio e sconto quantità. OpenAI traduce documenti e spiegazioni nella ricetta; prima del salvataggio la struttura viene validata. Durante il preventivo il calcolo è eseguito dal software, non dal modello, e nel PDF vengono riportati i singoli passaggi e subtotali. Se manca una variabile obbligatoria o nessuno scaglione è applicabile, il preventivo non viene inventato e passa alla verifica umana.

Il calcolo stradale usa openrouteservice e richiede i parametri globali `OPENROUTESERVICE_API_KEY`, `OPENROUTESERVICE_API_URL` e `OPENROUTESERVICE_TIMEOUT`. Le ricette specifiche di ciascun cliente rimangono nel database e si configurano in **Azienda e AI > Listino strutturato**; non vengono inserite nel file `.env` e non richiedono codice personalizzato per cliente.

## Numerazione e archiviazione

Ogni organizzazione ha una numerazione annuale indipendente nel formato `OFF-2026-00001`. Il file viene archiviato nel disco Laravel `local`, quindi non è pubblicamente accessibile. Il download passa da una rotta autenticata e soggetta all'isolamento tenant.

Il PDF viene generato insieme alla bozza del preventivo e poi riutilizzato per download e invio. Eliminando definitivamente il lead vengono eliminati anche i relativi file PDF.

## Invio

Le email con tipo `quotation` o `initial_quotation` includono automaticamente il PDF. Le email di qualificazione non lo allegano. La beta WhatsApp continua a comunicare la fascia nel testo e consente il download del documento dalla scheda lead, ma non invia ancora documenti tramite WhatsApp.

Non sono state aggiunte dipendenze Composer: il generatore è compatibile con PHP 8.3 e produce PDF 1.4.
