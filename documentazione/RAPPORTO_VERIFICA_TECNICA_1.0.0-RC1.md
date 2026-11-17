# Rapporto di verifica tecnica — MercurioCMS 1.0.0 RC1

## Esito sintetico

**Esito: verifica statica superata; collaudo integrato non ancora eseguibile in questo ambiente.** Il pacchetto è un candidato di rilascio, non una release stabile certificata.

## Identificazione del pacchetto sorgente

- Archivio sorgente: `mercuriocms-0.9.9-sviluppo.zip`
- Dimensione: 88.907 byte
- Elementi: 59
- Test integrità ZIP: nessun errore
- Versione sorgente: 0.9.9

## Controlli effettuati

| Controllo | Esito |
|---|---|
| Integrità archivio ZIP sorgente | Superato |
| Sintassi PHP | Superata: 21 file PHP |
| Sintassi JavaScript editor | Superata (`node --check`) |
| Integrità ZIP estensione dimostrativa | Superata |
| Lettore ZIP fallback su estensione dimostrativa | Superato: manifest e 3 file letti |
| Disponibilità PDO MySQL nell'ambiente | Non disponibile |
| Disponibilità ZipArchive nell'ambiente | Non disponibile; zlib disponibile |
| Test end-to-end con database | Non eseguito |

## Correzioni e consolidamenti apportati nella RC1

1. Aggiunta una fonte runtime unica per la versione in `VERSION`.
2. Corretto il controllo della versione minima delle estensioni: non usa più una versione 0.9.9 fissa.
3. Aggiornato il parametro di cache-busting dell'editor alla versione runtime.
4. Aggiunti nell'installatore i controlli per Fileinfo e Iconv.
5. Aggiornata la documentazione e uniformati i riferimenti alla candidata RC1.

## Osservazioni tecniche

- Le guide presenti nell'archivio sorgente erano disallineate: l'installazione indicava 0.6.0 e la prima configurazione 0.7.0. Sono state riscritte.
- Il server di verifica dispone di PHP 8.4, PDO, JSON, Fileinfo, Iconv e zlib; non dispone di `pdo_mysql` né dell'estensione `ZipArchive`.
- La mancanza di `pdo_mysql` impedisce di simulare installazione, migrazioni e operazioni applicative sul database.
- La mancanza di `ZipArchive` non impedisce il test del lettore di fallback; il test sul pacchetto di esempio è riuscito.
- I plugin sono codice PHP fidato e non sono isolati in una sandbox. Questa limitazione deve restare esplicita nella documentazione.

## Piano obbligatorio per approvare la 1.0.0 stabile

Eseguire il piano riportato in `NOTE_RILASCIO_1.0.0-RC1.md` su un ambiente PHP 8.2+ con MySQL/MariaDB e `pdo_mysql`, preferibilmente anche sullo stesso hosting di destinazione. Salvare esito, data, versione PHP, versione MySQL/MariaDB e screenshot dei test principali. Correggere ogni anomalia e ripetere i test di regressione prima di cambiare `VERSION` in `1.0.0`.
