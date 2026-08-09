# Avvio del progetto pilota

## 1. Installazione

Sono sufficienti PHP 8.3, Composer e le estensioni indicate nel README. Non serve Docker.

```bash
composer run setup
```

Impostare in `.env` almeno `APP_URL`, `APP_KEY`, il database e la chiave OpenAI. Per un server pubblico usare `APP_ENV=production`, `APP_DEBUG=false`, HTTPS e PostgreSQL.

```dotenv
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-5.6-terra
```

La chiave non deve essere inserita nel pannello web, nei lead o nel repository. Dopo averla configurata:

```bash
php artisan config:clear
php artisan migrate --seed --force
```

Il document root del dominio deve puntare alla cartella `public/`.

## 2. Primo accesso

Accedere con l'utente demo indicato nel README e cambiare subito la password dalla pagina **Account**. Il seed è pensato solo per il primo collaudo: in produzione eliminare o sostituire le credenziali dimostrative.

La inbox contiene una checklist con quattro requisiti:

- profilo aziendale completo;
- almeno un documento attivo nella knowledge base;
- provider OpenAI e chiave server configurati;
- almeno una sorgente webhook attiva.

## 3. Contesto aziendale

Compilare **Azienda** con descrizione, servizi, cliente ideale, tono e firma. Inserire nella **Knowledge base** soltanto informazioni approvate: servizi, FAQ, prezzi e regole commerciali. Questi dati guidano qualificazione e suggerimenti.

## 4. Acquisizione lead

Per il collaudo si può creare un lead manualmente. Per collegare un sito, aprire **Sorgenti** e creare nuove credenziali oppure ruotare quelle demo. Il segreto è visibile una sola volta.

Il client deve inviare il corpo JSON senza modificarlo dopo aver calcolato:

```text
signature = HMAC-SHA256(timestamp + "." + raw_json_body, secret)
```

Header obbligatori:

```text
X-Webhook-Source: <chiave sorgente>
X-Webhook-Timestamp: <unix timestamp>
X-Webhook-Signature: <firma esadecimale>
Idempotency-Key: <id univoco dell'evento>
Content-Type: application/json
```

## 5. Collaudo funzionale

1. Creare o ricevere un lead.
2. Aprire la scheda e avviare **Analizza lead**.
3. Verificare punteggio, temperatura, sintesi, motivazioni e prossime azioni.
4. Correggere manualmente l'analisi quando necessario: la correzione resta tracciata.
5. Controllare la timeline per acquisizione e analisi.

Se la chiave è assente o la chiamata fallisce, il lead rimane disponibile e l'esecuzione AI viene registrata come fallita.

## 6. Esercizio

Su un server mantenere attivo il worker delle code e configurare backup del database e del file `.env` in un archivio cifrato. Prima di coinvolgere utenti reali verificare HTTPS, posta per il reset password, conservazione dei dati e informativa privacy.
