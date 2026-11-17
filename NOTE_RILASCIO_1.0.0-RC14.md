# MercurioCMS 1.0.0-RC14 — Conservazione CSS e regolazione rapida tabelle

## Contenuto della versione

Questa RC14 ripropone le correzioni tecniche e funzionali preparate nella RC13, ma con un nuovo numero di release perché la RC13 era già stata installata.

### Salvataggio HTML e CSS

- Conservazione dei blocchi CSS `<style>`.
- Conservazione dei collegamenti ai fogli di stile tramite `<link rel="stylesheet">`.
- Conservazione degli attributi `class`, `id` e `style`.
- Conservazione dei contenitori HTML comuni, tra cui `div`, `span`, `section`, `article`, `header`, `footer` e `figure`.
- Mantenimento degli stili durante salvataggio, riapertura, modifica e visualizzazione del contenuto.
- Restano esclusi script, gestori di eventi HTML e URL con schemi pericolosi.

La possibilità di inserire CSS personalizzato è destinata agli amministratori che modificano il contenuto delle pagine.

### Barra strumenti tabelle

- Rimossa la sezione **Dimensioni** con campi numerici e pulsanti Applica.
- Rimane la sola sezione **Regolazione rapida**, con i comandi Larghezza −, Larghezza +, Altezza − e Altezza +.
- Restano disponibili le sezioni Struttura e Allineamento e aspetto.

## File aggiornati

- `app/bootstrap.php` — pulizia HTML compatibile con CSS e classi;
- `assets/js/editor.js` — conservazione delle risorse CSS nei passaggi dell'editor e barra tabelle;
- `VERSION` — aggiornamento a RC14.

## Installazione

Pacchetto incrementale da **MercurioCMS 1.0.0-RC13** a **1.0.0-RC14**. È destinato a chi ha già installato la precedente RC13. Non installare su RC12 o versioni precedenti. Prima dell'aggiornamento effettuare un backup; la procedura di aggiornamento di MercurioCMS crea inoltre il proprio backup automatico.

## Verifiche tecniche

I file applicativi sono gli stessi già preparati per la RC13; cambia la release e viene aggiornato il manifesto di aggiornamento con la nuova versione minima e gli hash SHA-256. La sintassi e l'integrità del pacchetto sono verificate. È comunque necessaria la prova funzionale nell'installazione locale: salvare una pagina con CSS, riaprirla, modificarla e controllare la resa pubblica.
