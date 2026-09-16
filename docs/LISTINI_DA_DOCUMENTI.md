# Listini e regole da documenti

## Uso

In **Azienda e AI → Listino strutturato → Crea listini e regole da documenti**, l'owner può:

1. Allegare fino a 5 PDF, Word DOCX, TXT, CSV o immagini JPG/PNG/WEBP: massimo 10 MB per file, 20 MB totali.
2. Descrivere i documenti, le priorità fra fonti, valuta, IVA, periodicità e criteri di preventivazione.
3. Autorizzare l'analisi tramite OpenAI e generare una proposta (massimo 20 voci per analisi).
4. Espandere ogni voce, modificarla, selezionarla per l'importazione e decidere se attivarla subito. Le voci sono disattivate per impostazione predefinita; quelle senza prezzo non sono preselezionate. Un prezzo mancante non viene sostituito con zero. La validità non presente nelle fonti viene proposta a 15 giorni nell'anteprima, da confermare.
5. Modificare e salvare separatamente le regole commerciali testuali nella Knowledge base.

I listini esistenti non vengono sovrascritti. Ripetere la conferma della stessa bozza non duplica i dati. Una **nuova analisi** degli stessi allegati genera invece una nuova proposta: deselezionare eventuali voci già presenti. Dopo il salvataggio, listini e regole restano modificabili nelle rispettive sezioni. Le automazioni non vengono accese da questa funzione.

Le regole importate attive vengono incluse nell'analisi del lead; le 10 più recentemente aggiornate vengono inoltre fornite alle risposte e alla stima del preventivo. Sono indicazioni commerciali per l'AI: questa funzione non aggiunge un motore di formule né cambia il calcolo del prezzo esistente, che rimane vincolato alle fasce del listino. Se la formula documentata non è rappresentabile, serve verifica commerciale.

## Formati e limiti

PDF e immagini consentono anche l'analisi visiva. Da Word viene estratto il testo: esportare in PDF se sono importanti grafici, immagini o impaginazione. Documenti protetti, illeggibili, molto lunghi o fonti discordanti possono richiedere una nuova analisi con meno pagine. Per listini estesi, suddividere l'importazione per categoria e controllare che tutte le voci attese siano presenti.

Gli importi attuali sono in EUR. Verificare IVA, valuta e unità di misura prima di attivare le regole. Non usare una fascia a progetto per importi mensili senza esplicitarlo.

## Installazione Plesk / PHP 8.3

Non servono nuove dipendenze Composer, migrazioni o cron. Pubblicare il branch `software` e svuotare le cache applicative:

```sh
/opt/plesk/php/8.3/bin/php artisan optimize:clear
```

È necessario il provider OpenAI già configurato, con un modello che supporti input visivi e Structured Outputs. L'operazione compare come `pricing_import` nei consumi AI e rispetta il limite di utilizzo della licenza.

L'analisi dei file può richiedere più tempo delle normali risposte testuali. Il timeout dedicato predefinito è 180 secondi e può essere regolato globalmente con `OPENAI_FILE_TIMEOUT=180`. Dopo una modifica al file `.env`, eseguire `php artisan optimize:clear`.

Per usare il limite massimo di upload, verificare nelle impostazioni PHP del sottodominio: `file_uploads=On`, `upload_max_filesize=10M` (o superiore), `post_max_size=24M` (o superiore), `max_file_uploads>=5`, `memory_limit>=256M`. Il timeout PHP/FPM e del proxy deve essere maggiore del timeout OpenAI configurato. Anche eventuali limiti nginx devono ammettere la richiesta. Non disabilitare globalmente i limiti di upload.

## Riservatezza e accesso

Solo l'owner può generare e confermare. La bozza è associata all'organizzazione e all'utente che l'ha generata. Gli originali rimangono nei file temporanei PHP per la durata della richiesta; Daria non li salva in storage pubblico né nella sessione o nei log. L'invio OpenAI usa input inline e `store=false`, senza creare file remoti nell'API Files. Questo non equivale a una garanzia di zero retention del provider.

Vengono conservati nomi degli allegati, spiegazione, bozza e consumi nel record AI dell'organizzazione. Evitare di caricare dati personali non necessari o segreti. La generazione resta da controllare prima dell'attivazione.

Riferimento API: https://developers.openai.com/api/docs/guides/file-inputs
