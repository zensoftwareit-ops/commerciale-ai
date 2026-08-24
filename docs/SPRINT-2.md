# Sprint 2 — analisi e knowledge base

## Funzioni implementate

- onboarding e completezza del profilo aziendale;
- knowledge base per testo, FAQ, servizi e regole di prezzo;
- stati `draft`, `active` e `archived`;
- profilo di qualificazione con pesi configurabili;
- policy di analisi versionata;
- output AI strutturato e validato;
- un tentativo di riparazione deterministica prima del fallimento;
- score AI, score regole e score finale separati;
- storico versionato delle analisi;
- correzione umana con autore e timestamp;
- registrazione di provider, modello, unità, costi ed errori;
- aggiornamento di priorità, temperatura, pipeline e timeline.

## Provider

`LeadAnalyzer` resta una porta applicativa. In produzione l'implementazione OpenAI usa la Responses API e uno schema JSON rigido; nei test `FakeLeadAnalyzer` mantiene le esecuzioni deterministiche. Il provider si seleziona con `AI_PROVIDER` senza modificare controller, scoring o persistenza.

## Sicurezza

- i contenuti esterni sono dati non attendibili, mai istruzioni;
- non vengono memorizzati ragionamenti interni del modello;
- l’input registrato contiene soltanto il contesto applicativo usato;
- ogni tabella applicativa è isolata tramite `organization_id`;
- nessun segreto del provider è presente nel repository.
