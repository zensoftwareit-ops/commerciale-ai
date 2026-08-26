# Commerciale AI Forms

Plugin proprietario per i moduli del sito Commerciale AI.

## Funzioni

- shortcode `[commerciale_ai_contact]` e `[commerciale_ai_form type="contact"]`;
- archivio privato delle richieste in **Richieste sito**;
- notifica email con collegamento diretto alla richiesta;
- consenso privacy obbligatorio, esportazione e cancellazione dati WordPress;
- honeypot, firma temporale, nonce e limite di frequenza;
- eliminazione automatica secondo il periodo di conservazione configurato;
- evento GA4 `generate_lead` dopo un invio confermato.

Configura destinatario, messaggio, evento Analytics e conservazione da **Richieste sito → Impostazioni**.

Per una consegna email affidabile, configura sul server un provider SMTP/transazionale. Il plugin conserva comunque ogni richiesta nel database anche se la notifica email fallisce.
