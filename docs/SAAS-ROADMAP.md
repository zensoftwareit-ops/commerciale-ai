# Daria SaaS — percorso verso la produzione

Daria viene distribuita come un'unica piattaforma multi-tenant. Ogni cliente è
un'`Organization`; owner e sottoutenti vi accedono tramite la tabella di membership.
Il database è condiviso, mentre tutte le entità operative sono isolate tramite
`organization_id`.

## Configurazione globale (`.env`)

Nel file server rimangono esclusivamente infrastruttura e segreti della piattaforma:

- URL, chiave applicativa e ambiente;
- connessione database, cache, sessioni e code;
- credenziale OpenAI e modello predefinito;
- SMTP transazionale o chiave Resend;
- credenziali Stripe e chiave dell'integrazione billing;
- storage, logging e interruttori di sicurezza globali.

## Configurazione per cliente (database)

Appartengono all'organizzazione:

- profilo aziendale, tono, firma e knowledge base;
- utenti, ruoli e owner;
- sorgenti lead e domini consentiti;
- caselle IMAP con credenziali cifrate;
- listini, regole di preventivo e automazioni;
- mittenti e domini email verificati;
- licenza, pacchetto, limiti e consumi AI;
- lead, conversazioni, notifiche e cronologia.

## Fase 1 — fondazione multi-tenant

- isolamento dei modelli tenant in modalità fail-closed;
- blocco delle scritture verso un tenant diverso da quello attivo;
- cambio workspace consentito soltanto agli utenti membri;
- panoramica Super Admin di clienti, owner, configurazione, utilizzo e licenza;
- test automatici contro accessi incrociati.

## Fase 2 — onboarding e controllo operativo

- onboarding guidato del cliente dopo l'attivazione;
- stato di attivazione per organizzazione;
- verifica email e sicurezza account;
- gestione completa di sospensione, cancellazione e riattivazione;
- audit log delle operazioni amministrative.

## Fase 3 — esercizio SaaS

- contabilizzazione token e costo stimato per organizzazione (completata);
- budget AI mensile per pacchetto, avviso all'80% e blocco al 100% (completato);
- retry con backoff, arresto automatico e passaggio al commerciale (completato);
- monitoraggio protetto e allarmi email deduplicati (completato);
- backup automatici e prove periodiche di ripristino;
- retention dei lead chiusi ed esportazione dati per tenant (completato);
- rate limit aggiuntivi per tenant e protezione dei costi OpenAI.

Un'istanza o un database dedicato rimangono una possibile offerta Enterprise e non
il modello standard della piattaforma.
