# Gest Web Rent

Gest Web Rent e un plugin WordPress per gestire veicoli a noleggio, pubblicare un catalogo frontend e ricevere richieste tramite WhatsApp Business o email.

## Requisiti

- WordPress 6.0 o superiore.
- PHP 7.4 o superiore.
- Permalink WordPress attivi consigliati.
- Repository GitHub pubblica o privata con release taggate `v*`.

## Installazione manuale

1. Carica la cartella `gest-web-rent` in `wp-content/plugins/`.
2. Attiva il plugin da **Plugin > Plugin installati**.
3. Vai in **Impostazioni > Gest Web Rent** e configura WhatsApp Business/email.
4. Crea i veicoli dal menu **Gest Web Rent**.

## Installazione da ZIP

1. Scarica `gest-web-rent.zip` dalla release GitHub.
2. In WordPress apri **Plugin > Aggiungi nuovo > Carica plugin**.
3. Seleziona lo ZIP e installa.
4. Attiva il plugin.

Lo ZIP deve contenere la cartella principale `gest-web-rent/`, non soltanto i file sciolti.

## Configurazione WhatsApp Business

In **Impostazioni > Gest Web Rent** inserisci il numero WhatsApp Business in formato internazionale, per esempio `+393331234567`.

Nel frontend il plugin genera link `wa.me` con un messaggio precompilato riferito al veicolo selezionato.

## Configurazione email

In **Impostazioni > Gest Web Rent** inserisci l'indirizzo email che deve ricevere le richieste di noleggio.

Il catalogo e la scheda veicolo generano un link `mailto:` con oggetto precompilato.

## Uso shortcode

Catalogo veicoli:

```text
[gest_web_rent_catalog]
```

Catalogo con limite:

```text
[gest_web_rent_catalog limit="6"]
```

Scheda singolo veicolo:

```text
[gest_web_rent_vehicle id="123"]
```

## Gestione veicoli

Dal menu **Gest Web Rent** puoi creare veicoli come contenuti WordPress. Ogni veicolo supporta:

- titolo;
- descrizione;
- riassunto;
- immagine in evidenza;
- prezzo;
- posti;
- alimentazione;
- cambio;
- note di disponibilita.

## Gestione disponibilita

La disponibilita viene salvata come metadato del veicolo e mostrata nella scheda frontend. Gli aggiornamenti del plugin non cancellano questi dati perche sono salvati nel database WordPress come contenuti e metadati.

## Aggiornamenti da GitHub

Il plugin include un updater interno leggero basato sulle GitHub Releases della repository:

```text
https://github.com/frattomella/gest-web-rent
```

Funzionamento:

- legge la release piu recente da `releases/latest`;
- legge il tag `v1.0.0`, `v1.0.1`, `v1.1.0`, ecc.;
- normalizza la versione rimuovendo la `v`;
- confronta la release con `GWR_VERSION`;
- mostra l'aggiornamento nella schermata Plugin di WordPress;
- scarica preferibilmente l'asset `gest-web-rent.zip`;
- mantiene la cartella plugin `gest-web-rent`.

Per repository pubbliche non serve token. Per repository private puoi inserire un **GitHub Access Token** in **Impostazioni > Gest Web Rent**. Il token non viene mai mostrato nel frontend ed e usato solo lato admin/server.

## Versionamento

Ogni release deve aggiornare:

- header `Version` in `gest-web-rent.php`;
- costante `GWR_VERSION` in `gest-web-rent.php`;
- `Stable tag` in `readme.txt`;
- `CHANGELOG.md`.

Esempio prima release:

```bash
git tag v1.0.0
git push origin v1.0.0
```

Il workflow GitHub Actions crea automaticamente una release e allega `gest-web-rent.zip`.

## Changelog essenziale

Vedi `CHANGELOG.md`.
