# MercurioCMS 1.0.0-RC15 — Ripristino barra flottante, modalità editor e libreria media

## Correzioni principali

- La barra personalizzata delle tabelle è ora realmente flottante e indipendente dal popover nativo di Summernote; compare accanto alla cella selezionata e segue la cella durante lo scorrimento e il ridimensionamento della finestra.
- Nascosti i comandi tabella nativi che si sovrapponevano alla barra personalizzata.
- Conservate le sezioni Regolazione rapida, Struttura e Allineamento e aspetto. La sezione Dimensioni e i relativi campi numerici/Applica non vengono ripristinati.
- Ripristinata la selezione contestuale della cella anche dopo l'uso dei comandi della barra.
- Mantenute le correzioni RC13/RC14 per conservazione di CSS, classi, ID, stili inline e contenitori HTML nei passaggi Visuale/HTML/Anteprima.
- Mantenuti i comandi della libreria per immagini e documenti, con salvataggio e ripristino del punto di inserimento nell'editor.
- Mantenuta la funzione di anteprima e la formattazione leggibile del codice HTML.

## File inclusi

- `app/bootstrap.php` — conserva la pulizia HTML introdotta in RC13/RC14.
- `assets/js/editor.js` — barra tabella flottante, passaggi Visuale/HTML/Anteprima e integrazione libreria media.
- `VERSION` — aggiornamento a `1.0.0-rc15`.

## Installazione

Pacchetto incrementale da **MercurioCMS 1.0.0-RC14** a **1.0.0-RC15**. Non installare su versioni precedenti. Effettuare un backup prima dell'aggiornamento; il sistema esegue inoltre il proprio backup automatico.

## Verifiche eseguite sul pacchetto

- Sintassi JavaScript verificata con Node.js.
- Sintassi PHP verificata con PHP CLI.
- Integrità ZIP e hash SHA-256 del manifesto verificati.
- Verificata la presenza dei riferimenti ai pulsanti Libreria, Inserisci immagine e Inserisci documento.

La prova funzionale conclusiva va eseguita nell'installazione locale: selezione cella, comandi tabella, passaggio Visuale/HTML/Anteprima e inserimento effettivo di un'immagine e di un documento dalla libreria.
