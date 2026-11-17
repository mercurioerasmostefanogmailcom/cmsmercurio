# MercurioCMS 1.0.0-RC17 — Barra tabelle integrata nell'editor

## Modifica
- La barra contestuale delle tabelle non segue più la cella con posizionamento fisso sopra la pagina.
- Ora compare in una fascia dedicata subito sotto la toolbar principale di Summernote e prima dell'area di scrittura.
- La fascia occupa spazio nel layout dell'editor: non copre i pulsanti dell'editor, il testo o le altre parti della pagina.
- La barra appare quando viene selezionata una cella e scompare quando la selezione viene rimossa o si cambia modalità.
- Su schermi stretti i gruppi di comandi si dispongono in una colonna.

## Correzione salvataggio pagine
- Corretto il parser degli attributi HTML in `sanitize_page_html()`: i gruppi opzionali della regex vengono gestiti in modo sicuro anche quando un attributo è non quotato o manca un gruppo di cattura.
- Eliminati gli avvisi `Undefined array key 3` e il `TypeError` di `html_entity_decode()` segnalati durante il salvataggio.

## Funzioni mantenute
- Inizializzazione protetta dell'editor introdotta in RC16.
- Modalità Visuale, Codice HTML e Anteprima.
- Libreria media e inserimento di immagini/documenti.
- Comandi tabella: regolazione rapida, struttura, allineamento e aspetto.
- Conservazione di CSS, classi, ID e stili gestiti dalle versioni precedenti.

## Installazione
Pacchetto incrementale da **MercurioCMS 1.0.0-RC16** a **1.0.0-RC17**. Eseguire un backup prima dell'aggiornamento.

## Verifiche
- Sintassi JavaScript verificata con Node.js.
- Sintassi PHP verificata con PHP CLI.
- Integrità ZIP e hash SHA-256 del manifesto verificati.
- La verifica funzionale completa va confermata nell'installazione locale.
