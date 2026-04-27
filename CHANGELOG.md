# Changelog

## 1.1.3

- Allineato controllo aggiornamenti GitHub al comportamento di GestPark Online.
- Aggiunta cache release dedicata `gwr_github_release_payload`.
- Aggiunto pulsante dashboard per forzare refresh GitHub e WordPress update cache.
- Migliorata compatibilita con asset release `gest-web-rent.zip`.

## 1.1.2

- Aggiunto pulsante dashboard per forzare il controllo aggiornamenti GitHub.
- Pulizia transient update_plugins e gwr_github_release.
- Reindirizzamento alla dashboard con messaggio di conferma.

## 1.1.0

- Refactor radicale su tabelle custom `wp_gwr_vehicles`, `wp_gwr_vehicle_images`, `wp_gwr_availability`.
- Rimossa l'esperienza principale basata su Custom Post Type/editor WordPress.
- Aggiunta gestione veicoli custom con form dedicato.
- Aggiunta gestione foto tramite WordPress Media Library.
- Aggiunta gestione indisponibilita nel form veicolo e nella pagina Disponibilita.
- Frontend concentrato su un unico componente: `[gwr_catalog]`.
- Filtro date integrato nel catalogo con aggiornamento AJAX automatico.
- Card responsive a 2 colonne desktop e 1 colonna mobile.
- Dettaglio veicolo in overlay/modal con sfondo blur e gallery.
- WhatsApp/email nel modal con date selezionate gia compilate.
- Disattivati blocchi Gutenberg e pagina single veicolo come esperienza principale.

## 1.0.1

- Corretto packaging ZIP per installazione WordPress.
- Lo ZIP ora contiene la cartella corretta gest-web-rent.
- Aggiunta validazione del file principale nel workflow release.
- Aggiunto script locale per generare build/gest-web-rent.zip.

## 1.0.0

- Prima versione stabile.
- Gestione veicoli a noleggio.
- Catalogo frontend.
- Contatto WhatsApp Business.
- Contatto email.
