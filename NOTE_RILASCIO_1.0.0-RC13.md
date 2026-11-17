# MercurioCMS 1.0.0-RC13 — Conservazione degli stili CSS e regolazione rapida tabelle

## Correzione del salvataggio HTML/CSS

RC13 corregge la pulizia del contenuto delle pagine che, nelle versioni precedenti, eliminava i blocchi CSS e diversi elementi necessari per applicare fogli di stile personalizzati.

Ora il contenuto della pagina conserva:

- blocchi CSS `<style>`;
- collegamenti a fogli di stile tramite `<link rel="stylesheet">`;
- attributi `class` e `id`, necessari per applicare regole CSS;
- attributi `style` inline;
- contenitori HTML comuni come `div`, `span`, `section`, `article`, `header`, `footer`, `figure` e gli elementi di intestazione.

La conservazione è applicata sia durante il salvataggio, sia quando il contenuto viene riaperto nell'editor e visualizzato sul sito. Il bridge dell'editor conserva separatamente i blocchi CSS e i link ai fogli di stile durante i passaggi tra modalità visuale, HTML e anteprima, evitando che Summernote li rimuova.

Restano esclusi script, gestori di eventi HTML e URL con schemi pericolosi. La possibilità di inserire CSS personalizzato è destinata agli amministratori che modificano il contenuto delle pagine.

## Barra strumenti tabelle

Resta la barra contestuale introdotta nelle RC precedenti, con la sezione **Regolazione rapida** al posto dei campi numerici e dei pulsanti Applica. Le azioni rapide permettono di aumentare o ridurre larghezza della cella e altezza della riga selezionata. Restano disponibili le sezioni Struttura e Allineamento e aspetto.

## File aggiornati

- `app/bootstrap.php` — pulizia HTML compatibile con CSS e classi;
- `assets/js/editor.js` — conservazione delle risorse CSS nei passaggi dell'editor;
- `VERSION` — aggiornamento versione.

## Installazione

Pacchetto incrementale da **MercurioCMS 1.0.0-RC12** a **1.0.0-RC13**. Non installare su RC11 o versioni precedenti. Prima dell'aggiornamento effettuare comunque un backup; la procedura di aggiornamento di MercurioCMS crea inoltre il proprio backup automatico.

## Verifiche tecniche

- Sintassi PHP verificata;
- sintassi JavaScript verificata con Node.js;
- test della funzione di pulizia HTML su blocchi `<style>`, link CSS, classi, ID, stili inline e rimozione di script/attributi evento;
- integrità ZIP e hash SHA-256 verificati.

È ancora necessaria la prova funzionale nell'installazione locale: salvare una pagina con CSS, riaprirla, modificarla e controllare la resa pubblica.
