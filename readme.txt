=== Gest Web Rent ===
Contributors: frattomella
Tags: noleggio, veicoli, catalogo, whatsapp, rent
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
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
* Filtro date integrato con aggiornamento automatico AJAX.
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

No. Il filtro date e integrato nel catalogo e aggiorna automaticamente i risultati.

= Esiste una pagina singola veicolo? =

No. Il dettaglio si apre in overlay/modal senza cambiare pagina.

= Gli aggiornamenti cancellano veicoli o disponibilita? =

No. Veicoli, foto, impostazioni e indisponibilita sono salvati nel database WordPress.

== Changelog ==

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
