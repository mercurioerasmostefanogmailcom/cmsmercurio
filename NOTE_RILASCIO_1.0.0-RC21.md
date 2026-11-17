# MercurioCMS 1.0.0-RC21

## Gestione ordinata e sicura dei backup

- I backup sono copie complete e indipendenti di file e database: eliminare una copia non modifica né invalida le altre.
- La schermata è organizzata in tre gruppi separati e comprimibili: punti di ripristino protetti, backup automatici degli aggiornamenti e backup di sicurezza.
- È disponibile una ricerca per data o versione, oltre a un riepilogo di quantità, spazio occupato e spazio recuperabile con la pulizia.
- La pulizia automatica conserva gli ultimi 5 backup automatici non protetti e rimuove i backup di sicurezza non protetti più vecchi di 7 giorni.
- I punti protetti non hanno scadenza e non vengono eliminati dalla pulizia automatica. Non è possibile rimuovere la protezione dall'ultimo punto protetto: occorre prima proteggere un'altra versione funzionante.
- Il punto scelto e ripristinato viene protetto automaticamente. Se non è possibile registrare la protezione, il ripristino viene annullato e viene tentato il recupero della situazione precedente.
- Se anche il rollback fallisce, il backup di sicurezza viene protetto automaticamente per impedirne la cancellazione automatica.
- I backup di sicurezza sono disponibili anche come sorgente di ripristino e, se non protetti, scadono dopo 7 giorni.
- La pulizia è eseguibile manualmente e viene eseguita dopo un aggiornamento riuscito e dopo un ripristino riuscito. I backup protetti restano intatti.
- Ogni nuovo backup include un manifesto SHA-256 dei file e del database; prima del ripristino vengono verificati gli hash e l'inventario dei file.
- I backup RC precedenti, privi di manifesto, restano utilizzabili con controlli strutturali: non è possibile verificare retroattivamente gli hash.
- Il ripristino riallinea i file del programma al contenuto del punto scelto, rimuovendo i file di programma aggiunti successivamente. Configurazione, uploads, storage, plugins e il gestore aggiornamenti attualmente in uso vengono preservati.
- Il ripristino del database elimina anche le tabelle del prefisso CMS introdotte dopo il backup e non presenti nella copia selezionata.
- Prima di ogni ripristino viene creato e verificato un backup di sicurezza completo della situazione corrente.
- La creazione delle cartelle backup usa un'operazione atomica con nuovi tentativi, per evitare collisioni e sovrascritture in caso di richieste ravvicinate.

Aggiornamento incrementale da MercurioCMS 1.0.0-RC20.
