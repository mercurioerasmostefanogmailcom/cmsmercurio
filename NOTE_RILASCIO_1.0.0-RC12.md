# MercurioCMS 1.0.0-RC12 — Correzione definitiva editor tabelle

## Correzione principale

Summernote Lite 0.9.1 nasconde l'elemento originale `#visual-editor` e crea il wrapper `.note-editor` come elemento fratello. Le versioni precedenti cercavano il wrapper con `editor.closest('.note-editor')`: la ricerca restituiva `null`, impedendo l'individuazione della zona di scrittura e della cella attiva.

RC12 individua il wrapper reale dopo l'inizializzazione di Summernote e usa lo stesso riferimento per selezione celle, modalità visuale, sincronizzazione HTML e gestione delle immagini.

## Funzioni tabella

- Barra aggiuntiva integrata nel popover tabella nativo di Summernote, senza una seconda barra indipendente.
- Larghezza e altezza della cella.
- Larghezza della colonna e altezza della riga.
- Ridimensionamento rapido.
- Allineamento orizzontale e verticale.
- Colore di sfondo della cella.
- Eliminazione della cella, mantenendo almeno una cella per riga.
- Conservazione di dimensioni, allineamenti, colori e attributi `rowspan`/`colspan` nel passaggio tra editor visuale e HTML.
- Larghezza massima del popover limitata per evitare che occupi tutta la pagina.

## Compatibilità e installazione

Aggiornamento cumulativo da MercurioCMS 0.9.9 o superiore. Porta il nucleo alla base RC4 e integra tutte le correzioni editor fino a RC12, compresi i font delle icone Summernote. Richiede che la pagina Aggiornamenti sia stata abilitata tramite il bridge 0.9.9. Non sovrascrive `config.php`, il database, `uploads/`, `storage/` o i contenuti delle estensioni installate.

## Verifiche eseguite

- JavaScript verificato con Node.js.
- Prova browser automatizzata con Summernote Lite 0.9.1: selezione cella, visualizzazione del popover, larghezza/altezza cella, larghezza colonna, altezza riga, allineamenti, colore di sfondo, eliminazione cella, sincronizzazione HTML e passaggio visuale/HTML.
- Nessun errore JavaScript nella prova.

La verifica è stata eseguita in ambiente di test isolato; resta necessaria la prova finale sulla specifica installazione Aruba.
