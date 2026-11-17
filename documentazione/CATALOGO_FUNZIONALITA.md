MERCURIOCMS 1.0.0

Riferimento delle funzionalità incluse nel pacchetto base MercurioCMS 1.0.0.

1. INSTALLAZIONE E CONFIGURAZIONE
Installatore web con controllo requisiti, connessione MySQL/MariaDB, scelta del prefisso tabelle e creazione del primo amministratore. Configurazione guidata in sette passaggi e impostazioni successivamente modificabili dal pannello.

2. IDENTITÀ E ASPETTO
Nome e descrizione del sito; logo e favicon separati; personalizzazione dei colori generali, dell’intestazione, del menu, del footer e dell’area introduttiva; anteprime e ripristino dei colori originali. Testi introduttivi personalizzabili e facoltativi.

3. PAGINE
Creazione, modifica, duplicazione, pubblicazione, bozza, pubblicazione nascosta e spostamento nel cestino. Titolo, slug, riassunto facoltativo, contenuto, immagine di sfondo e associazione a una o più categorie.

4. CATEGORIE E NAVIGAZIONE
Creazione, modifica, ordinamento, visibilità indipendente e scelta di una pagina pubblicata come destinazione diretta. La homepage automatica mostra le pagine pubblicate ordinate per data di aggiornamento; è possibile scegliere una pagina pubblicata come homepage.

5. EDITOR
Editor visuale Summernote, codice HTML indentato e anteprima; formattazione, link, pulizia, rimozione collegamenti, tabelle, immagini e documenti dalla libreria. Strumenti aggiuntivi per tabelle e ridimensionamento immagini con percentuali predefinite. Sanitizzazione lato server del markup.

6. LIBRERIA MEDIA E CESTINO
Upload immagini JPG/JPEG, PNG, GIF, WEBP e documenti PDF, TXT, DOC, DOCX, XLS, XLSX, PPT, PPTX; limite applicativo 10 MB per file. Inserimento nell’editor, download controllato dei documenti, cestino con ripristino e cancellazione definitiva.

7. REVISIONI E AMMINISTRATORI
Cronologia delle revisioni delle pagine, ripristino delle versioni conservate, profilo amministratore e gestione del secondo amministratore secondo le regole del pannello. Protezione dell’amministratore principale.

8. ESTENSIONI
Caricamento ZIP dal pannello, manifest plugin.json, identificativo univoco, installazione disattivata, attivazione, disattivazione e disinstallazione. Controlli su percorsi, file, dimensioni, numero di elementi e collegamenti simbolici. Lettore ZIP integrato per archivi classici non cifrati STORE/DEFLATE quando ZipArchive non è disponibile e zlib è presente.
Limiti del caricatore: ZIP massimo 10 MB; massimo 100 elementi; massimo 20 MB complessivi estratti; massimo 5 MB per singolo file. ZIP64 e archivi cifrati richiedono ZipArchive. Le estensioni sono codice PHP fidato e non vengono eseguite in una sandbox.

9. AGGIORNAMENTI E GESTIONE BACKUP
Aggiornamento incrementale dal pannello riservato all’amministratore principale. Verifica manifest e checksum SHA-256, controllo percorsi, backup preventivo di file e database, rollback dei file in caso di errore e ripristino di punti precedenti. I backup nuovi contengono un manifesto SHA-256; la verifica precede il ripristino.
Organizzazione in punti protetti, backup automatici degli aggiornamenti e backup di sicurezza. Pulizia automatica: conserva gli ultimi 5 backup automatici non protetti e rimuove i backup di sicurezza non protetti oltre 7 giorni. I punti protetti non scadono e la procedura impedisce di eliminare l’ultimo punto protetto.

10. SICUREZZA
Password con password_hash/password_verify; sessioni amministrative con cookie HttpOnly e SameSite Lax e flag Secure su HTTPS; rigenerazione ID sessione dopo il login; token CSRF nelle operazioni amministrative POST; query parametrizzate; protezioni .htaccess per configurazione, storage e cartelle upload.

11. NON INCLUSO NEL PACCHETTO BASE
Gestione di articoli, prodotti, utenti pubblici o tassonomie aggiuntive; e-commerce, commenti, forum, newsletter, statistiche integrate; app Android; sandbox per codice PHP di terze parti; garanzia automatica di conformità privacy/cookie. Queste funzioni richiederebbero estensioni o sviluppo separato.
