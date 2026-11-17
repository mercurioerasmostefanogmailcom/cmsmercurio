# MercurioCMS 1.0.0-RC19

## Ripristino di una versione precedente

- Aggiunta la funzione di ripristino dei backup automatici creati prima degli aggiornamenti.
- Il ripristino richiede conferma esplicita e digitazione della parola `RIPRISTINA`.
- Prima di iniziare viene creato un nuovo backup di sicurezza completo di file e database.
- Il ripristino riporta file e database allo stato del backup selezionato; `config.php`, `uploads/`, `plugins/` e `storage/` sono preservati.
- In caso di errore viene tentato il recupero automatico dalla copia di sicurezza appena creata.
- I backup non vengono cancellati automaticamente.

## Importante

Il database viene riportato alla data del backup: le modifiche a pagine, categorie, account e impostazioni effettuate successivamente possono andare perse. Prima di ripristinare, verificare la data e la versione indicate.
