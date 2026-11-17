# MercurioCMS 1.0.0-RC18 — Correzione barra strumenti editor

## Correzione bloccante
- Corretta l'inserzione della barra contestuale delle tabelle nel DOM di Summernote.
- In RC17 la barra veniva inserita con `noteEditor.insertBefore(tableTools, editable)`, ma `.note-editable` non è un figlio diretto di `.note-editor`: è contenuto in `.note-editing-area`.
- Il browser generava quindi `NotFoundError`, interrompendo l'esecuzione del resto di `editor.js`. Questo impediva la comparsa della barra e bloccava anche modalità HTML, anteprima, libreria media e pulsanti.
- RC18 inserisce la barra come elemento fratello di `.note-editing-area`, immediatamente sotto la toolbar principale. È previsto un fallback per markup differenti.

## Funzioni mantenute
- Correzione PHP del salvataggio di attributi e stili introdotta in RC17.
- Barra contestuale in fascia dedicata, senza sovrapporsi alla toolbar o all'area di scrittura.
- Regolazione rapida, struttura e allineamento/aspetto delle tabelle.
- Modalità Visuale, Codice HTML e Anteprima.
- Libreria media e inserimento di immagini/documenti.
- Conservazione di CSS, classi, ID e stili gestiti dalle versioni precedenti.

## Installazione
Pacchetto incrementale da **MercurioCMS 1.0.0-RC17** a **1.0.0-RC18**. Eseguire un backup prima dell'aggiornamento.

## Verifiche eseguite
- Sintassi JavaScript verificata con Node.js.
- Sintassi PHP verificata con PHP CLI.
- Integrità ZIP e hash SHA-256 del manifesto verificati.
- Controllato il punto d'inserimento DOM: la barra viene inserita prima dell'area di scrittura usando il suo genitore effettivo.
- La verifica funzionale completa resta da confermare nell'installazione locale del browser.
