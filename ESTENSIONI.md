# Sviluppare estensioni per MercurioCMS 1.0.0

## Struttura del pacchetto ZIP

Il file ZIP deve avere questi file direttamente nella radice:

```text
plugin.json
plugin.php
admin.php
```

`plugin.json` è il manifest obbligatorio:

```json
{
  "id": "esempio-estensione",
  "name": "Esempio estensione",
  "description": "Una breve descrizione",
  "version": "1.0.0",
  "min_cms_version": "1.0.0",
  "entry": "plugin.php"
}
```

L'ID deve essere univoco e composto da lettere minuscole, numeri, trattini o underscore. `entry` è il file PHP caricato quando l'estensione è attiva.

## Registrare una voce nel menu amministrativo

Nel file di avvio usare l'API:

```php
<?php
cms_register_admin_menu(
    'esempio-estensione',
    'Esempio estensione',
    '../plugins/esempio-estensione/admin.php'
);
```

La voce compare nel menu amministrativo soltanto mentre il plugin è attivo. Il link deve restare all'interno della cartella del plugin.

## Proteggere la pagina amministrativa del plugin

Ogni pagina amministrativa dell'estensione deve controllare l'accesso:

```php
<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_admin();
```

L'entrypoint viene caricato su tutte le richieste del sito quando il plugin è attivo, quindi deve limitarsi a registrare hook/voce menu e definire funzioni. Non deve stampare output direttamente.

## Sicurezza e limiti

- Un'estensione contiene codice PHP eseguibile con i permessi dell'applicazione: installare solo ZIP fidati. Non esiste una sandbox PHP per i plugin.
- Il caricatore valida ID, versione minima, percorsi, file di avvio e collegamenti simbolici.
- Rifiuta percorsi assoluti e tentativi di uscire dalla cartella tramite `../`.
- Limiti: ZIP 10 MB, 100 elementi, 20 MB estratti complessivi e 5 MB per singolo file.
- Per leggere i pacchetti ZIP è sufficiente `ZipArchive` oppure zlib (`gzinflate`), normalmente disponibile nelle installazioni PHP. Se `ZipArchive` manca, MercurioCMS usa il lettore ZIP integrato.
- Il lettore integrato supporta archivi ZIP classici non cifrati con compressione STORE o DEFLATE; per archivi ZIP64 o cifrati occorre `ZipArchive`.
- Il plugin non può sovrascrivere il core: viene estratto esclusivamente in `plugins/<id>/`.
- Disattivare un plugin ne interrompe il caricamento ma conserva i file; disinstallarlo elimina la cartella e il record nel registro. Eventuali tabelle o dati creati dal plugin devono essere gestiti dal plugin stesso prima della disinstallazione.
