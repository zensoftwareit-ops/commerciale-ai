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

Il listino definisce i limiti economici autorizzati. Le regole semplici possono ancora usare una fascia minima/massima e un livello di complessità. Per noleggi e servizi misurabili sono disponibili formule deterministiche: scaglioni di tariffa giornaliera (`1-3: 550`, `4-8: 500`), località di partenza, costo chilometrico e scelta fra sola andata o andata/ritorno. Daria legge durata e destinazione dal modulo, applica lo scaglione, calcola la distanza stradale e somma i subtotali; OpenAI descrive l'ambito ma non decide né modifica i numeri.

Il calcolo stradale usa openrouteservice e richiede i parametri globali `OPENROUTESERVICE_API_KEY`, `OPENROUTESERVICE_API_URL` e `OPENROUTESERVICE_TIMEOUT`. Le regole specifiche di ciascun cliente rimangono nel database e si configurano in **Azienda e AI > Listino strutturato**.

## Numerazione e archiviazione

Ogni organizzazione ha una numerazione annuale indipendente nel formato `OFF-2026-00001`. Il file viene archiviato nel disco Laravel `local`, quindi non è pubblicamente accessibile. Il download passa da una rotta autenticata e soggetta all'isolamento tenant.

Il PDF viene generato insieme alla bozza del preventivo e poi riutilizzato per download e invio. Eliminando definitivamente il lead vengono eliminati anche i relativi file PDF.

## Invio

Le email con tipo `quotation` o `initial_quotation` includono automaticamente il PDF. Le email di qualificazione non lo allegano. La beta WhatsApp continua a comunicare la fascia nel testo e consente il download del documento dalla scheda lead, ma non invia ancora documenti tramite WhatsApp.

Non sono state aggiunte dipendenze Composer: il generatore è compatibile con PHP 8.3 e produce PDF 1.4.
