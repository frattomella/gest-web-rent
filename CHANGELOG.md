# Changelog

## 1.4.0

- Sostituito il vecchio popup con un configuratore veicolo quasi fullscreen e responsive.
- Aggiunti header, gallery, indice sezioni, riepilogo noleggio sticky e CTA mobile.
- Le sezioni commerciali vengono mostrate esclusivamente quando alimentate da dati reali.
- Migliorate navigazione gallery, swipe, tastiera, accordion, focus trap e ritorno del focus.
- Aggiunto supporto agli offset della barra admin WordPress desktop e mobile.
- Arricchiti WhatsApp ed email con date, orari, sedi, tariffa e link della pagina.

## 1.3.0

- Ridisegnato il catalogo con sidebar desktop e pannello filtri responsive condiviso.
- Aggiunte categorie rapide basate sui veicoli disponibili e sui prezzi reali.
- Aggiunti ordinamento client-side, vista lista/griglia persistente e chip dei filtri attivi.
- Sostituite le vecchie card cliccabili con card semantiche orizzontali e CTA esplicite.
- Migliorate immagini responsive, caratteristiche, servizi inclusi e informazioni sulla sede.
- Aggiunti skeleton di caricamento e stati vuoto/errore professionali.

## 1.2.0

- Introdotto un design system isolato per il catalogo pubblico.
- Ridisegnata la barra di ricerca con localita di ritiro e riconsegna, date italiane e orari.
- Aggiunte validazione inline, gestione del periodo e riepilogo della ricerca.
- Resi espliciti submit e applicazione filtri, senza richieste AJAX durante la compilazione.
- Aggiunti contatore filtri, reset selettivo e pannello responsive accessibile.
- Distinti gli stati iniziale, loading, nessun risultato ed errore tecnico.

## 1.1.5

- Date frontend nel formato italiano `GG-MM-AAAA` con valori ISO interni.
- Ricerca veicoli avviata solo al submit, senza chiamate AJAX a ogni modifica.
- Filtri secondari raccolti in un accordion accessibile e responsive.
- Ridisegnato il modal veicolo con gallery `object-fit: contain`, sezioni e divisori.
- Normalizzati e validati i contatti WhatsApp ed email con date italiane nei messaggi.
- Migliorati focus, navigazione tastiera, palette blu/grigio e usabilita mobile.

## 1.1.4

- Ridisegnata UI del popup dettaglio veicolo.
- Migliorata gallery immagini responsive.
- Sostituiti i grandi riquadri caratteristiche con statistiche compatte con icone.
- Ridisegnato pannello calendario e filtri catalogo.
- Uniformato stile di input, select e campi numerici.
- Rimosso banner "Catalogo noleggio".
- Migliorata UX mobile del modal e del catalogo.

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
