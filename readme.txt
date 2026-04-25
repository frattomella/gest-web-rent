=== Gest Web Rent ===
Contributors: frattomella
Tags: noleggio, veicoli, catalogo, whatsapp, rent
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestione veicoli a noleggio con dashboard, disponibilita per date, catalogo frontend, schede veicolo, blocchi Gutenberg, contatto WhatsApp Business e contatto email.

== Description ==

Gest Web Rent permette di gestire veicoli a noleggio in WordPress, pubblicare un catalogo frontend filtrabile per disponibilita e ricevere richieste commerciali tramite WhatsApp Business o email.

Funzionalita principali:

* Gestione veicoli come custom post type.
* Dashboard amministrativa in stile GestPark Online.
* Scheda noleggio completa con prezzi, limiti km, cauzione, assicurazione, dotazioni e note.
* Disponibilita per periodi e filtro frontend per data ritiro/riconsegna.
* Catalogo frontend tramite shortcode.
* Scheda veicolo singola.
* Blocchi Gutenberg dinamici per catalogo, calendario disponibilita e scheda veicolo.
* Link WhatsApp Business con messaggio precompilato.
* Link email con oggetto precompilato.
* Aggiornamenti automatici da GitHub Releases.

== Installation ==

1. Carica la cartella `gest-web-rent` in `wp-content/plugins/` oppure installa lo ZIP della release.
2. Attiva il plugin dalla schermata Plugin.
3. Configura WhatsApp Business ed email in **Gest Web Rent > Configurazione**.
4. Crea i veicoli dal menu **Gest Web Rent**.
5. Inserisci lo shortcode `[gest_web_rent_catalog]` in una pagina.

== Frequently Asked Questions ==

= Serve un token GitHub? =

No, se la repository e pubblica. Per repository private puoi inserire un GitHub Access Token nelle impostazioni del plugin.

= Gli aggiornamenti cancellano veicoli o disponibilita? =

No. Veicoli, metadati e impostazioni sono salvati nel database WordPress e non vengono rimossi durante gli aggiornamenti.

= Quali shortcode sono disponibili? =

Usa `[gest_web_rent_catalog]` per il catalogo, `[gest_web_rent_availability]` per il calendario disponibilita e `[gest_web_rent_vehicle id="123"]` per una singola scheda veicolo.

== Changelog ==

= 1.1.0 =
* Plugin rifatto su base operativa simile a GestPark Online.
* Dashboard amministrativa con KPI, configurazione e guida componenti.
* Scheda veicolo completa per noleggio.
* Gestione periodi di disponibilita/impegno per ogni veicolo.
* Catalogo frontend con filtri disponibilita.
* Calendario disponibilita frontend.
* Blocchi Gutenberg dinamici.

= 1.0.0 =
* Prima versione stabile.
* Gestione veicoli a noleggio.
* Catalogo frontend.
* Scheda veicolo.
* Calendario disponibilita tramite note di disponibilita per veicolo.
* Contatto WhatsApp Business.
* Contatto email.
