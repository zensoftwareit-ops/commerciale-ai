# Architettura del pilot

Commerciale AI è un monolite modulare Laravel 12 con interfaccia Blade server-rendered. SQLite è il database predefinito per sviluppo; MariaDB, MySQL e PostgreSQL sono supportati per il server. Code, cache e sessioni usano il database senza servizi aggiuntivi.

## Confini principali

- `Support/Tenancy`: contesto tenant e scope Eloquent centralizzato.
- `Services/Leads`: normalizzazione e creazione atomica di lead e timeline.
- `Contracts/LeadAnalyzer`: porta indipendente dal provider, collegata a OpenAI in produzione e al fake deterministico nei test.
- `Contracts/InboundMailbox`: porta indipendente dal client IMAP, con implementazione Composer pura e fake nei test.
- controller web: autenticazione, inbox, inserimento e scheda lead.
- controller inbound adattivo: endpoint tokenizzato, allowlist dei domini, normalizzazione, deduplicazione e idempotenza automatica.

Ogni modello aziendale contiene `organization_id`; lo scope viene attivato dal tenant risolto dalla membership dell'utente. I webhook risolvono prima la fonte globale e impostano poi esplicitamente il tenant.

## Decisioni e assunzioni

- Ruoli MVP: `owner`, `sales`, `viewer`; il Super Admin verrà introdotto con audit dedicato.
- La pipeline è configurabile per tenant e conserva una `system_category` per i report.
- Gli UUID sono chiavi primarie e identificatori pubblici.
- Le chiamate OpenAI sono abilitate soltanto quando la chiave è configurata sul server; ogni invio commerciale richiede approvazione umana.
- Le email ricevute vengono associate usando thread ID e mittente, con fallback conservativo su un solo lead compatibile. Messaggi sconosciuti o automatici non vengono acquisiti.
- Il flusso standard usa un token casuale nell’endpoint; nel database viene conservato soltanto il suo hash SHA-256.
- I domini consentiti sono associati alla sorgente. `Origin`, `Referer` e URL dichiarati nel payload vengono verificati quando disponibili.
- Nei POST server-to-server senza evidenza del dominio, il token segreto autentica la sorgente; la modalità usata viene registrata nella receipt.

## Rischi aperti

- Git non è installato sulla macchina di sviluppo corrente; la pubblicazione usa il connettore GitHub.
- La deduplicazione email/telefono è intenzionalmente conservativa e andrà resa configurabile.
- La selezione multi-organizzazione usa la sessione ma non espone ancora un selettore UI.
- Il rate limiting su database è sufficiente per il pilot; un backend dedicato potrà essere introdotto solo se necessario.

## Threat model essenziale

- Cross-tenant disclosure: scope centrale, risoluzione membership e test negativo su route binding.
- Webhook spoofing/replay: token ad alta entropia memorizzato come hash, allowlist, rate limit e idempotenza automatica.
- Email spoofing: associazione basata sia sul thread ID sia sull'indirizzo normalizzato del lead; fallback ammesso soltanto per un unico lead compatibile.
- Mass assignment: whitelist nei modelli e validazione request.
- XSS/CSRF: escaping Blade e middleware Laravel; le API non usano sessioni.
- Dati sensibili nei log: il webhook registra hash e stato, non il payload.
