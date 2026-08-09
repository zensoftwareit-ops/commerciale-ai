# Sprint 1 — backlog tecnico ordinato

1. Bootstrap Laravel 12 e configurazione PHP 8.3+ con SQLite.
2. Definizione contesto tenant, UUID e scope automatici.
3. Organizzazioni, membership e ruoli `owner`, `sales`, `viewer`.
4. Autenticazione, logout e recupero password.
5. Pipeline tenant-configurabile con categorie di sistema.
6. Schema lead, contatti, fonti inbound, ricevute webhook e timeline.
7. Servizio atomico di creazione, normalizzazione email/telefono e attività iniziale.
8. Inserimento manuale con validazione e autorizzazione per ruolo.
9. Inbox filtrabile e scheda lead con aggiornamento stato/pipeline.
10. Webhook v1 con HMAC, anti-replay, rate limit, idempotenza e deduplicazione.
11. Contratto `LeadAnalyzer` e fake deterministico.
12. Seed Zen Software con dati esclusivamente fittizi.
13. Test di isolamento, webhook, idempotenza, inserimento e fake AI.
14. Ambiente PHP/Composer, variabili, istruzioni di avvio e threat model.

Tutti i punti sono implementati nello Sprint 1. Restano intenzionalmente fuori: provider AI reale, email reale, analisi persistita, bozze, follow-up e report.
