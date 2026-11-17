# MercurioCMS 1.0.0-RC4

Questa candidata corregge un errore individuato durante il test dell'aggiornamento da una versione precedente: il controllo di sicurezza rifiutava file collocati in sottocartelle non ancora presenti nell'installazione.

Il controllo ora verifica ogni componente già esistente, rifiuta collegamenti simbolici e destinazioni fuori dalla cartella del CMS, ma permette la creazione delle directory mancanti necessarie ai file del pacchetto.

Prima di ogni aggiornamento viene mantenuta la procedura di backup prevista. Eseguire il test esclusivamente su una copia dell'installazione.
