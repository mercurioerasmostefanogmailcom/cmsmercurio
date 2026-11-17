# Changelog MercurioCMS

## 1.0.0-RC4
- Corretto il controllo dei percorsi dell'aggiornamento: ora consente la creazione di directory intermedie mancanti, mantenendo i controlli contro symlink e percorsi esterni.

- Integrata la gestione degli aggiornamenti del CMS nel pannello amministrativo, riservata al primo amministratore.
- Aggiunto caricamento ZIP con manifest, controllo della versione, validazione dei percorsi e verifica SHA-256 dei file.
- Aggiunto backup preventivo completo di file e database prima dell’aggiornamento.
- Protetti config.php, uploads/, storage/, plugins/ e .git/ dalla sovrascrittura tramite pacchetto.
- Aggiunto ripristino dei file già sostituiti se l’applicazione del pacchetto si interrompe.
- Aggiornate le istruzioni di aggiornamento, installazione e guida utente.

# Changelog MercurioCMS

## 1.0.0-RC2 - 2026-11-05
- Integrato Summernote Lite 0.9.1 come editor visuale delle pagine, con interfaccia in italiano.
- Aggiunto jQuery 3.6.0 come dipendenza locale: l'editor non dipende da CDN esterni.
- Conservate le modalità Codice HTML con indentazione e Anteprima.
- Collegata la selezione e l'inserimento di immagini e documenti alla libreria media interna di MercurioCMS.
- Conservati il recupero delle bozze locali e i controlli di sanificazione HTML lato server.
- Aggiunti gli avvisi e i testi delle licenze MIT di Summernote e jQuery.

## 1.0.0-RC1 - 2026-11-05
- Preparata una candidata al rilascio con revisione e riallineamento della documentazione.
- Centralizzata la versione runtime nel file VERSION.
- Corretto il controllo della versione minima richiesta dalle estensioni, prima fissato a 0.9.9.
- Aggiornato il versionamento della cache dello script editor.
- Aggiunti controlli preliminari Fileinfo e Iconv nell'installatore.
- Aggiunti catalogo funzionalità, note di rilascio e rapporto di verifica tecnica.


## 0.9.9 - 2026-11-04
- Sostituito il formatter HTML precedente con un formatter strutturale basato sul parser HTML del browser, per indentare gli elementi annidati e gestire correttamente attributi contenenti caratteri speciali.
- Aggiunto versionamento dell'URL dello script editor per evitare che il browser continui a usare una copia JavaScript in cache.
- Aggiunto un lettore ZIP integrato di fallback basato su zlib: l'installazione di estensioni standard non richiede più necessariamente l'estensione ZipArchive.
- Il fallback supporta ZIP classici non cifrati con compressione STORE/DEFLATE; ZIP64 e archivi cifrati richiedono ZipArchive.
- Aggiornate guida, documentazione tecnica e istruzioni di aggiornamento.

## 0.9.8 - 2026-11-04
- Nell'editor, la visualizzazione del codice HTML applica una formattazione con indentazione per facilitarne la lettura e la modifica.
- Introdotto il registro delle estensioni e la gestione dal pannello amministrativo: installazione ZIP, attivazione, disattivazione e disinstallazione.
- Aggiunti controlli sui pacchetti: manifest obbligatorio, identificativi e percorsi validati, limiti su dimensioni e numero di file, rifiuto di collegamenti simbolici e protezione dai percorsi ZIP non sicuri.
- Le estensioni installate sono inizialmente disattivate; i file del core non vengono sovrascritti.


## 0.9.7
- Aggiunta la possibilità di nascondere una categoria dal menu pubblico senza eliminarla; la categoria resta gestibile e può essere resa nuovamente visibile.
- Aggiunti i comandi per spostare le categorie verso l'alto o verso il basso e definire l'ordine del menu pubblico.
- Le nuove categorie vengono aggiunte in fondo al menu.
- Aggiunta la migrazione automatica non distruttiva del campo `is_visible`; le categorie già esistenti restano visibili.
- Migliorati i metadati pubblici per le anteprime dei collegamenti: favicon con URL assoluto, Open Graph e Twitter Card; il logo del sito viene usato come immagine di anteprima quando disponibile.
- Corretta la struttura dei moduli di gestione categoria, separando i moduli di modifica, ordinamento, visibilità ed eliminazione.


## 0.9.6
- Aggiunta la modifica delle categorie esistenti (nome e pagina di destinazione).
- Una categoria può aprire direttamente una pagina pubblicata; se non è selezionata una pagina, continua a mostrare l'elenco dei contenuti.
- Aggiunta la scelta di una pagina pubblicata come homepage; è possibile ripristinare la homepage automatica.
- Aggiornamento automatico e non distruttivo del database per il nuovo campo `landing_page_id` nelle categorie.


## 0.6.0
- Estesa la configurazione guidata da quattro a sette passaggi.
- Inserita nella procedura iniziale l'installazione del modulo Pagine e Categorie.
- La procedura richiede almeno una categoria e almeno una pagina prima del completamento.
- Creata una pagina di prova iniziale salvata come bozza e associata alla prima categoria.
- Aggiunta una barra laterale persistente nel pannello amministrativo.
- Riunite nella navigazione laterale Dashboard, Pagine, Categorie, Configurazione guidata, Impostazioni, Visualizza sito ed Esci.
- Resa la barra laterale adattiva per schermi piccoli.
- Conservata la compatibilità del percorso dashboard per installazioni 0.5.0 in cui il modulo contenuti non è ancora installato.


## 0.5.0
- Aggiunta configurazione guidata iniziale in quattro passaggi: identità, logo/favicon, colori e riepilogo.
- Il primo accesso amministrativo avvia automaticamente la procedura guidata.
- Aggiunta la pagina Impostazioni per modificare successivamente tutte le personalizzazioni.
- Aggiunta gestione sicura del caricamento di logo e favicon con verifica MIME, formato e dimensione.
- Il front-end pubblico applica nome, descrizione, logo, favicon e palette colori configurati.
- Conservata la separazione delle installazioni tramite file di configurazione e prefisso database dedicati.


## 0.4.0
- Aggiunta la pagina pubblica principale `index.php` per evitare l'accesso Forbidden alla radice e mostrare i contenuti pubblicati.
- Aggiunta navigazione pubblica per categorie e pagine.
- Installatore: password amministratore minima ridotta a 9 caratteri.
- Installatore: supporto a database esistenti con controllo del prefisso tabelle libero.
- Installatore: rimosso l'uso improprio delle transazioni attorno a DDL MySQL e aggiunta pulizia delle tabelle create nel tentativo fallito.
- Dashboard riorganizzata con Stato moduli a sinistra e Contenuti a destra.
- Percorso guidato per installare il modulo, creare categorie e creare pagine.
- Eliminati i pulsanti di reinstallazione ridondanti quando il modulo è già attivo.
- Uniformato il pulsante Esci e reso Gestisci categorie un pulsante.
- Nomi dei vincoli esterni del modulo resi specifici per prefisso, per consentire più installazioni nello stesso database.

## 0.3.1
- Correzione della gestione delle transazioni MySQL durante l'installazione.
- Gestione dell'utente amministratore già inserito in un tentativo precedente.

## 0.3.0
- Prima versione consolidata del CMS, pannello amministrativo, modulo pagine e categorie ed editor visuale di base.


## 0.7.0 - Editor pagine visuale e HTML
- Aggiunta la commutazione tra editor visuale, codice HTML e anteprima.
- Aggiunti titolo 4, rimozione collegamento e strumenti di formattazione.
- Aggiunta una conversione prudente del codice HTML quando si torna alla modalità visuale.
- Rafforzata la sanitizzazione lato server: vengono mantenuti solo tag e collegamenti consentiti.
- Nessuna modifica allo schema del database; aggiornamento senza migrazioni.

## 0.8.0
- Aggiunti colori indipendenti per sfondo e testo dell'header, con anteprima in Impostazioni.
- Aggiunto cestino con ripristino e cancellazione definitiva delle pagine.
- Aggiunta cronologia delle versioni delle pagine con ripristino della versione precedente.
- Aggiunta libreria media per immagini e documenti, con inserimento diretto nell'editor.
- Aggiunta migrazione database automatica e non distruttiva per i nuovi campi e le nuove tabelle.


## 0.8.1
- Corretto il passaggio Aspetto e colori della prima configurazione: ora include anche i colori dell'header.
- Aggiunta la personalizzazione separata dei colori dell'area introduttiva della home: sfondo, etichetta “Sito web”, titolo “Benvenuto” e descrizione.
- Aggiunte anteprime interattive nella procedura guidata e nelle Impostazioni.
- Nessuna modifica al database: le nuove preferenze usano le impostazioni già disponibili.


## 0.8.2
- Libreria media: indicati chiaramente formati ammessi e limite di 10 MB.
- Caricamento documenti reso più compatibile con i MIME rilevati per DOC/DOCX, XLS/XLSX e PPT/PPTX.
- Distinta la visualizzazione nel browser (immagini, PDF e TXT) dal download dei documenti Office.
- Aggiunto download controllato dei documenti tramite download.php.
- Esteso il cestino a immagini e documenti, con ripristino e cancellazione definitiva.
- I collegamenti alla libreria media dall'editor si aprono in una nuova scheda.
- Aggiunto recupero della bozza non salvata dell'editor tramite sessione del browser.
- Migliorata la conservazione del punto di inserimento quando si aggiungono immagini o collegamenti.
- Migrazione automatica non distruttiva del campo media.deleted_at.

## 0.9.0
- Aggiunti i testi personalizzabili della pagina iniziale (“scritta piccola” e “titolo principale”), anche vuoti, sia nel wizard sia nelle Impostazioni.
- Rifatto il passaggio tra editor visuale, codice HTML e anteprima con sincronizzazione del contenuto prima del salvataggio.
- Introdotta una libreria media integrata nell'editor: selezione, caricamento AJAX, inserimento immagini con testo alternativo e collegamenti a documenti senza cambiare pagina.
- Il cestino distingue ora pagine, immagini e documenti.
- Aggiornate le istruzioni e i controlli tecnici della versione.


## 0.9.1 — revisione funzionale (sviluppo)
- Aggiunta la pagina Profilo e amministratori: modifica credenziali del proprio account, creazione e disattivazione del secondo amministratore.
- Aggiunto aggiornamento automatico non distruttivo dello schema amministratori (`display_name`, `is_active`).
- Protetto l'account principale (ID 1) dalla disattivazione.
- Aggiunta duplicazione pagina con nuovo titolo, nuovo slug univoco, categorie copiate e stato iniziale Bozza.
- Aggiunta anteprima colori più completa durante la configurazione guidata, con aggiornamento immediato degli elementi principali.
- Aggiunti pulsanti dedicati per inserire immagini e documenti nell'editor.
- Migliorata la conservazione della selezione del testo quando si usano i comandi della barra strumenti.


## 0.9.2 — sviluppo
- Il secondo amministratore può essere eliminato definitivamente; la creazione è disponibile soltanto quando non esiste un secondo account. L'amministratore principale resta protetto.
- Migliorata la conservazione della selezione nell'editor e la rimozione del collegamento quando il cursore si trova al suo interno.
- Aggiunto il campo Riassunto della pagina (massimo 500 caratteri), utilizzato nella descrizione SEO e nelle schede pubbliche.
- Il riassunto viene mantenuto nelle duplicazioni e nella cronologia delle revisioni.
- Aggiunte migrazioni automatiche non distruttive per `excerpt` in `pages` e `page_revisions`.
- Affiancata la palette colori alle anteprime nelle Impostazioni e aggiunto il ripristino dei colori originali.
- Aggiornate le istruzioni di aggiornamento e la verifica tecnica.

## 0.9.3 - Migliorie editor e pagine
- Ridimensionamento immagini inserite nell'editor tramite controllo percentuale (25%, 50%, 75%, 100%); la dimensione viene salvata nel contenuto.
- Corretto il salvataggio delle bozze locali: l'evento di uscita pagina non ricrea più la bozza subito dopo l'invio del modulo, evitando la richiesta di ripristino ripetuta.
- Riassunto generato automaticamente dal contenuto testuale (e dai testi alternativi/nome file delle immagini) quando il campo è lasciato vuoto.
- Possibilità di scegliere un'immagine di sfondo per ogni pagina dalla libreria media; la scelta viene mantenuta nelle revisioni e nelle duplicazioni.
- Impostazioni colori riordinate in gruppi funzionali con codici esadecimali visibili e anteprime.
- Migrazione automatica non distruttiva per i nuovi campi immagine di sfondo.


## 0.9.4 — correzioni editor e indice
- Aggiunta la misura 10% al controllo di ridimensionamento delle immagini nell'editor.
- Le schede delle pagine nell'indice pubblico mostrano anche l'immagine di sfondo impostata per la pagina, con una velatura chiara per mantenere leggibili titolo e riassunto.
- Corretto il recupero delle bozze locali: ogni bozza è associata alla versione salvata da cui deriva; le bozze obsolete vengono eliminate e non viene proposto il recupero se non ci sono modifiche effettive.
- La navigazione fuori dall'editor salva una bozza solo quando il contenuto differisce dalla versione iniziale caricata.


## 0.9.5 — rifinitura indice e tabelle nell'editor
- Quando una pagina ha un'immagine di sfondo, la relativa scheda nell'indice mostra soltanto il titolo cliccabile; riassunto e collegamento aggiuntivo vengono nascosti.
- Aggiunto il comando Inserisci tabella nell'editor visuale: consente di scegliere righe e colonne e, facoltativamente, usare la prima riga come intestazione.
- Le celle della tabella possono contenere testo, formattazione e immagini inserite dalla libreria media.
- Aggiornato il filtro HTML per conservare in modo sicuro la struttura delle tabelle e aggiunti stili adattivi per la visualizzazione pubblica e nell'editor.


## 1.0.0 — 2026-11-05
Ricostruzione release da RC12 a RC21-rev1; consolidamento documentazione e gestione backup.
