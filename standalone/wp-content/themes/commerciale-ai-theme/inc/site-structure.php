<?php
/**
 * Managed marketing pages and navigation.
 *
 * @package Commerciale_AI
 */

defined('ABSPATH') || exit;

const CAI_CONTENT_VERSION = '2.1.0';

/**
 * Return the public page architecture.
 *
 * Content describes only capabilities implemented by the Commerciale AI app.
 *
 * @return array<string,array<string,mixed>>
 */
function cai_marketing_pages(): array
{
    return [
        'prodotto' => [
            'title' => 'Prodotto', 'slug' => 'prodotto', 'parent' => null,
            'excerpt' => 'Dall’arrivo del lead alla prossima azione: un flusso commerciale ordinato, assistito dall’AI e sempre sotto il controllo del team.',
            'content' => <<<'HTML'
<section class="content-lead"><p class="content-kicker">Un ambiente operativo, non un’altra casella da controllare</p><h2>Tutto il percorso del lead, nello stesso posto.</h2><p>Commerciale AI raccoglie richieste provenienti dal sito, inserimenti manuali e conversazioni email. Normalizza i dati, mette in evidenza ciò che conta e prepara il lavoro successivo senza togliere la decisione alle persone.</p></section>
<section class="content-section"><div class="content-heading"><p class="content-kicker">Le funzioni</p><h2>Sei aree che lavorano insieme.</h2></div><div class="link-card-grid">
<a class="link-card" href="/prodotto/acquisizione-lead/"><span>01</span><h3>Acquisizione lead</h3><p>Moduli web, endpoint dedicati, inserimenti manuali ed email in un’unica inbox.</p><strong>Scopri la raccolta lead →</strong></a>
<a class="link-card" href="/prodotto/qualificazione-ai/"><span>02</span><h3>Qualificazione AI</h3><p>Sintesi, intento, urgenza e priorità spiegate con dati verificabili.</p><strong>Scopri l’analisi →</strong></a>
<a class="link-card" href="/prodotto/risposte-conversazioni/"><span>03</span><h3>Risposte e conversazioni</h3><p>Bozze contestuali, approvazione umana, invio email e risposte in ingresso.</p><strong>Scopri le conversazioni →</strong></a>
<a class="link-card" href="/prodotto/pipeline-follow-up/"><span>04</span><h3>Pipeline e follow-up</h3><p>Stati, prossime azioni, timeline e notifiche per non perdere il momento giusto.</p><strong>Scopri il flusso →</strong></a>
<a class="link-card" href="/prodotto/knowledge-base/"><span>05</span><h3>Knowledge base</h3><p>Profilo aziendale, documenti e regole di prezzo danno contesto alle risposte.</p><strong>Scopri il contesto →</strong></a>
<a class="link-card" href="/prodotto/team-sicurezza-consumi/"><span>06</span><h3>Team, sicurezza e consumi</h3><p>Ruoli, separazione dei dati, limiti di licenza e controllo dell’utilizzo AI.</p><strong>Scopri il controllo →</strong></a>
</div></section>
<section class="content-section split-panel"><div><p class="content-kicker">Il principio</p><h2>L’AI prepara. Il commerciale decide.</h2></div><div><p>Le analisi e le bozze sono strumenti di lavoro: possono essere riviste, corrette e approvate prima dell’invio. Le automazioni più delicate partono disattivate e restano governate da limiti e controlli.</p><ul class="check-list"><li>Nessuna risposta “magica” senza contesto</li><li>Regole di prezzo deterministiche e versionate</li><li>Traccia delle attività e delle comunicazioni</li><li>Passaggio immediato a una persona quando serve</li></ul></div></section>
HTML,
        ],
        'acquisizione-lead' => [
            'title' => 'Acquisizione lead', 'slug' => 'acquisizione-lead', 'parent' => 'prodotto',
            'excerpt' => 'Porta nello stesso flusso le richieste dal sito, i contatti inseriti dal team e i messaggi ricevuti via email.',
            'content' => <<<'HTML'
<section class="content-lead"><h2>Ogni richiesta entra già leggibile.</h2><p>Per ogni sorgente viene creato un endpoint riservato. Il sito invia il proprio payload e Commerciale AI riconosce automaticamente i campi più comuni — contatto, azienda, servizio, messaggio, budget e consenso — anche quando hanno nomi diversi.</p></section>
<section class="content-section"><div class="detail-grid"><article><span class="detail-icon">↗</span><h3>Moduli e siti web</h3><p>Collega landing page e moduli senza imporre al sito una struttura rigida. I domini ammessi vengono controllati per ogni sorgente.</p></article><article><span class="detail-icon">＋</span><h3>Inserimento manuale</h3><p>Il team può creare un lead direttamente, mantenendo nello stesso archivio telefonate, segnalazioni e contatti offline.</p></article><article><span class="detail-icon">@</span><h3>Email in ingresso</h3><p>Le caselle IMAP configurate importano le risposte, le associano alla conversazione corretta e segnalano i casi da verificare.</p></article></div></section>
<section class="content-section split-panel"><div><p class="content-kicker">Dati puliti</p><h2>Meno copia-incolla, meno duplicati.</h2></div><div><p>Indirizzi email e campi di contatto vengono normalizzati. Il payload originale resta disponibile per il contesto operativo, mentre i dati sensibili non vengono duplicati inutilmente nei campi di analisi.</p><a class="text-link" href="/come-funziona/">Vedi il flusso completo →</a></div></section>
HTML,
        ],
        'qualificazione-ai' => [
            'title' => 'Qualificazione AI', 'slug' => 'qualificazione-ai', 'parent' => 'prodotto',
            'excerpt' => 'Trasforma una richiesta disordinata in una sintesi commerciale con intento, urgenza, priorità e motivazione.',
            'content' => <<<'HTML'
<section class="content-lead"><h2>Capire prima di rispondere.</h2><p>L’analisi AI combina il messaggio del lead con il profilo dell’azienda, la knowledge base e regole esplicite. Il risultato è strutturato e validato: non un testo generico, ma informazioni operative che il team può controllare.</p></section>
<section class="content-section"><div class="detail-grid detail-grid--four"><article><strong class="big-label">Sintesi</strong><p>Riduce la richiesta ai fatti utili senza perdere il contesto.</p></article><article><strong class="big-label">Intento</strong><p>Individua l’obiettivo commerciale espresso dal contatto.</p></article><article><strong class="big-label">Urgenza</strong><p>Segnala tempi, vincoli e indicatori che richiedono attenzione.</p></article><article><strong class="big-label">Priorità</strong><p>Propone uno score motivato, combinato con le regole dichiarate.</p></article></div></section>
<section class="content-section note-panel"><p class="content-kicker">Controllo umano</p><h2>Il punteggio aiuta a ordinare il lavoro, non decide il valore di una persona.</h2><p>Ogni output viene registrato con il modello utilizzato e può essere corretto dall’operatore. I contenuti ricevuti dal lead sono trattati come dati non attendibili, non come istruzioni per il sistema.</p></section>
HTML,
        ],
        'risposte-conversazioni' => [
            'title' => 'Risposte e conversazioni', 'slug' => 'risposte-conversazioni', 'parent' => 'prodotto',
            'excerpt' => 'Prepara risposte pertinenti, inviale dalla casella aziendale e ritrova l’intera conversazione nella scheda del lead.',
            'content' => <<<'HTML'
<section class="content-lead"><h2>Una bozza utile, pronta da rendere tua.</h2><p>Dopo l’analisi, Commerciale AI prepara una risposta coerente con tono, servizi e informazioni aziendali. L’operatore può modificarla, approvarla e inviarla dalla casella configurata.</p></section>
<section class="content-section"><ol class="numbered-flow"><li><span>1</span><div><h3>Il sistema prepara</h3><p>La bozza usa solo il contesto disponibile e, quando mancano dati, formula domande mirate.</p></div></li><li><span>2</span><div><h3>La persona verifica</h3><p>Testo, destinatario e prossima azione restano modificabili prima dell’invio.</p></div></li><li><span>3</span><div><h3>La conversazione continua</h3><p>Le risposte ricevute via IMAP entrano nella timeline e riaprono il lavoro quando serve.</p></div></li></ol></section>
<section class="content-section split-panel"><div><p class="content-kicker">Automazione responsabile</p><h2>Velocità con un freno a portata di mano.</h2></div><div><p>L’invio automatico iniziale è opzionale, parte disattivato e può essere limitato a indirizzi interni durante il collaudo. Il numero massimo di interventi automatici evita conversazioni senza fine; i casi incerti passano al team.</p></div></section>
HTML,
        ],
        'pipeline-follow-up' => [
            'title' => 'Pipeline e follow-up', 'slug' => 'pipeline-follow-up', 'parent' => 'prodotto',
            'excerpt' => 'Dai a ogni opportunità uno stato, una prossima azione e una storia consultabile da tutto il team autorizzato.',
            'content' => <<<'HTML'
<section class="content-lead"><h2>La pipeline diventa una lista di decisioni.</h2><p>L’inbox mostra provenienza, servizio richiesto, score, temperatura, stato operativo, prossima azione e ultima attività. I filtri aiutano a concentrarsi sui lead che richiedono davvero un intervento.</p></section>
<section class="content-section"><div class="detail-grid"><article><span class="detail-icon">◎</span><h3>Stato commerciale</h3><p>Segui il passaggio tra nuovo contatto, revisione, conversazione e fasi successive.</p></article><article><span class="detail-icon">→</span><h3>Prossima azione</h3><p>Associa una data di follow-up e rendi chiaro chi deve fare cosa.</p></article><article><span class="detail-icon">≡</span><h3>Timeline</h3><p>Analisi, bozze, invii, risposte e cambi di stato restano collegati al lead.</p></article></div></section>
<section class="content-section note-panel"><p class="content-kicker">Notifiche operative</p><h2>Quando arriva una risposta, il lavoro torna visibile.</h2><p>I nuovi messaggi possono annullare il follow-up precedente, contrassegnare il lead come “da gestire” e preparare una nuova bozza. Le associazioni incerte restano in una coda dedicata, senza forzare collegamenti sbagliati.</p></section>
HTML,
        ],
        'knowledge-base' => [
            'title' => 'Knowledge base e regole commerciali', 'slug' => 'knowledge-base', 'parent' => 'prodotto',
            'excerpt' => 'Dai all’AI un perimetro chiaro: identità aziendale, servizi, documenti, tono e fasce di preventivo controllate.',
            'content' => <<<'HTML'
<section class="content-lead"><h2>Risposte migliori partono da informazioni migliori.</h2><p>Il proprietario configura il profilo aziendale, il tono di voce, la firma email, le domande di qualificazione e i documenti che descrivono servizi e procedure. Questo contesto viene selezionato per preparare analisi e bozze più pertinenti.</p></section>
<section class="content-section"><div class="detail-grid"><article><span class="detail-icon">A</span><h3>Profilo aziendale</h3><p>Nome commerciale, posizionamento, tono, firma e istruzioni operative.</p></article><article><span class="detail-icon">≡</span><h3>Documenti</h3><p>Contenuti interni organizzati nella knowledge base e consultabili dal team.</p></article><article><span class="detail-icon">€</span><h3>Regole di prezzo</h3><p>Fasce di preventivo deterministiche e versionate che l’AI può presentare, ma non alterare.</p></article></div></section>
<section class="content-section split-panel"><div><p class="content-kicker">Quando manca un dato</p><h2>Meglio una domanda precisa che una promessa inventata.</h2></div><div><p>Se le informazioni non bastano per una fascia o una risposta affidabile, il sistema prepara poche domande mirate. Il team conserva così credibilità e controllo commerciale.</p></div></section>
HTML,
        ],
        'team-sicurezza-consumi' => [
            'title' => 'Team, sicurezza e consumi', 'slug' => 'team-sicurezza-consumi', 'parent' => 'prodotto',
            'excerpt' => 'Organizza gli accessi, separa i dati delle aziende e tieni visibili licenza, soglie e utilizzo dell’AI.',
            'content' => <<<'HTML'
<section class="content-lead"><h2>Ogni persona vede ciò che serve al suo ruolo.</h2><p>L’owner gestisce configurazione, utenti e licenza. I commerciali lavorano sui lead e sulle risposte; gli utenti in sola lettura possono consultare senza modificare. Ogni organizzazione resta isolata dalle altre.</p></section>
<section class="content-section"><div class="detail-grid"><article><span class="detail-icon">◉</span><h3>Ruoli e posti inclusi</h3><p>Owner, commerciale e sola lettura, entro il numero di utenti previsto dal piano.</p></article><article><span class="detail-icon">◇</span><h3>Dati separati</h3><p>Lead, caselle, documenti e attività sono vincolati all’organizzazione attiva.</p></article><article><span class="detail-icon">▥</span><h3>Consumi leggibili</h3><p>Richieste, token e costo stimato mostrano come viene usato il budget AI mensile.</p></article></div></section>
<section class="content-section note-panel"><p class="content-kicker">Credenziali e pagamenti</p><h2>I segreti non devono circolare nel sito.</h2><p>Le credenziali delle caselle sono cifrate nel database. Il pagamento viene gestito sulle pagine sicure di Stripe; il sito riceve soltanto gli eventi necessari ad attivare e aggiornare la licenza.</p></section>
HTML,
        ],
        'soluzioni' => [
            'title' => 'Soluzioni', 'slug' => 'soluzioni', 'parent' => null,
            'excerpt' => 'Lo stesso flusso si adatta a chi vende da solo, a un piccolo team commerciale o a un’azienda di servizi con più fonti di lead.',
            'content' => <<<'HTML'
<section class="content-lead"><p class="content-kicker">Parti dal modo in cui lavori</p><h2>Non serve cambiare mestiere per usare meglio i lead.</h2><p>Commerciale AI è pensato per organizzazioni che ricevono richieste, devono capirle rapidamente e vogliono rispondere in modo coerente. Cambiano volumi e ruoli; il percorso resta semplice.</p></section>
<section class="content-section"><div class="solution-grid"><a href="/soluzioni/professionisti-microimprese/"><p class="content-kicker">1 persona</p><h3>Professionisti e microimprese</h3><p>Riduci il tempo tra richiesta e prima risposta senza costruire un reparto commerciale.</p><strong>Vedi il percorso →</strong></a><a href="/soluzioni/team-commerciali/"><p class="content-kicker">Più persone</p><h3>Team commerciali</h3><p>Condividi priorità, conversazioni e prossime azioni senza perdere responsabilità.</p><strong>Vedi il percorso →</strong></a><a href="/soluzioni/agenzie-servizi-b2b/"><p class="content-kicker">Più sorgenti</p><h3>Agenzie e servizi B2B</h3><p>Gestisci richieste eterogenee con contesto, regole di prezzo e domande di qualifica.</p><strong>Vedi il percorso →</strong></a></div></section>
HTML,
        ],
        'professionisti' => [
            'title' => 'Professionisti e microimprese', 'slug' => 'professionisti-microimprese', 'parent' => 'soluzioni',
            'excerpt' => 'Per chi vende e consegna il lavoro: meno tempo a ricostruire richieste, più chiarezza su chi richiamare.',
            'content' => <<<'HTML'
<section class="content-lead"><h2>Una scrivania commerciale ordinata, anche se il commerciale sei tu.</h2><p>Centralizza i contatti dal sito, ottieni una sintesi della richiesta e prepara una risposta usando servizi, tono e regole della tua attività.</p></section>
<section class="content-section"><div class="before-after"><div><p class="content-kicker">Prima</p><ul><li>Richieste sparse tra form e caselle email</li><li>Risposte riscritte ogni volta</li><li>Follow-up affidati alla memoria</li></ul></div><div><p class="content-kicker">Con Commerciale AI</p><ul class="check-list"><li>Una inbox per tutte le opportunità</li><li>Bozze già contestualizzate</li><li>Prossima azione sempre visibile</li></ul></div></div></section>
<section class="content-section cta-inline"><div><h2>Scegli in base ai volumi reali.</h2><p>Per partire, confronta numero di utenti, lead mensili e budget AI.</p></div><a class="cai-button cai-button--coral" href="/prezzi/">Confronta i piani →</a></section>
HTML,
        ],
        'team-commerciali' => [
            'title' => 'Team commerciali', 'slug' => 'team-commerciali', 'parent' => 'soluzioni',
            'excerpt' => 'Una vista condivisa su lead, priorità e conversazioni, con accessi distinti e responsabilità leggibili.',
            'content' => <<<'HTML'
<section class="content-lead"><h2>Il contesto non resta nella casella di una sola persona.</h2><p>Il team lavora sulla stessa inbox, vede lo stato operativo dei lead e ritrova analisi, bozze, messaggi e follow-up nella timeline. I ruoli distinguono chi configura, chi opera e chi consulta.</p></section>
<section class="content-section"><div class="detail-grid"><article><span class="detail-icon">1</span><h3>Priorità condivise</h3><p>Score, urgenza e stato aiutano a distribuire il lavoro in modo coerente.</p></article><article><span class="detail-icon">2</span><h3>Risposte coerenti</h3><p>Profilo aziendale e knowledge base riducono differenze di tono e informazioni.</p></article><article><span class="detail-icon">3</span><h3>Controllo owner</h3><p>Utenti, caselle, fonti, regole e consumi restano governati dal titolare.</p></article></div></section>
<section class="content-section cta-inline"><div><h2>Il piano giusto segue la dimensione del team.</h2><p>Verifica i posti inclusi e le soglie operative prima dell’acquisto.</p></div><a class="cai-button cai-button--coral" href="/prezzi/">Guida alla scelta →</a></section>
HTML,
        ],
        'agenzie-b2b' => [
            'title' => 'Agenzie e servizi B2B', 'slug' => 'agenzie-servizi-b2b', 'parent' => 'soluzioni',
            'excerpt' => 'Per richieste diverse per servizio, budget e maturità: qualificazione coerente e preventivi governati da regole.',
            'content' => <<<'HTML'
<section class="content-lead"><h2>Ogni richiesta è diversa. Il metodo non deve esserlo.</h2><p>Collega più siti e landing page, conserva i campi commerciali specifici e usa domande di qualificazione coerenti con i servizi offerti. Le regole di prezzo producono fasce controllate e versionate.</p></section>
<section class="content-section"><div class="detail-grid"><article><span class="detail-icon">↗</span><h3>Più fonti</h3><p>Un endpoint e domini ammessi per ogni sito o percorso di acquisizione.</p></article><article><span class="detail-icon">?</span><h3>Qualifica mirata</h3><p>Budget, tempi, servizio e obiettivo diventano criteri leggibili dal team.</p></article><article><span class="detail-icon">€</span><h3>Fasce controllate</h3><p>Il listino propone intervalli deterministici; l’AI non può inventare prezzi.</p></article></div></section>
<section class="content-section cta-inline"><div><h2>Dimensiona il piano su fonti e volumi.</h2><p>Il numero di lead mensili è il criterio principale insieme agli utenti coinvolti.</p></div><a class="cai-button cai-button--coral" href="/prezzi/">Confronta i pacchetti →</a></section>
HTML,
        ],
        'come-funziona' => [
            'title' => 'Come funziona', 'slug' => 'come-funziona', 'parent' => null,
            'excerpt' => 'Dalla configurazione iniziale alla gestione quotidiana: sei passaggi chiari e verificabili.',
            'content' => <<<'HTML'
<section class="content-lead"><p class="content-kicker">Il percorso operativo</p><h2>Configura una volta. Migliora a ogni conversazione.</h2><p>L’onboarding accompagna l’owner nella preparazione del contesto e dei collegamenti. Il lavoro quotidiano parte poi dalla inbox dei lead.</p></section>
<section class="content-section"><ol class="process-list"><li><span>01</span><div><h3>Descrivi l’azienda</h3><p>Imposta identità, servizi, tono, firma e domande di qualificazione.</p></div></li><li><span>02</span><div><h3>Aggiungi conoscenza e regole</h3><p>Carica i documenti utili e configura le fasce di preventivo.</p></div></li><li><span>03</span><div><h3>Collega fonti e casella</h3><p>Crea l’endpoint per il sito e verifica la casella email aziendale.</p></div></li><li><span>04</span><div><h3>Ricevi e analizza</h3><p>Il lead viene normalizzato; l’AI prepara sintesi, priorità e bozza.</p></div></li><li><span>05</span><div><h3>Verifica e invia</h3><p>Il commerciale controlla il contenuto e decide il prossimo passo.</p></div></li><li><span>06</span><div><h3>Segui la conversazione</h3><p>Risposte, attività e follow-up aggiornano la scheda del lead.</p></div></li></ol></section>
<section class="content-section note-panel"><p class="content-kicker">Prima di andare in produzione</p><h2>Il collaudo fa parte dell’installazione.</h2><p>Verifica la sorgente con un lead di prova, controlla la qualità dell’analisi, rivedi tono e knowledge base e testa invio/ricezione dalla casella. Le automazioni vanno abilitate soltanto dopo questo passaggio.</p></section>
<section class="content-section cta-inline"><div><h2>Vuoi vedere quale piano serve?</h2><p>Parti da utenti, volume mensile dei lead e budget AI.</p></div><a class="cai-button cai-button--coral" href="/prezzi/">Vai ai prezzi →</a></section>
HTML,
        ],
        'prezzi' => [
            'title' => 'Prezzi', 'slug' => 'prezzi', 'parent' => null,
            'excerpt' => 'Confronta i pacchetti sulla base del lavoro reale: persone coinvolte, lead gestiti ogni mese e utilizzo dell’AI.',
            'content' => <<<'HTML'
<section class="content-lead"><p class="content-kicker">Come scegliere</p><h2>Il piano giusto non è quello con il nome più grande.</h2><p>Conta quante persone lavorano sui lead, quanti nuovi contatti entrano mediamente in un mese e quanta analisi AI serve. Le soglie riportate nelle schede arrivano direttamente dal sistema licenze.</p></section>
<section class="content-section"><div class="choice-grid"><article><span>01</span><h3>Utenti</h3><p>Conta owner, commerciali e persone in sola lettura che devono accedere.</p></article><article><span>02</span><h3>Lead mensili</h3><p>Usa la media dei mesi più intensi, lasciando margine per la crescita.</p></article><article><span>03</span><h3>Budget AI</h3><p>Più analisi e bozze vengono generate, maggiore sarà il consumo di token.</p></article></div></section>
<section class="content-section pricing-section"><div class="content-heading"><p class="content-kicker">Pacchetti disponibili</p><h2>Confronto trasparente.</h2><p>Pagamento annuale tramite Stripe. Il rinnovo, le fatture e l’eventuale disdetta si gestiscono dall’area cliente.</p></div>[commerciale_ai_pricing]</section>
<section class="content-section split-panel"><div><p class="content-kicker">Hai un dubbio?</p><h2>Scegli per l’uso di oggi, non per una promessa futura.</h2></div><div><p>Se sei vicino a una soglia, considera il piano successivo. Se invece hai processi o volumi fuori scala, contattaci prima dell’acquisto: verificheremo insieme configurazione e sostenibilità.</p><a class="text-link" href="/contatti/">Parla con noi →</a></div></section>
HTML,
        ],
        'faq' => [
            'title' => 'Domande frequenti', 'slug' => 'faq', 'parent' => null,
            'excerpt' => 'Risposte dirette su installazione, AI, email, sicurezza, pagamenti, licenze e limiti dei piani.',
            'content' => <<<'HTML'
<section class="content-section faq-list">
<details open><summary>Commerciale AI è un CRM completo?</summary><p>È un ambiente operativo focalizzato sui lead in ingresso: raccolta, qualificazione, risposte email, pipeline, follow-up, knowledge base e controllo dei consumi. Non sostituisce automaticamente gestionali, contabilità o strumenti non indicati nelle funzionalità.</p></details>
<details><summary>L’AI invia email senza controllo?</summary><p>Per impostazione predefinita prepara bozze che un operatore può modificare, approvare e inviare. Le automazioni iniziali sono opzionali, partono disattivate e dispongono di limiti e modalità di collaudo.</p></details>
<details><summary>Come arrivano i lead dal mio sito?</summary><p>Per ogni sorgente viene generato un endpoint riservato. Il sito invia il payload del modulo; Commerciale AI riconosce e normalizza i campi più comuni. È possibile limitare i domini autorizzati.</p></details>
<details><summary>Posso usare la mia casella email?</summary><p>Sì. L’owner configura una casella IMAP/SMTP compatibile. Le credenziali vengono cifrate nel database e le risposte possono essere importate nella conversazione del lead.</p></details>
<details><summary>Cosa succede se l’AI non ha abbastanza informazioni?</summary><p>Il sistema può proporre poche domande mirate. Le fasce di preventivo dipendono da regole deterministiche: l’AI può presentarle, ma non modificarle o inventarle.</p></details>
<details><summary>Come scelgo il pacchetto?</summary><p>Confronta posti utente, limite di lead mensili e budget AI. Le schede prezzo mostrano i valori correnti letti dal backend licenze.</p></details>
<details><summary>Dove avviene il pagamento?</summary><p>Il checkout avviene sulle pagine sicure di Stripe. WordPress non raccoglie né conserva i dati completi della carta.</p></details>
<details><summary>Posso disdire o scaricare le fatture?</summary><p>Sì. Dall’area cliente puoi aprire il portale Stripe per gestire metodo di pagamento, fatture, rinnovo e disdetta secondo le condizioni del piano.</p></details>
<details><summary>Cosa accade quando raggiungo un limite?</summary><p>Il sistema applica le soglie previste dalla licenza. L’area consumi rende visibile l’utilizzo AI; per continuare oltre i limiti occorre passare a un piano adeguato.</p></details>
</section>
<section class="content-section cta-inline"><div><h2>Non hai trovato la risposta?</h2><p>Scrivici descrivendo team, fonti dei lead e volume mensile.</p></div><a class="cai-button cai-button--coral" href="/contatti/">Contattaci →</a></section>
HTML,
        ],
        'contatti' => [
            'title' => 'Contatti', 'slug' => 'contatti', 'parent' => null,
            'excerpt' => 'Raccontaci come arrivano oggi i lead e quante persone li gestiscono: ti aiuteremo a verificare configurazione e piano.',
            'content' => '[commerciale_ai_contact]',
        ],
    ];
}

function cai_page_url(string $key): string
{
    $pages = cai_marketing_pages();
    if (! isset($pages[$key])) return home_url('/');
    $page = get_page_by_path(cai_page_path($key, $pages));
    return $page instanceof WP_Post ? (string) get_permalink($page) : home_url('/'.cai_page_path($key, $pages).'/');
}

/** @param array<string,array<string,mixed>> $pages */
function cai_page_path(string $key, array $pages): string
{
    $segments = [];
    while (isset($pages[$key])) {
        array_unshift($segments, (string) $pages[$key]['slug']);
        $key = (string) ($pages[$key]['parent'] ?? '');
        if ($key === '') break;
    }
    return implode('/', $segments);
}

/** Replace root-relative managed links with the current WordPress base URL. */
function cai_prepare_page_content(string $content): string
{
    return (string) preg_replace_callback('/href="\/(?!\/)([^"#]+)"/', static function (array $match): string {
        return 'href="'.esc_url(home_url('/'.ltrim($match[1], '/'))).'"';
    }, $content);
}

function cai_sync_marketing_site(): void
{
    if ((string) get_option('cai_content_version', '') === CAI_CONTENT_VERSION) return;
    if (! current_user_can('edit_theme_options') && wp_doing_cron()) return;

    $pages = cai_marketing_pages();
    $ids = [];
    foreach ($pages as $key => $page) {
        $parent_id = ! empty($page['parent']) ? (int) ($ids[$page['parent']] ?? 0) : 0;
        $existing = get_page_by_path(cai_page_path($key, $pages));
        if (! $existing instanceof WP_Post && $parent_id === 0) $existing = get_page_by_path((string) $page['slug']);
        $managed = $existing instanceof WP_Post && (string) get_post_meta($existing->ID, '_cai_managed_page', true) === '1';
        $adoptable_pricing = $key === 'prezzi' && $existing instanceof WP_Post && trim((string) $existing->post_content) === '[commerciale_ai_pricing]';
        $post = [
            'post_type' => 'page', 'post_status' => 'publish', 'post_title' => (string) $page['title'],
            'post_name' => (string) $page['slug'], 'post_parent' => $parent_id,
            'post_excerpt' => (string) $page['excerpt'], 'post_content' => cai_prepare_page_content((string) $page['content']),
        ];
        if ($existing instanceof WP_Post) {
            $ids[$key] = $existing->ID;
            if ($managed || $adoptable_pricing) {
                $post['ID'] = $existing->ID;
                $result = wp_update_post(wp_slash($post), true);
                if (! is_wp_error($result)) update_post_meta($existing->ID, '_cai_managed_page', '1');
            }
            continue;
        }
        $result = wp_insert_post(wp_slash($post), true);
        if (! is_wp_error($result)) {
            $ids[$key] = (int) $result;
            update_post_meta((int) $result, '_cai_managed_page', '1');
        }
    }

    cai_sync_navigation($ids, $pages);
    update_option('cai_content_version', CAI_CONTENT_VERSION, false);
    flush_rewrite_rules(false);
}
add_action('init', 'cai_sync_marketing_site', 30);

/** @param array<string,int> $ids @param array<string,array<string,mixed>> $pages */
function cai_sync_navigation(array $ids, array $pages): void
{
    $locations = get_theme_mod('nav_menu_locations', []);
    $definitions = [
        'primary' => ['name' => 'Commerciale AI · Principale', 'keys' => ['prodotto', 'acquisizione-lead', 'qualificazione-ai', 'pipeline-follow-up', 'soluzioni', 'professionisti', 'team-commerciali', 'agenzie-b2b', 'come-funziona', 'prezzi', 'faq']],
        'footer' => ['name' => 'Commerciale AI · Footer', 'keys' => ['prodotto', 'come-funziona', 'prezzi', 'faq', 'contatti']],
    ];
    foreach ($definitions as $location => $definition) {
        $menu = wp_get_nav_menu_object($definition['name']);
        $created_menu = $menu ? $menu->term_id : wp_create_nav_menu($definition['name']);
        if (is_wp_error($created_menu)) continue;
        $menu_id = (int) $created_menu;
        if ($menu_id <= 0) continue;
        $existing_items = [];
        foreach ((array) wp_get_nav_menu_items($menu_id) as $existing_item) {
            if ($existing_item instanceof WP_Post && (string) $existing_item->type === 'post_type') $existing_items[(int) $existing_item->object_id] = $existing_item->ID;
        }
        $item_ids = [];
        foreach ($definition['keys'] as $key) {
            if (empty($ids[$key])) continue;
            $parent_key = (string) ($pages[$key]['parent'] ?? '');
            $item_id = wp_update_nav_menu_item($menu_id, (int) ($existing_items[$ids[$key]] ?? 0), [
                'menu-item-object-id' => $ids[$key], 'menu-item-object' => 'page', 'menu-item-type' => 'post_type',
                'menu-item-status' => 'publish', 'menu-item-parent-id' => $item_ids[$parent_key] ?? 0,
            ]);
            if (! is_wp_error($item_id)) $item_ids[$key] = (int) $item_id;
        }
        $current = isset($locations[$location]) ? wp_get_nav_menu_object((int) $locations[$location]) : false;
        if (! $current || str_starts_with((string) $current->name, 'Commerciale AI ·')) $locations[$location] = $menu_id;
    }
    set_theme_mod('nav_menu_locations', $locations);
}

function cai_contact_shortcode(): string
{
    ob_start(); ?>
    <section class="contact-layout">
        <div class="contact-card contact-card--accent"><p class="content-kicker">Prima dell’acquisto</p><h2>Tre informazioni ci aiutano a risponderti bene.</h2><ol><li>Quante persone lavorano sui lead?</li><li>Quanti nuovi contatti ricevete in un mese?</li><li>Da quali siti o caselle arrivano?</li></ol></div>
        <div class="contact-card"><p class="content-kicker">Modulo contatti</p><h2>Il modulo è temporaneamente non disponibile.</h2><p>Attiva il plugin Commerciale AI Forms per raccogliere e gestire le richieste dal sito.</p><p class="contact-note">Per pagamenti e abbonamenti già attivi, usa l’Area cliente.</p></div>
    </section>
    <?php return (string) ob_get_clean();
}
add_shortcode('commerciale_ai_contact', 'cai_contact_shortcode');
