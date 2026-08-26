# Installazione WordPress senza Docker

La directory `wordpress/standalone` è un pacchetto WordPress completo. Non servono
Docker, Composer o comandi WP-CLI.

## Hosting o server web

Servono soltanto:

- PHP 8.1 o successivo;
- MySQL 8 oppure MariaDB 10.6 o successivo;
- Apache o Nginx con HTTPS;
- un database vuoto e le relative credenziali.

Procedura:

1. eseguire il pull del branch nel server;
2. impostare `wordpress/standalone` come document root del dominio, oppure copiarne
   il contenuto nella document root già esistente;
3. assicurarsi che il server possa scrivere `wp-config.php` e `wp-content/uploads`;
4. aprire il dominio nel browser;
5. inserire database, utente, password e host nel wizard WordPress;
6. creare l'utente amministratore e completare l'installazione;
7. entrare una prima volta in `/wp-admin/`.

Al primo accesso amministrativo il bootstrap integrato:

- attiva il tema `commerciale-ai-theme`;
- attiva il plugin `commerciale-ai-client`;
- attiva il plugin `commerciale-ai-forms`;
- crea le pagine Prezzi e Area cliente;
- configura permalink, fuso orario e impostazioni commenti.

Aprire infine **Impostazioni > Commerciale AI** e inserire URL del backend,
`BILLING_INTEGRATION_KEY`, chiavi Stripe e URL di accesso al software.
Aprire anche **Richieste sito > Impostazioni** per configurare l'indirizzo che
riceve le notifiche dei form e il periodo di conservazione.

## Installazione locale su Windows

Se lavori in locale, puoi usare qualunque stack con Apache, PHP e MySQL già
installato:

1. creare un virtual host la cui document root sia `wordpress/standalone`;
2. creare il database `commerciale_ai_wp`;
3. avviare Apache e MySQL;
4. aprire l'URL del virtual host e seguire il wizard WordPress.

Non aprire il sito di produzione in HTTP. Stripe richiede un endpoint webhook
pubblicamente raggiungibile tramite HTTPS.

## Hosting che non può creare wp-config.php

Se il wizard segnala che non può scrivere il file:

1. copiare `wp-config-sample.php` in `wp-config.php`;
2. inserire manualmente `DB_NAME`, `DB_USER`, `DB_PASSWORD` e `DB_HOST`;
3. generare chiavi e salt univoci;
4. non committare mai `wp-config.php`.

Il file è già escluso da Git.
