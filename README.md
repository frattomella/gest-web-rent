# Gest Web Rent

Gest Web Rent e un plugin WordPress per concessionari e rent company: gestisce veicoli a noleggio, disponibilita per date, catalogo frontend, schede veicolo e richieste tramite WhatsApp Business o email.

## Requisiti

- WordPress 6.0 o superiore.
- PHP 7.4 o superiore.
- Permalink WordPress attivi consigliati.
- Repository GitHub pubblica o privata con release taggate `v*`.

## Installazione manuale

1. Carica la cartella `gest-web-rent` in `wp-content/plugins/`.
2. Attiva il plugin da **Plugin > Plugin installati**.
3. Vai in **Gest Web Rent > Impostazioni** e configura WhatsApp Business/email.
4. Crea i veicoli dal menu **Gest Web Rent > Veicoli**.
5. Inserisci in una pagina il blocco **Gest Web Rent - Catalogo veicoli** oppure lo shortcode.

## Installazione da ZIP

1. Non scaricare lo ZIP automatico del branch GitHub, perche GitHub crea cartelle tipo `gest-web-rent-main`.
2. Scarica invece `gest-web-rent.zip` dalla sezione GitHub Releases.
3. In alternativa genera lo ZIP localmente con `bash scripts/build-zip.sh`.
4. In WordPress apri **Plugin > Aggiungi nuovo > Carica plugin**.
5. Seleziona `build/gest-web-rent.zip` oppure lo ZIP della release.
6. Installa e attiva il plugin.

Lo ZIP corretto deve contenere:

```text
gest-web-rent/
  gest-web-rent.php
```

Non deve contenere `gest-web-rent-main/` o cartelle generate automaticamente da GitHub.

## Build ZIP locale

```bash
bash scripts/build-zip.sh
```

Output:

```text
build/gest-web-rent.zip
```

Verifica:

```bash
unzip -l build/gest-web-rent.zip | head
```

Deve apparire `gest-web-rent/gest-web-rent.php`.

## Configurazione WhatsApp Business

In **Gest Web Rent > Impostazioni** inserisci il numero WhatsApp Business in formato internazionale, per esempio `+393331234567`.

Nel frontend il plugin genera link `wa.me` con un messaggio precompilato riferito al veicolo selezionato.

## Configurazione email

In **Gest Web Rent > Impostazioni** inserisci l'indirizzo email che deve ricevere le richieste di noleggio.

Il catalogo e la scheda veicolo generano un link `mailto:` con oggetto precompilato.

## Uso shortcode

Catalogo veicoli:

```text
[gwr_catalog]
```

Catalogo con limite:

```text
[gwr_catalog limit="6"]
```

Calendario disponibilita:

```text
[gwr_availability_calendar]
```

Scheda singolo veicolo:

```text
[gwr_vehicle id="123"]
```

Alias compatibili:

```text
[gest_web_rent_catalog]
[gest_web_rent_vehicle id="123"]
```

## Blocchi Gutenberg

Il plugin registra tre componenti editor dinamici:

- **Gest Web Rent - Catalogo veicoli**: card veicoli con filtri data, marca/modello, posti e prezzo.
- **Gest Web Rent - Calendario disponibilita**: selezione date e risultati disponibili.
- **Gest Web Rent - Scheda veicolo**: gallery, dati noleggio, dati tecnici e box contatto.

## Gestione veicoli

Dal menu **Gest Web Rent** puoi creare veicoli come contenuti WordPress. Ogni veicolo supporta:

- titolo;
- descrizione;
- riassunto;
- immagine in evidenza;
- marca, modello, versione e categoria;
- prezzo giornaliero, settimanale e mensile;
- cauzione e costo chilometri extra;
- chilometraggio massimo giornaliero/mensile;
- eta minima e patente richiesta;
- posti;
- porte;
- anno;
- chilometraggio;
- alimentazione;
- cambio;
- sede ritiro;
- assicurazione;
- servizi inclusi;
- dotazioni/accessori;
- note ritiro/consegna;
- URL galleria immagini.

## Gestione disponibilita

Ogni veicolo ha una metabox **Disponibilita e impegni** dove inserire periodi:

- occupato;
- manutenzione;
- riservato;
- non disponibile.

Il catalogo e il calendario frontend confrontano le date richieste con questi periodi e mostrano solo i veicoli liberi. Gli aggiornamenti del plugin non cancellano questi dati perche sono salvati nella tabella WordPress `wp_gwr_availability`.

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

Per repository pubbliche non serve token. Per repository private puoi inserire un **GitHub Access Token** in **Gest Web Rent > Impostazioni**. Il token non viene mai mostrato nel frontend ed e usato solo lato admin/server.

## Versionamento

Ogni release deve aggiornare:

- header `Version` in `gest-web-rent.php`;
- costante `GWR_VERSION` in `gest-web-rent.php`;
- `Stable tag` in `readme.txt`;
- `CHANGELOG.md`.

Esempio release corrente:

```bash
git tag v1.0.1
git push origin v1.0.1
```

Il workflow GitHub Actions crea automaticamente una release e allega `gest-web-rent.zip`.

## Changelog essenziale

Vedi `CHANGELOG.md`.
