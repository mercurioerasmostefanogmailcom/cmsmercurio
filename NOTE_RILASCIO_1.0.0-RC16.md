# MercurioCMS 1.0.0-RC16 — Correzione inizializzazione editor

## Correzione bloccante
- Evitata l'esecuzione prematura del callback `onChange` di Summernote durante il caricamento del contenuto iniziale.
- L'integrazione abilita sincronizzazione e salvataggio bozze soltanto dopo il completamento delle dichiarazioni e dell'inizializzazione dell'editor.
- Questo errore interrompeva l'esecuzione di `editor.js` e impediva l'avvio delle modalità Visuale/HTML/Anteprima, della barra contestuale e della libreria media.

## Funzioni mantenute
- Barra flottante contestuale delle tabelle RC15.
- Regolazione rapida, struttura e allineamento/aspetto delle tabelle.
- Modalità Visuale, Codice HTML e Anteprima.
- Libreria media e inserimento di immagini e documenti.
- Conservazione di CSS, classi, ID e stili gestiti dalle RC precedenti.

## Installazione
Pacchetto incrementale da **MercurioCMS 1.0.0-RC15** a **1.0.0-RC16**. Eseguire prima un backup. Non installare su RC14 o versioni precedenti.

## Verifiche
- Sintassi JavaScript verificata con Node.js.
- Sintassi PHP verificata con PHP CLI.
- Integrità ZIP e hash SHA-256 del manifesto verificati.
- La verifica funzionale completa va confermata nell'installazione locale.
