# Architettura Sprint 1

Commerciale AI è un monolite modulare Laravel 12 con interfaccia Blade server-rendered. PostgreSQL è il database applicativo; Redis gestirà code e cache. SQLite è ammesso esclusivamente nei test locali.

## Confini principali

- `Support/Tenancy`: contesto tenant e scope Eloquent centralizzato.
- `Services/Leads`: normalizzazione e creazione atomica di lead e timeline.
- `Contracts/LeadAnalyzer`: porta indipendente dal provider; nello Sprint 1 è collegata al fake deterministico.
- controller web: autenticazione, inbox, inserimento e scheda lead.
- controller inbound: autenticazione HMAC, anti-replay, deduplicazione e idempotenza.

Ogni modello aziendale contiene `organization_id`; lo scope viene attivato dal tenant risolto dalla membership dell'utente. I webhook risolvono prima la fonte globale e impostano poi esplicitamente il tenant.

## Decisioni e assunzioni

- Ruoli MVP: `owner`, `sales`, `viewer`; il Super Admin verrà introdotto con audit dedicato.
- La pipeline è configurabile per tenant e conserva una `system_category` per i report.
- Gli UUID sono chiavi primarie e identificatori pubblici.
- Nessuna email o chiamata AI reale è ammessa nello Sprint 1.
- Il webhook usa `X-Webhook-Source`, `X-Webhook-Timestamp`, `X-Webhook-Signature` e `Idempotency-Key`.
- Firma: HMAC-SHA256 di `<timestamp>.<raw-body>`.

## Rischi aperti

- Docker e Git non sono installati sulla macchina di sviluppo corrente.
- La build Docker è definita ma non è stata provata sulla macchina corrente, che non dispone di Docker.
- La deduplicazione email/telefono è intenzionalmente conservativa e andrà resa configurabile.
- La selezione multi-organizzazione usa la sessione ma non espone ancora un selettore UI.
- Rate limiting distribuito richiede Redis attivo.

## Threat model essenziale

- Cross-tenant disclosure: scope centrale, risoluzione membership e test negativo su route binding.
- Webhook spoofing/replay: segreto cifrato a riposo, HMAC, finestra temporale e idempotenza.
- Mass assignment: whitelist nei modelli e validazione request.
- XSS/CSRF: escaping Blade e middleware Laravel; le API non usano sessioni.
- Dati sensibili nei log: il webhook registra hash e stato, non il payload.
