# Changelog

## 2.0.0

- Corretto definitivamente il caricamento del dettaglio veicolo sostituendo il nonce pubblico soggetto a cache con una route REST read-only, validata e rate limited.
- Centralizzati ID `data-gwr-vehicle-id`, listener delegato sui risultati, normalizzazione risposta, loading, retry, cache contestuale e protezione dalle risposte obsolete.
- Aggiunte modalita operative `showcase`, `request` e `booking` con helper condivisi e default retrocompatibile sicuro.
- Aggiunta selezione visuale della modalita in dashboard e impostazioni, con menu e accessi diretti coerenti.
- Aggiunto modulo leggero per informazioni e richieste di prenotazione senza pagamento, con tabella dedicata, stato `pending_review`, email e azioni amministrative.
- Aggiunto blocco disponibilita per richieste configurabile: disattivato, temporaneo o fino alla gestione manuale.
- Resi condizionali endpoint, webhook, cron e servizi di prenotazione, pagamenti, documenti e portale cliente.
- Mantenuti intatti dati e tabelle esistenti durante il cambio modalita.
- Verificati lint PHP/JavaScript, struttura ZIP WordPress e asset release `gest-web-rent.zip`.

## 1.9.2

- Corretto il caricamento del dettaglio quando tariffazione, disponibilita o validazione del periodo non sono disponibili.
- Aggiunto fallback dal payload commerciale esteso al payload veicolo essenziale.
- Isolate le eccezioni dei servizi secondari e protetta la risposta JSON da warning/output PHP inatteso.
- Reso tollerante il parser JavaScript a BOM e spazi nella risposta AJAX.
- Corretto il messaggio di errore duplicato e distinta la sessione nonce scaduta dagli altri errori.

## 1.9.1

- Corretto il bug bloccante che mostrava il fallback generico al posto del dettaglio veicolo.
- Sostituito il payload Base64 nelle card con il riferimento canonico `data-gwr-vehicle-id` per risultati iniziali, filtrati e ordinati.
- Centralizzato il payload pubblico del configuratore con disponibilita e preventivo server; gli errori secondari di prezzo non bloccano il veicolo.
- Aggiunti loading accessibile, errori classificati, retry, cache contestuale per periodo e annullamento delle richieste concorrenti.
- Migliorati focus, attributi ARIA, ripristino del trigger e protezione da risposte obsolete.
- Rifinita la UI/UX del modal con larghezza desktop controllata, galleria proporzionata, stati chiari e layout mobile fino a 320 px.
- Eseguiti test mirati su payload parziali, immagini mancanti, cache, risposta non JSON, sintassi PHP/JavaScript e packaging WordPress.

## 1.9.0

- Completata l'area cliente `[gwr_booking_portal]` con accesso tramite codice e token casuale a 32 byte, hash HMAC nel database, scadenza configurabile, revoca e rotazione.
- Aggiunti recupero link con risposta neutra, honeypot e rate limiting; pagine private con `no-store`, `noindex`, `nofollow` e referrer disabilitato.
- Aggiunti stato leggibile, timeline, periodo, extra, totale, pagato, saldo, deposito, retry Stripe server-side e istruzioni bonifico copiabili.
- Aggiunti documenti pubblicabili, checklist documenti cliente, upload PDF/JPG/PNG nello storage protetto e verifica approvato/rifiutato in amministrazione.
- Aggiunte richieste di modifica e annullamento, prive di effetti automatici fino all'approvazione; date, orari, sede, veicolo e dati vengono applicati tramite il flusso transazionale esistente.
- Creato `GWR_Notification_Service` con eventi centralizzati, template HTML/testo configurabili, allowlist token, mittente sicuro, coda, idempotenza, retry con backoff e log destinatari mascherati.
- Unificati scadenza prenotazioni, riconciliazione pagamenti, promemoria pagamento/ritiro/riconsegna e pulizia in un singolo evento WP-Cron con lock e batch.
- Create le tabelle `gwr_customer_requests`, `gwr_notification_queue` e `gwr_notification_logs`; estese prenotazioni e allegati con metadati token e verifica documenti tramite migrazioni idempotenti.
- Aggiornati pannelli amministrativi Prenotazioni, Documenti e Comunicazioni e preparato il rilascio GitHub con asset `gest-web-rent.zip`.

## 1.8.0

- Aggiunto motore documentale per riepilogo prenotazione, conferma, voucher, contratto, verbale consegna, verbale riconsegna, riepilogo pagamenti e conferma annullamento.
- Create le tabelle `gwr_booking_documents`, `gwr_document_signatures`, `gwr_document_email_logs` e `gwr_booking_attachments` con migrazione idempotente.
- Aggiunti snapshot documentali versionati con hash contenuto/file e cache HTML protetta in upload.
- Aggiunti download e preview autorizzati via capability admin, token prenotazione o URL firmato temporaneo.
- Aggiunta area admin Documenti con archivio, impostazioni aziendali, logo Media Library, testi e clausole configurabili.
- Aggiunto pannello Documenti nel dettaglio prenotazione con genera, anteprima, download, invia, firma, invalida, archivia e allegati.
- Aggiunto shortcode `[gwr_booking_portal]` per consultazione cliente sicura dei documenti disponibili.
- Strategia PDF: fallback HTML A4 stampabile/salvabile in PDF, senza dipendenze esterne o servizi headless.

## 1.7.0

- Aggiunto motore tariffario centralizzato con importi in centesimi, durata noleggio unificata e snapshot immutabile in prenotazione.
- Create le tabelle `gwr_price_lists`, `gwr_pricing_rules`, `gwr_coupons`, `gwr_coupon_usages`, `gwr_payments` e `gwr_payment_events` con migrazione idempotente.
- Aggiunte regole per listini, stagionalita, durata, weekend, date speciali, supplementi, sconti, coupon, IVA e deposito cauzionale.
- Aggiunti pagamento Stripe Checkout, bonifico, richiesta e pagamento al ritiro con split acconto/saldo.
- Aggiunti webhook Stripe firmati, idempotenza eventi, retry pubblico, riconciliazione cron e stati pagamento dedicati.
- Aggiunta area admin Tariffe e pagamenti, elenco pagamenti, dettaglio eventi, rimborsi e test connessione Stripe.
- Aggiunto shortcode `[gwr_payment_status]` e blocco automatico per gli URL di ritorno Stripe.

## 1.6.0

- Aggiunto flusso prenotazione fullscreen a quattro step con cliente, conducente, documenti e consensi.
- Create le tabelle `gwr_bookings`, `gwr_booking_items` e `gwr_booking_logs` con migrazione idempotente.
- Centralizzati ricalcolo server-side, requisiti conducente, snapshot, codice pratica e transizioni.
- Aggiunti lock MySQL per veicolo, transazioni e blocchi disponibilita collegati alla prenotazione.
- Aggiunti honeypot, tempo minimo, rate limiting, nonce e blocco doppio invio.
- Aggiunte email cliente/amministratore e comunicazioni cliente sui cambi di stato.
- Aggiunta amministrazione con elenco paginato, filtri, dettaglio, modifica controllata e stampa.
- Aggiunta scadenza configurabile delle richieste in attesa e rilascio automatico del veicolo.

## 1.5.0

- Aggiunta pagina amministrativa `Condizioni di noleggio` con navigazione per sezioni.
- Introdotti termini globali e override indipendenti per ogni veicolo.
- Aggiunti repeater sanitizzati e ordinabili per servizi, coperture, documenti, extra e FAQ.
- Centralizzati normalizzazione, ereditarieta e payload pubblico in `GWR_Rental_Terms`.
- Collegato il configuratore ai dati commerciali reali tramite dettaglio AJAX on demand.
- Aggiunta selezione di coperture ed extra con calcolo in centesimi e riepilogo `aria-live`.
- Inizializzazione idempotente delle opzioni senza nuove tabelle o perdita dei dati esistenti.

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
