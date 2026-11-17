# MercurioCMS 1.0.0 — Release

Prima release stabile ricostruita dal pacchetto cumulativo 0.9.9 → RC12 e dalla sequenza di aggiornamenti RC12 → RC21-rev1.

## Funzioni principali
- Gestione essenziale di Pagine e Categorie, con homepage e menu configurabili.
- Editor visuale Summernote, codice HTML, anteprima, strumenti per tabelle e libreria media.
- Gestione di immagini, documenti, cestino e revisioni delle pagine.
- Estensioni ZIP installabili e attivabili dall’amministrazione.
- Aggiornamenti con backup, punti protetti, verifica SHA-256 e ripristino.

## Gestione backup
I backup automatici non protetti vengono mantenuti fino agli ultimi 5; i backup di sicurezza non protetti scadono dopo 7 giorni. I punti protetti non scadono e non vengono rimossi dalla pulizia automatica.

## Verifica
Integrità ZIP e manifest SHA-256 superati; 22 file PHP senza errori di sintassi con PHP 8.4.24; controllo JavaScript superato. Il collaudo integrato MySQL/MariaDB e browser deve essere completato sull’ambiente di destinazione prima della distribuzione pubblica. Vedere `documentazione/06_Rapporto_verifica.docx`.
