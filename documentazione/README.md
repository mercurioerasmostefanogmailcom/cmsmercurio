# MercurioCMS 1.0.0

Prima release del CMS essenziale basato su Pagine e Categorie.

## Installazione rapida
1. Estrarre il pacchetto e copiare la cartella `mercuriocms` sul server web.
2. Creare un database MySQL/MariaDB e un prefisso tabelle dedicato.
3. Aprire `install.php` dal browser e completare l’installazione.
4. Accedere a `/admin/` e completare la configurazione guidata.

## Documentazione
Consultare `documentazione/` per installazione, prima configurazione, guida utente, catalogo funzioni, note di rilascio e rapporto di verifica.

## Aggiornamenti
Per partire da 0.9.9 installare prima il bridge che rende disponibile il gestore aggiornamenti, quindi caricare il pacchetto cumulativo 0.9.9 → 1.0.0. Eseguire sempre un backup esterno prima dell’aggiornamento.

## Verifica
Il pacchetto ha superato i controlli ZIP, SHA-256 dei manifest, sintassi PHP (22 file, PHP 8.4.24) e sintassi JavaScript. Il collaudo integrato con MySQL/MariaDB e browser deve essere completato nell’ambiente di destinazione.
