# Gest Web Rent

Gest Web Rent e un plugin WordPress vetrina per noleggio veicoli. Gestisce veicoli, foto, indisponibilita, contatti WhatsApp Business/email e un catalogo frontend responsive con dettagli in overlay.

Il plugin non usa l'editor WordPress come esperienza principale per creare veicoli: la gestione e interna, con form custom e tabelle dedicate.

## Requisiti

- WordPress 6.0 o superiore.
- PHP 7.4 o superiore.
- Nessuna dipendenza obbligatoria da WooCommerce, Elementor o plugin esterni.

## Installazione da ZIP

Non scaricare lo ZIP automatico del branch GitHub, perche GitHub crea cartelle tipo `gest-web-rent-main`.

Usa invece:

```bash
bash scripts/build-zip.sh
```

Poi carica in WordPress:

```text
build/gest-web-rent.zip
```

Lo ZIP corretto contiene:

```text
gest-web-rent/
gest-web-rent/gest-web-rent.php
```

## Admin

Menu principale:

```text
Gest Web Rent
```

Sottomenu:

- Dashboard
- Veicoli
- Aggiungi veicolo
- Disponibilita
- Impostazioni

La dashboard mostra riepilogo veicoli, veicoli attivi, veicoli in evidenza, blocchi futuri, stato WhatsApp/email e shortcode catalogo.

## Creare Un Veicolo

Vai in **Gest Web Rent > Aggiungi veicolo**.

Il form custom include:

- dati principali;
- prezzi;
- regole noleggio;
- servizi inclusi;
- dotazioni;
- descrizione e note;
- foto veicolo tramite Media Library;
- stato;
- evidenza;
- indisponibilita.

I veicoli sono salvati nella tabella custom `wp_gwr_vehicles`, non come post WordPress.

## Foto Veicolo

Nel form veicolo puoi selezionare piu immagini dalla Media Library, impostare la copertina, riordinare le immagini e rimuoverle. Le foto sono salvate in `wp_gwr_vehicle_images`.

## Disponibilita

Le indisponibilita si gestiscono:

- dentro la scheda custom del veicolo;
- oppure dalla pagina **Gest Web Rent > Disponibilita**.

La tabella `wp_gwr_availability` salva periodi con:

- busy;
- maintenance;
- reserved;
- unavailable.

Tutti questi stati rendono il veicolo non disponibile nel catalogo quando le date richieste si sovrappongono.

## Catalogo Frontend

Inserisci in una pagina:

```text
[gwr_catalog]
```

Alias compatibile:

```text
[gest_web_rent_catalog]
```

Il catalogo include:

- filtro date integrato;
- aggiornamento AJAX automatico al cambio date;
- filtri veicolo;
- card responsive a 2 colonne desktop e 1 colonna mobile;
- dettaglio veicolo in overlay/modal con blur;
- gallery foto;
- info noleggio complete;
- pulsanti WhatsApp/email con date selezionate.

Non esistono piu come esperienza pubblica centrale:

- calendario separato;
- scheda veicolo separata;
- pagina single veicolo;
- blocchi Gutenberg rigidi.

## WhatsApp Ed Email

Configura in **Gest Web Rent > Impostazioni**:

- numero WhatsApp Business;
- email concessionario;
- nome concessionario;
- messaggio WhatsApp predefinito;
- oggetto email predefinito;
- corpo email predefinito;
- testo privacy/nota contatto;
- colore primario.

Il modal usa automaticamente le date selezionate nel catalogo.

## Google Calendar

Google Calendar non e usato come sistema principale. Una integrazione stabile richiederebbe OAuth/API/service account e una configurazione Google Cloud non adatta a un plugin installabile con facilita.

La gestione principale resta interna e stabile. Una futura sincronizzazione Google Calendar potra essere aggiunta come modulo opzionale.

## Aggiornamenti Da GitHub

Il plugin usa GitHub Releases della repository:

```text
https://github.com/frattomella/gest-web-rent
```

L'updater cerca prima l'asset release `gest-web-rent.zip`; solo come fallback usa lo zipball GitHub e rinomina la cartella estratta in `gest-web-rent`.

Dalla dashboard `Gest Web Rent` e disponibile il box `Aggiornamenti GitHub` con il pulsante `Controlla aggiornamenti GitHub`: svuota le cache `gwr_github_release` e `update_plugins`, forza `wp_update_plugins()` e rimanda alla dashboard con conferma. L'installazione dell'aggiornamento resta manuale dalla pagina Plugin di WordPress.

## Versionamento

Ogni release deve aggiornare:

- header `Version` in `gest-web-rent.php`;
- costante `GWR_VERSION`;
- `Stable tag` in `readme.txt`;
- `CHANGELOG.md`.

Release corrente:

```text
1.1.2
```
