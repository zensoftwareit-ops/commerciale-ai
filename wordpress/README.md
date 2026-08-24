# Commerciale AI WordPress

Installazione completa e riproducibile di WordPress 7.1 con tema Commerciale AI e
plugin Stripe/licenze già integrati. Docker non è necessario.

## Installazione senza Docker

La modalità principale usa il normale wizard WordPress:

1. puntare il dominio o il virtual host a `wordpress/standalone`;
2. aprire il sito e inserire le credenziali del database;
3. creare l'amministratore;
4. accedere a `/wp-admin/`.

Un MU-plugin incluso attiva automaticamente tema e plugin, crea le pagine Prezzi
e Area cliente e configura permalink e fuso orario. La procedura completa è in
[`INSTALL-NO-DOCKER.md`](INSTALL-NO-DOCKER.md).

## Avvio opzionale con Docker

Prerequisiti: Docker Desktop con il comando `docker compose` disponibile.

```powershell
Copy-Item wordpress/.env.example wordpress/.env
notepad wordpress/.env
powershell -ExecutionPolicy Bypass -File wordpress/start.ps1
```

Dopo aver compilato `.env`, lo script:

1. avvia MariaDB e WordPress;
2. installa WordPress se il database è vuoto;
3. attiva `commerciale-ai-theme`;
4. attiva `commerciale-ai-client`;
5. crea le pagine Prezzi e Area cliente;
6. imposta permalink, lingua operativa e opzioni del plugin;
7. lascia il sito disponibile su `WP_HOME`.

Con i valori di esempio il sito risponde su `http://localhost:8080` e il backend
Laravel locale è atteso su `http://host.docker.internal:8000`.

Per fermare i container senza cancellare il database:

```powershell
powershell -ExecutionPolicy Bypass -File wordpress/stop.ps1
```

Per cancellare anche il database locale usare consapevolmente
`docker compose -f wordpress/docker-compose.yml down -v`.

## Installazione su hosting tradizionale

La directory `standalone` contiene già il core WordPress e può essere usata come
document root. Tema e plugin sono già presenti rispettivamente in:

- `wp-content/themes/commerciale-ai-theme`;
- `wp-content/plugins/commerciale-ai-client`.

Configurare nell'ambiente del server almeno `WORDPRESS_DB_*`, `WORDPRESS_SALT` e
`WP_HOME`. Se l'hosting non consente variabili d'ambiente, valorizzare le stesse
impostazioni in un file non pubblico e adattare `wp-config.php`; non inserire
segreti nel repository.

Dopo l'installazione attivare tema e plugin dal pannello, quindi aprire
**Impostazioni > Commerciale AI** e verificare le connessioni.

## Aggiornare tema e plugin integrati

Le directory sorgente sono `commerciale-ai-theme` e `commerciale-ai-client`.
Prima di un rilascio copiarle nelle directory equivalenti sotto `standalone` e
ricostruire gli ZIP con `build.ps1`.

## Produzione

- usare esclusivamente HTTPS;
- sostituire tutte le password e i placeholder di `.env.example`;
- usare chiavi Stripe live solo dopo il collaudo completo in test mode;
- eseguire backup di database e `wp-content/uploads`;
- lasciare `WP_DEBUG=false`;
- configurare proxy e DNS affinché `WP_HOME` coincida con il dominio pubblico.
