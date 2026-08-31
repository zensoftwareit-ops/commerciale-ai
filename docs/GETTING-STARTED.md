# Avvio operativo di Daria

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

In alternativa, dalla pagina **Avvio guidato** scegliere **Compila con Daria** e
descrivere l'attività in linguaggio naturale e/o indicare l'URL del sito aziendale.
Daria legge fino a quattro pagine pubbliche dello stesso dominio e OpenAI prepara un'anteprima modificabile
del profilo, delle modalità di gestione delle richieste e della knowledge base. Prima
di applicarla verificare in particolare ipotesi, prezzi, tempi e criteri di passaggio
a un commerciale. Il wizard non abilita automaticamente alcun invio.

## 4. Acquisizione lead

Per il collaudo si può creare un lead manualmente. Per collegare un sito, aprire **Sorgenti**, indicare i domini consentiti e generare un endpoint dedicato. L’URL segreto è visibile una sola volta.

Il backend del sito deve soltanto eseguire:

```text
POST https://DOMINIO-APP/api/v1/inbound/leads/<token-segreto>
Content-Type: application/json

<payload originale del sito>
```

Non sono richiesti mapping, firma o header personalizzati. Daria riconosce automaticamente le strutture più comuni e genera internamente la chiave di idempotenza. L’endpoint deve restare nel backend: non inserirlo in JavaScript pubblico.

Un POST server-to-server non espone necessariamente un dominio verificabile. Il token segreto è quindi l’autenticazione primaria; quando la richiesta contiene `Origin`, `Referer` o un URL sorgente, il dominio viene anche confrontato con quelli consentiti nel database.

## 5. Collaudo funzionale

1. Creare o ricevere un lead.
2. Aprire la scheda e avviare **Analizza lead**.
3. Verificare punteggio, temperatura, sintesi, motivazioni e prossime azioni.
4. Correggere manualmente l'analisi quando necessario: la correzione resta tracciata.
5. Controllare la timeline per acquisizione e analisi.

Se la chiave è assente o la chiamata fallisce, il lead rimane disponibile e l'esecuzione AI viene registrata come fallita.

## 6. Esercizio

Su un server mantenere attivo il worker delle code e configurare backup del database e del file `.env` in un archivio cifrato. Prima di coinvolgere utenti reali verificare HTTPS, posta per il reset password, conservazione dei dati e informativa privacy.
