# MercurioCMS 1.0.0 – Versione Stabile

Release consolidata per installazione pulita e aggiornamento cumulativo da 0.9.9.

## Correzioni incluse
- Creazione automatica delle cartelle operative `storage`, `storage/update-backups`, `plugins`, `uploads`, `uploads/images` e `uploads/documents`.
- Gestione sicura dell'assenza della cartella backup, senza warning `scandir()`.
- Attivazione e disattivazione del secondo amministratore, oltre alla sua eliminazione.
- Opzioni indipendenti per nascondere titolo e categoria/percorso nella pagina pubblica.
- Punto di ingresso `/admin/` che inoltra alla dashboard dopo autenticazione.
- Protezione delle cartelle scrivibili contro listing ed esecuzione diretta di script PHP.

## Nota
Prima dell'aggiornamento eseguire un backup completo di file e database. La release è stata sottoposta a controlli statici e di sintassi PHP; il collaudo funzionale finale richiede l'esecuzione sull'ambiente di destinazione.
