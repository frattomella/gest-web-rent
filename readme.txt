=== Gest Web Rent ===
Contributors: frattomella
Tags: noleggio, veicoli, catalogo, whatsapp, rent
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin vetrina per noleggio veicoli con gestione custom, foto, indisponibilita, catalogo responsive, modal dettaglio e contatti WhatsApp/email.

== Description ==

Gest Web Rent permette di creare e gestire veicoli a noleggio senza usare l'editor WordPress classico come esperienza principale. I veicoli sono salvati in tabelle custom e pubblicati tramite un unico componente frontend: il catalogo noleggio.

Funzionalita principali:

* Dashboard amministrativa in stile GestPark Online.
* Gestione veicoli tramite form custom.
* Foto veicolo tramite Media Library.
* Indisponibilita per date in tabella custom.
* Catalogo frontend responsive con griglia a 2 colonne desktop.
* Filtro date integrato con ricerca AJAX su comando.
* Dettaglio veicolo in overlay/modal con sfondo blur.
* Gallery foto nel modal.
* Link WhatsApp Business ed email con date precompilate.
* Aggiornamenti automatici da GitHub Releases.

== Installation ==

1. Scarica lo ZIP `gest-web-rent.zip` dalla GitHub Release, non lo ZIP automatico del branch.
2. Carica lo ZIP da **Plugin > Aggiungi nuovo > Carica plugin**.
3. Attiva il plugin.
4. Configura contatti in **Gest Web Rent > Impostazioni**.
5. Crea i veicoli da **Gest Web Rent > Aggiungi veicolo**.
6. Inserisci lo shortcode `[gwr_catalog]` in una pagina.

== Frequently Asked Questions ==

= Il plugin usa l'editor WordPress per creare veicoli? =

No. La creazione e modifica veicolo avviene tramite form custom nel menu Gest Web Rent.

= Quale shortcode devo usare? =

Usa `[gwr_catalog]`. L'alias `[gest_web_rent_catalog]` resta disponibile per compatibilita.

= Esiste un calendario separato? =

No. Il filtro date e integrato nel catalogo e avvia la ricerca con il pulsante Cerca veicoli.

= Esiste una pagina singola veicolo? =

No. Il dettaglio si apre in overlay/modal senza cambiare pagina.

= Gli aggiornamenti cancellano veicoli o disponibilita? =

No. Veicoli, foto, impostazioni e indisponibilita sono salvati nel database WordPress.

== Changelog ==

= 1.1.5 =
* Date frontend nel formato italiano GG-MM-AAAA con valori ISO interni.
* Ricerca veicoli solo al submit e filtri secondari in accordion accessibile.
* Ridisegnato il modal veicolo con gallery non ritagliata e gerarchia semplificata.
* Corretta e rafforzata la generazione dei contatti WhatsApp ed email.
* Migliorati focus, navigazione tastiera, responsive e palette blu/grigio.

= 1.1.4 =
* Ridisegnata UI del popup dettaglio veicolo.
* Migliorata gallery immagini responsive.
* Sostituiti i grandi riquadri caratteristiche con statistiche compatte con icone.
* Ridisegnato pannello calendario e filtri catalogo.
* Uniformato stile di input, select e campi numerici.
* Rimosso banner "Catalogo noleggio".
* Migliorata UX mobile del modal e del catalogo.

= 1.1.3 =
* Allineato controllo aggiornamenti GitHub al comportamento di GestPark Online.
* Aggiunta cache release dedicata `gwr_github_release_payload`.
* Aggiunto pulsante dashboard per forzare refresh GitHub e WordPress update cache.
* Migliorata compatibilita con asset release `gest-web-rent.zip`.

= 1.1.2 =
* Aggiunto pulsante dashboard per forzare il controllo aggiornamenti GitHub.
* Pulizia transient `update_plugins` e `gwr_github_release`.
* Reindirizzamento alla dashboard con messaggio di conferma.

= 1.1.0 =
* Refactor radicale su tabelle custom `wp_gwr_vehicles`, `wp_gwr_vehicle_images`, `wp_gwr_availability`.
* Rimossa esperienza principale basata su Custom Post Type/editor WordPress.
* Aggiunta gestione veicoli custom con form dedicato.
* Aggiunta gestione foto via Media Library.
* Integrata gestione indisponibilita nel form veicolo e nella pagina Disponibilita.
* Frontend ridotto a un unico componente `[gwr_catalog]`.
* Filtro date AJAX automatico integrato nel catalogo.
* Dettaglio veicolo in modal con gallery e contatti WhatsApp/email.
* Disattivati blocchi Gutenberg e single page come esperienza principale.

= 1.0.1 =
* Corretto packaging ZIP per installazione WordPress.
* Lo ZIP ora contiene la cartella corretta `gest-web-rent`.
* Aggiunta validazione del file principale nel workflow release.

= 1.0.0 =
* Prima versione stabile.
* Gestione veicoli a noleggio.
* Catalogo frontend.
* Contatto WhatsApp Business.
* Contatto email.
