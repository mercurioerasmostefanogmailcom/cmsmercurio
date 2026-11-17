# MercurioCMS 1.0.0-RC3

## Aggiornamenti del CMS dal pannello
- Aggiunta la voce **Aggiornamenti CMS**, riservata all’amministratore principale.
- L’amministratore carica un pacchetto ZIP di aggiornamento; il CMS verifica manifest, versione, percorsi e checksum SHA-256 prima di modificare i file.
- Prima dell’applicazione viene creato un backup completo dei file dell’installazione e delle tabelle del database con il prefisso configurato.
- `config.php`, `uploads/`, `storage/`, `plugins/` e `.git/` sono esclusi dai file distribuibili e restano preservati.
- In caso di errore durante la sostituzione, i file già sostituiti vengono ripristinati dalla copia appena creata.
- Le migrazioni del database della nuova versione continuano a essere eseguite dal bootstrap, in modo incrementale e non distruttivo.

## Limiti da conoscere
- Il pacchetto di aggiornamento è un archivio ZIP con `update.json` e i file sotto `files/`; il manifest contiene gli SHA-256.
- Il lettore ZIP integrato consente di operare anche senza l’estensione PHP ZipArchive, se è disponibile zlib.
- Per un aggiornamento in produzione è comunque raccomandata una copia esterna preventiva.
- Il ripristino completo del database da `database.sql` è un’operazione manuale: il sistema non avvia un rollback SQL automatico dopo migrazioni già eseguite.

## Verifiche
La funzionalità richiede il collaudo su una copia dell’installazione 0.9.9 con dati reali di prova prima di dichiarare definitiva la versione 1.0.0.
