# MercurioCMS 1.0.0-RC21 — revisione correttiva 1

Questa revisione mantiene la linea RC21 e corregge la gestione dei backup e dei punti di ripristino.

## Modifiche

- La schermata mostra sempre le tre sezioni di gestione, anche quando non esistono ancora backup: punti protetti, backup automatici e backup di sicurezza.
- È possibile creare in qualsiasi momento un punto di ripristino completo della versione attualmente installata. La copia viene verificata con il manifesto SHA-256 e protetta automaticamente.
- Se non esiste ancora alcun punto protetto, il primo backup completo creato prima di un aggiornamento viene protetto automaticamente: il sistema conserva così almeno una versione ripristinabile.
- È disponibile l'eliminazione cumulativa: si sceglie un backup e si eliminano quello e tutti i backup più vecchi non protetti. I punti protetti vengono sempre saltati.
- Prima di un'eliminazione singola o cumulativa, se non esiste alcun punto protetto, il backup completo più recente viene verificato e protetto automaticamente.
- La gestione continua a distinguere i punti protetti, gli ultimi 5 backup automatici non protetti e i backup di sicurezza non protetti, con scadenza dopo 7 giorni.
- I punti protetti non possono essere eliminati dalla pulizia automatica. Non è possibile rimuovere la protezione dall'ultimo punto protetto.
- I backup restano copie complete e indipendenti di file e database; il ripristino verifica l'integrità prima di intervenire e crea prima una copia di sicurezza della situazione corrente.

## Uso consigliato

1. Dopo aver applicato questa revisione, aprire **Aggiornamenti** e verificare che compaia il punto protetto creato automaticamente prima dell'aggiornamento.
2. Se si desidera fotografare anche lo stato appena aggiornato, usare **Crea punto di ripristino protetto adesso**.
3. Per ridurre una lista lunga, usare **Elimina questo e i più vecchi**. I punti protetti non saranno cancellati.
4. Usare **Pulisci backup non necessari** per mantenere gli ultimi 5 backup automatici e rimuovere i backup di sicurezza scaduti.

Aggiornamento incrementale da MercurioCMS 1.0.0-RC21. La revisione tecnica è identificata come `1.0.0-rc21.1`; non introduce una nuova RC22.
