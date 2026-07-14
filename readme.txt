=== Gest Web Rent ===
Contributors: frattomella
Tags: noleggio, veicoli, catalogo, whatsapp, rent
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin vetrina per noleggio veicoli con gestione custom, foto, indisponibilita, catalogo responsive, modal dettaglio e contatti WhatsApp/email.

== Description ==

Gest Web Rent permette di creare e gestire veicoli a noleggio senza usare l'editor WordPress classico come esperienza principale. I veicoli sono salvati in tabelle custom e pubblicati tramite un unico componente frontend: il catalogo noleggio.

Funzionalita principali:

* Dashboard amministrativa in stile GestPark Online.
* Modalita Vetrina, Richiesta di prenotazione e Prenotazione online selezionabili dalla dashboard.
* Gestione veicoli tramite form custom.
* Condizioni di noleggio globali con override per singolo veicolo.
* Coperture, franchigie, deposito, requisiti, documenti, politiche, extra e FAQ configurabili.
* Prenotazione a quattro step con cliente, conducente, consensi e conferma.
* Prezzo e disponibilita ricalcolati dal server con protezione dai conflitti.
* Motore tariffario server-side con listini, stagionalita, durata, weekend, coupon, tasse e snapshot.
* Pagamenti online Stripe Checkout, bonifico, richiesta e pagamento al ritiro con acconto/saldo.
* Area amministrativa per tariffe, coupon, impostazioni Stripe, pagamenti, webhook e rimborsi.
* Documenti professionali di prenotazione con voucher, conferme, contratti, verbali e riepiloghi.
* Download protetti, snapshot versionati, firme semplici, allegati pratica e portale cliente.
* Area cliente sicura con recupero link, stato, timeline, pagamenti, documenti, upload e richieste.
* Comunicazioni centralizzate con template, coda idempotente, retry, log e promemoria WP-Cron.
* Gestione amministrativa di stato, cronologia, consegna, riconsegna e stampa.
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

Per mostrare una pagina dedicata allo stato pagamento usa `[gwr_payment_status]`. Gli URL Stripe predefiniti mostrano automaticamente lo stesso blocco quando rientrano con `gwr_payment=success` o `gwr_payment=cancel`.

Per mostrare l'area documenti cliente usa `[gwr_booking_portal]`. L'accesso richiede codice prenotazione e token pubblico della pratica.

= Esiste un calendario separato? =

No. Il filtro date e integrato nel catalogo e avvia la ricerca con il pulsante Cerca veicoli.

= Esiste una pagina singola veicolo? =

No. Il dettaglio si apre in overlay/modal senza cambiare pagina.

= Gli aggiornamenti cancellano veicoli o disponibilita? =

No. Veicoli, foto, impostazioni e indisponibilita sono salvati nel database WordPress.

== Changelog ==

= 2.0.0 =
* Reso il dettaglio veicolo indipendente dai nonce presenti nelle pagine cache tramite route REST pubblica read-only e rate limit.
* Centralizzati ID card, payload dettaglio, parser risposta, loading, retry e annullamento delle richieste concorrenti.
* Aggiunte modalita operative Vetrina, Richiesta di prenotazione e Prenotazione online con menu, endpoint e servizi condizionali.
* Aggiunto archivio richieste clienti con stato Da valutare, notifiche email e blocco disponibilita opzionale.
* Stripe, pagamenti, checkout, documenti e area cliente vengono inizializzati esclusivamente in modalita Prenotazione online.
* Conservati prenotazioni, pagamenti, documenti e richieste durante ogni cambio modalita.

= 1.9.2 =
* Il dettaglio veicolo non viene piu bloccato da errori secondari di prezzo, disponibilita o periodo.
* Protetta la risposta AJAX da warning PHP e output inatteso.
* Corretto il messaggio di errore duplicato e aggiunta gestione specifica della sessione scaduta.

= 1.9.1 =
* Corretto il caricamento on demand del dettaglio veicolo usando l'ID reale della card.
* Normalizzati endpoint, payload pubblico, disponibilita e preventivo server senza esporre dati interni.
* Aggiunti loading accessibile, retry, cache per periodo e protezione dalle risposte fuori ordine.
* Rifinita la UI del configuratore su desktop e mobile, inclusi galleria e stati di errore.

= 1.9.0 =
* Completata area cliente con token hash, rotazione/revoca, recupero link neutro, no-store e noindex.
* Aggiunti riepilogo prenotazione, timeline, pagato/saldo/deposito, retry Stripe e istruzioni bonifico.
* Aggiunti documenti autorizzati, upload cliente protetti, checklist e verifica amministrativa.
* Aggiunte richieste di modifica e annullamento con approvazione amministrativa controllata.
* Aggiunto servizio notifiche centralizzato con template, token consentiti, coda, log, idempotenza e retry.
* Unificati scadenze, riconciliazione pagamenti e promemoria in un singolo evento WP-Cron.
* Aggiunte tabelle `gwr_customer_requests`, `gwr_notification_queue` e `gwr_notification_logs` con migrazioni idempotenti.

= 1.8.0 =
* Aggiunto motore documentale per riepilogo prenotazione, conferma, voucher, contratto, verbale consegna, verbale riconsegna, riepilogo pagamenti e conferma annullamento.
* Aggiunte tabelle `gwr_booking_documents`, `gwr_document_signatures`, `gwr_document_email_logs` e `gwr_booking_attachments`.
* Aggiunti snapshot documentali versionati con hash contenuto/file e cache HTML protetta in upload.
* Aggiunti download e preview autorizzati via capability admin, token prenotazione o URL firmato temporaneo.
* Aggiunta area admin Documenti con archivio, impostazioni aziendali, logo Media Library, testi e clausole configurabili.
* Aggiunto pannello Documenti nel dettaglio prenotazione con genera, anteprima, download, invia, firma, invalida, archivia e allegati.
* Aggiunto shortcode `[gwr_booking_portal]` per consultazione cliente sicura dei documenti disponibili.

= 1.7.0 =
* Aggiunto motore tariffario centralizzato con importi in centesimi, durata noleggio unificata e snapshot immutabile in prenotazione.
* Aggiunte tabelle `gwr_price_lists`, `gwr_pricing_rules`, `gwr_coupons`, `gwr_coupon_usages`, `gwr_payments` e `gwr_payment_events`.
* Aggiunte regole per listini, stagionalita, durata, weekend, date speciali, supplementi, sconti, coupon, IVA e deposito cauzionale.
* Aggiunti pagamento Stripe Checkout, bonifico, richiesta e pagamento al ritiro con split acconto/saldo.
* Aggiunti webhook Stripe firmati, idempotenza eventi, retry pubblico, riconciliazione cron e stati pagamento dedicati.
* Aggiunta area admin Tariffe e pagamenti, elenco pagamenti, dettaglio evento, rimborsi e test connessione Stripe.

= 1.6.0 =
* Aggiunto flusso completo di prenotazione a quattro step nel configuratore fullscreen.
* Aggiunte tabelle prenotazioni, righe e cronologia con migrazione idempotente.
* Aggiunti ricalcolo prezzo server-side, snapshot commerciale e blocco disponibilita atomico.
* Aggiunti codice pratica, consensi, antispam, email cliente/admin e conferma frontend.
* Aggiunta area admin con filtri, paginazione, dettaglio, transizioni, modifica e stampa.
* Aggiunta scadenza configurabile delle richieste in attesa tramite WP-Cron.

= 1.5.0 =
* Aggiunta area amministrativa Condizioni di noleggio con navigazione a sezioni.
* Aggiunti termini globali e override indipendenti per singolo veicolo.
* Aggiunti repeater validati per servizi, coperture, documenti, extra e FAQ.
* Collegati i dati commerciali reali al configuratore con caricamento dettaglio on demand.
* Aggiunta selezione locale di coperture ed extra con riepilogo prezzi accessibile.
* Nessuna nuova tabella: migrazione idempotente tramite opzioni WordPress non autoload.

= 1.4.0 =
* Sostituito il vecchio dettaglio con un configuratore veicolo quasi fullscreen.
* Aggiunti riepilogo noleggio sticky, gallery accessibile e sezioni dati dinamiche.
* Migliorati focus trap, swipe, tastiera, accordion e supporto admin bar.
* Arricchiti i messaggi WhatsApp ed email con periodo, orari, sedi e tariffa.

= 1.3.0 =
* Nuovo catalogo professionale con sidebar filtri e card orizzontali.
* Aggiunti categorie rapide, ordinamento, viste lista/griglia e filtri attivi.
* Migliorati immagini, tariffa giornaliera, dotazioni, servizi e sede nelle card.
* Aggiunti skeleton di caricamento e stati vuoto/errore dedicati.

= 1.2.0 =
* Nuova barra di ricerca professionale con localita, date e orari.
* Aggiunti riepilogo ricerca, validazione inline e stato loading accessibile.
* Filtri secondari responsive con contatore, applicazione esplicita e reset selettivo.
* Migliorati stati vuoto ed errore tecnico del catalogo.

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
