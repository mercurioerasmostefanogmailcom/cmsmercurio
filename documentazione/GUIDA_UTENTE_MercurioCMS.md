# MercurioCMS — Guida utente

**Versione documentata:** 1.0.0 RC3

## Accedere al pannello

Aprire l'indirizzo `/admin/` del sito e autenticarsi con le credenziali del primo amministratore create durante l'installazione. Per proteggere il sito usare HTTPS e una password lunga, unica e non riutilizzata.

## Dashboard e struttura dei contenuti

MercurioCMS gestisce due elementi editoriali: **pagine** e **categorie**. Le categorie alimentano il menu pubblico; le pagine costituiscono i contenuti del sito. La homepage automatica mostra le pagine pubblicate ordinate per data di aggiornamento, dalla più recente. È possibile selezionare una pagina pubblicata come homepage dalle Impostazioni.

## Creare una categoria

1. Aprire **Categorie**.
2. Inserire il nome e creare la categoria.
3. Se necessario, modificarne il nome o scegliere una pagina pubblicata da aprire quando il visitatore seleziona la categoria.
4. Usare **Sposta su** e **Sposta giù** per cambiare l'ordine del menu.
5. Usare **Nascondi dal menu** per non mostrare la categoria ai visitatori senza eliminarla. Il comando inverso la rende nuovamente visibile.

Se non viene scelta una pagina di destinazione, la categoria mostra l'elenco delle pagine associate.

## Creare e modificare una pagina

1. Aprire **Pagine** e scegliere **Nuova pagina**.
2. Inserire il titolo. Lo slug può essere generato dal titolo oppure personalizzato.
3. Scrivere un riassunto facoltativo (massimo 500 caratteri), utile per le schede e i metadati descrittivi.
4. Selezionare un'immagine di sfondo facoltativa dalla libreria.
5. Inserire il contenuto, scegliere lo stato e associare una o più categorie.
6. Salvare e controllare il risultato sul sito pubblico.

Gli stati disponibili sono:
- **Bozza:** non visibile ai visitatori.
- **Pubblicata:** compare negli elenchi pubblici e può essere aperta.
- **Pubblicata ma nascosta:** non compare negli elenchi ma può essere raggiunta tramite il collegamento diretto.

## Usare l'editor

L'editor offre tre modalità:
- **Editor visuale:** per scrivere e formattare il contenuto.
- **Codice HTML:** per intervenire direttamente sul markup. Il codice viene indentato per facilitarne la lettura; la formattazione può normalizzare spazi e rientri.
- **Anteprima:** per controllare il contenuto prima del salvataggio.

La barra strumenti consente grassetto, corsivo, sottolineato, paragrafi, titoli, elenchi, citazioni, collegamenti, rimozione dei link, pulizia della formattazione e inserimento tabelle. Le tabelle possono avere una prima riga di intestazione.

Per le immagini e i documenti usare **Apri libreria**, **Inserisci immagine** o **Inserisci documento**. Le immagini possono avere testo alternativo e larghezza impostabile al 10%, 25%, 50%, 75% o 100%. I documenti vengono inseriti come collegamenti scaricabili.

Al salvataggio, il server rimuove i tag e gli attributi non consentiti. Sono ammessi collegamenti HTTP/HTTPS, mailto, percorsi interni e ancore; evitare markup non supportato.

Se si lascia l'editor con modifiche non salvate, il browser può conservare una bozza locale e proporne il recupero al successivo accesso all'editor.

## Libreria media

La libreria accetta immagini JPG/JPEG, PNG, GIF e WEBP e documenti PDF, TXT, DOC, DOCX, XLS, XLSX, PPT e PPTX. Il limite è 10 MB per file. PDF e TXT possono essere visualizzati nel browser secondo le impostazioni del dispositivo; i file Office vengono normalmente scaricati.

I file eliminati vengono spostati nel Cestino. Se un file è già inserito in una pagina, eliminarlo dalla libreria rende non disponibile la relativa immagine o il collegamento finché non viene ripristinato.

## Cestino e cronologia

Il Cestino raccoglie pagine, immagini e documenti eliminati. Da qui è possibile ripristinare gli elementi o cancellarli definitivamente.

Quando si modifica una pagina già esistente, MercurioCMS conserva una revisione precedente. Dalla modifica pagina è possibile ripristinare una delle ultime 30 revisioni. Prima del ripristino, la versione corrente viene conservata nella cronologia.

## Duplicare una pagina

Dall'elenco Pagine usare **Duplica**, inserire il nuovo titolo e confermare. La copia viene creata come bozza con slug univoco; categorie, riassunto e immagine di sfondo vengono mantenuti.

## Impostazioni del sito

Da **Impostazioni** è possibile modificare nome, descrizione, logo, favicon, colori del sito, dell'header e dell'area introduttiva, oltre alla homepage. I campi dell'etichetta e del titolo introduttivo possono essere lasciati vuoti per nascondere le rispettive scritte.

## Amministratori

Dalla sezione Profilo e amministratori è possibile modificare i dati del proprio account e gestire il secondo amministratore secondo le opzioni disponibili. L'amministratore principale è protetto e non può essere disattivato.

## Estensioni

Da **Gestione estensioni** caricare un pacchetto ZIP valido. L'estensione viene installata disattivata; scegliere **Attiva** solo dopo aver verificato la provenienza. **Disattiva** interrompe il caricamento ma conserva i file. **Disinstalla** rimuove il record e la cartella dell'estensione.

Le estensioni sono codice PHP eseguibile con i permessi del sito: installare esclusivamente pacchetti fidati. Il lettore ZIP integrato supporta archivi classici non cifrati STORE/DEFLATE; ZIP64 e archivi cifrati richiedono ZipArchive.

## Backup e sicurezza

Eseguire backup regolari sia del database sia dell'intera cartella del sito. Non condividere `config.php`, non eliminare `storage/` e non lasciare `install.php` accessibile dopo l'installazione. Su server diversi da Apache, configurare protezioni equivalenti a quelle fornite dai file `.htaccess`.

## Aggiornare MercurioCMS

Gli aggiornamenti del programma sono riservati al primo amministratore. Aprire **Aggiornamenti CMS**, selezionare il pacchetto ZIP di aggiornamento e scegliere **Verifica e applica aggiornamento**. Prima di sostituire i file il sistema crea una copia di sicurezza completa dei file dell’installazione e delle tabelle del database. Al termine controllare sito pubblico, pannello, editor e libreria media. Conservare il backup fino al completamento delle verifiche.
