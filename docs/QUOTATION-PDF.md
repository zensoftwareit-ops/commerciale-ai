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

Il documento usa esclusivamente prezzi, inclusioni, esclusioni e validità della regola di listino associata. Se la quotazione è indicativa, il PDF lo dichiara espressamente.

## Numerazione e archiviazione

Ogni organizzazione ha una numerazione annuale indipendente nel formato `OFF-2026-00001`. Il file viene archiviato nel disco Laravel `local`, quindi non è pubblicamente accessibile. Il download passa da una rotta autenticata e soggetta all'isolamento tenant.

Il PDF viene generato al primo download o immediatamente prima dell'invio della mail, poi viene riutilizzato senza rigenerazioni. Eliminando definitivamente il lead vengono eliminati anche i relativi file PDF.

## Invio

Le email con tipo `quotation` o `initial_quotation` includono automaticamente il PDF. Le email di qualificazione non lo allegano. La beta WhatsApp continua a comunicare la fascia nel testo e consente il download del documento dalla scheda lead, ma non invia ancora documenti tramite WhatsApp.

Non sono state aggiunte dipendenze Composer: il generatore è compatibile con PHP 8.3 e produce PDF 1.4.
