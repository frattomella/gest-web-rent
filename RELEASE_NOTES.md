# Gest Web Rent v2.0.2

Questa release corregge il caricamento dei risultati dopo la selezione delle date anche sugli hosting con REST API limitata o pagine in cache.

- Ricerca catalogo tramite REST con fallback AJAX pubblico indipendente dai nonce.
- Endpoint read-only protetto da rate limit e output PHP inatteso.
- Fallback AJAX legacy mantenuto per la compatibilita con installazioni esistenti.
- Nessuna modifica alla query disponibilita o ai dati dei veicoli.
- Nessuna modifica a dati, disponibilita, prezzi, richieste o prenotazioni esistenti.
- Asset ufficiale `gest-web-rent.zip` con cartella radice corretta per WordPress.
