# MercurioCMS 1.0.0 — Release Candidate 1

**Stato:** candidata al rilascio, non ancora release stabile.  
**Base di partenza:** 0.9.9 (4 novembre 2026).

## Obiettivo

Consolidare il pacchetto 0.9.9, uniformare la documentazione, preparare il primo rilascio 1.0 e correggere i riferimenti alla versione che avrebbero impedito la corretta gestione dei requisiti minimi delle estensioni dopo il passaggio alla 1.0.

## Modifiche di consolidamento RC1

- Introdotto il file `VERSION` come fonte unica della versione runtime.
- Il controllo `min_cms_version` delle estensioni ora confronta la versione effettiva letta da `VERSION`, invece di usare il valore fisso `0.9.9`.
- Il parametro di cache-busting dello script editor usa la versione runtime.
- Aggiunti controlli preliminari dell'estensione Fileinfo e Iconv nell'installatore, richieste dal controllo dei file caricati e dalla generazione degli slug.
- Aggiornati README, istruzioni d'installazione e aggiornamento, guida alla prima configurazione e guida utente.
- Aggiunti catalogo funzionalità e note di rilascio.

## Funzionalità incluse dalla linea 0.9.x

- Editor visuale/HTML/anteprima, formattazione del codice HTML e libreria media integrata.
- Tabelle, immagini ridimensionabili, riassunti SEO e immagini di sfondo.
- Cestino e cronologia revisioni.
- Gestione profilo e secondo amministratore.
- Homepage personalizzabile, destinazioni categoria, ordinamento e visibilità delle categorie.
- Sistema estensioni ZIP con lettore di fallback zlib.

## Verifiche eseguite in questo ambiente

- Integrità dell'archivio ZIP principale: superata.
- Conteggio archivio principale: 59 elementi.
- Sintassi PHP: superata su tutti i file PHP presenti nel pacchetto.
- Sintassi JavaScript (`assets/js/editor.js`): superata.
- Integrità ZIP del pacchetto dimostrativo: superata.
- Lettura del pacchetto dimostrativo con `CmsZipReader`: superata; manifest e tre file estratti/letti correttamente.

## Verifiche ancora necessarie prima della release stabile 1.0.0

In questo ambiente non è disponibile `pdo_mysql` né `ZipArchive`. Il lettore ZIP fallback è stato provato, ma non è possibile effettuare qui il collaudo end-to-end su MySQL/MariaDB. Prima di dichiarare stabile la 1.0.0 occorre completare il piano di collaudo su hosting o ambiente locale con PHP 8.2+, PDO MySQL e database MySQL/MariaDB:

1. Installazione pulita e configurazione guidata completa.
2. Aggiornamento di una copia di un'installazione 0.9.9 con dati esistenti.
3. Creazione, modifica, pubblicazione, duplicazione, cestino e ripristino pagine.
4. Creazione, modifica, ordinamento, visibilità e destinazione categorie.
5. Verifica editor visuale, HTML, anteprima, tabelle, link, immagini, documenti e bozze locali.
6. Verifica revisioni e ripristino.
7. Verifica amministratore secondario e protezione account principale.
8. Verifica caricamento, attivazione, disattivazione e disinstallazione estensione dimostrativa.
9. Verifica rifiuto di ZIP con path traversal e manifest non valido.
10. Verifica upload e download media, permessi e protezioni del web server.
11. Controllo responsive e navigazione su desktop e smartphone.

La RC1 non deve essere presentata come release stabile finché questi test non sono completati e registrati.
