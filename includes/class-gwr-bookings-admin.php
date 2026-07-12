<?php
/**
 * Booking administration pages and actions.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/** Admin UI for bookings. */
class GWR_Bookings_Admin {
	/** Register protected actions. */
	public static function init() {
		add_action( 'admin_post_gwr_booking_status', array( __CLASS__, 'handle_status' ) );
		add_action( 'admin_post_gwr_booking_update', array( __CLASS__, 'handle_update' ) );
		add_action( 'admin_post_gwr_booking_print', array( __CLASS__, 'handle_print' ) );
	}

	/** Paginated bookings list. */
	public static function list_page() {
		self::require_capability();
		$args = array(
			'search' => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
			'status' => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
			'vehicle_id' => absint( $_GET['vehicle_id'] ?? 0 ),
			'pickup_from' => sanitize_text_field( wp_unslash( $_GET['pickup_from'] ?? '' ) ),
			'pickup_to' => sanitize_text_field( wp_unslash( $_GET['pickup_to'] ?? '' ) ),
			'return_from' => sanitize_text_field( wp_unslash( $_GET['return_from'] ?? '' ) ),
			'created_from' => sanitize_text_field( wp_unslash( $_GET['created_from'] ?? '' ) ),
			'min_amount' => sanitize_text_field( wp_unslash( $_GET['min_amount'] ?? '' ) ),
			'active' => ! empty( $_GET['active'] ),
			'expired' => ! empty( $_GET['expired'] ),
			'page' => max( 1, absint( $_GET['paged'] ?? 1 ) ),
			'per_page' => 20,
		);
		$result = GWR_Bookings::query( $args );
		$vehicles = GWR_CPT::get_vehicles();
		GWR_Admin::page_start( 'bookings', __( 'Prenotazioni', 'gest-web-rent' ), __( 'Richieste, conferme, consegne e riconsegne con disponibilita sincronizzata.', 'gest-web-rent' ) );
		self::notice();
		echo '<form method="get" class="gwr-booking-filters"><input type="hidden" name="page" value="gwr-bookings" />';
		echo '<label><span>' . esc_html__( 'Ricerca', 'gest-web-rent' ) . '</span><input type="search" name="s" value="' . esc_attr( $args['search'] ) . '" placeholder="' . esc_attr__( 'Codice, cliente, email, telefono', 'gest-web-rent' ) . '" /></label>';
		echo '<label><span>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</span><select name="status"><option value="">' . esc_html__( 'Tutti', 'gest-web-rent' ) . '</option>';
		foreach ( GWR_Bookings::statuses() as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '" ' . selected( $args['status'], $key, false ) . '>' . esc_html( $label ) . '</option>'; }
		echo '</select></label><label><span>' . esc_html__( 'Veicolo', 'gest-web-rent' ) . '</span><select name="vehicle_id"><option value="">' . esc_html__( 'Tutti', 'gest-web-rent' ) . '</option>';
		foreach ( $vehicles as $vehicle ) { echo '<option value="' . esc_attr( $vehicle['id'] ) . '" ' . selected( $args['vehicle_id'], $vehicle['id'], false ) . '>' . esc_html( $vehicle['title'] ) . '</option>'; }
		echo '</select></label><label><span>' . esc_html__( 'Ritiro dal', 'gest-web-rent' ) . '</span><input type="date" name="pickup_from" value="' . esc_attr( $args['pickup_from'] ) . '" /></label><label><span>' . esc_html__( 'Ritiro al', 'gest-web-rent' ) . '</span><input type="date" name="pickup_to" value="' . esc_attr( $args['pickup_to'] ) . '" /></label>';
		echo '<label><span>' . esc_html__( 'Riconsegna dal', 'gest-web-rent' ) . '</span><input type="date" name="return_from" value="' . esc_attr( $args['return_from'] ) . '" /></label><label><span>' . esc_html__( 'Creata dal', 'gest-web-rent' ) . '</span><input type="date" name="created_from" value="' . esc_attr( $args['created_from'] ) . '" /></label><label><span>' . esc_html__( 'Importo minimo', 'gest-web-rent' ) . '</span><input type="number" min="0" step="0.01" name="min_amount" value="' . esc_attr( $args['min_amount'] ) . '" /></label>';
		echo '<label class="gwr-check-card"><input type="checkbox" name="active" value="1" ' . checked( $args['active'], true, false ) . ' /><span>' . esc_html__( 'Solo attive', 'gest-web-rent' ) . '</span></label><label class="gwr-check-card"><input type="checkbox" name="expired" value="1" ' . checked( $args['expired'], true, false ) . ' /><span>' . esc_html__( 'Solo scadute', 'gest-web-rent' ) . '</span></label><button class="button button-primary">' . esc_html__( 'Filtra', 'gest-web-rent' ) . '</button></form>';

		echo '<section class="gwr-data-grid-card"><div class="gwr-table-wrap"><table class="widefat gwr-bookings-table"><thead><tr><th>' . esc_html__( 'Codice', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Cliente', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Veicolo', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Ritiro', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Riconsegna', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Totale', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Creata', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Azioni', 'gest-web-rent' ) . '</th></tr></thead><tbody>';
		if ( empty( $result['items'] ) ) { echo '<tr><td colspan="9">' . esc_html__( 'Nessuna prenotazione trovata.', 'gest-web-rent' ) . '</td></tr>'; }
		foreach ( $result['items'] as $booking ) { self::list_row( $booking ); }
		echo '</tbody></table></div></section>';
		if ( $result['pages'] > 1 ) {
			$base = remove_query_arg( 'paged' );
			echo '<nav class="gwr-pagination" aria-label="' . esc_attr__( 'Pagine prenotazioni', 'gest-web-rent' ) . '">';
			for ( $page = 1; $page <= $result['pages']; $page++ ) { echo '<a class="button' . ( $page === $args['page'] ? ' button-primary' : '' ) . '" href="' . esc_url( add_query_arg( 'paged', $page, $base ) ) . '"' . ( $page === $args['page'] ? ' aria-current="page"' : '' ) . '>' . esc_html( $page ) . '</a>'; }
			echo '</nav>';
		}
		GWR_Admin::page_end();
	}

	/** Full booking detail. */
	public static function detail_page() {
		self::require_capability();
		$booking_id = absint( $_GET['booking_id'] ?? 0 );
		$booking = GWR_Bookings::get( $booking_id );
		if ( ! $booking ) { wp_die( esc_html__( 'Prenotazione non trovata.', 'gest-web-rent' ) ); }
		$items = GWR_Bookings::items( $booking_id );
		$logs = GWR_Bookings::logs( $booking_id );
		$vehicles = GWR_CPT::get_vehicles();
		$print_url = wp_nonce_url( admin_url( 'admin-post.php?action=gwr_booking_print&booking_id=' . $booking_id ), 'gwr_booking_print_' . $booking_id );
		GWR_Admin::page_start( 'bookings', sprintf( __( 'Prenotazione %s', 'gest-web-rent' ), $booking['booking_code'] ), __( 'Dati correnti, snapshot commerciale, consensi, documenti e cronologia della pratica.', 'gest-web-rent' ), array( array( 'label' => __( 'Stampa riepilogo', 'gest-web-rent' ), 'url' => $print_url ), array( 'label' => __( 'Torna alle prenotazioni', 'gest-web-rent' ), 'url' => admin_url( 'admin.php?page=gwr-bookings' ) ) ) );
		self::notice();
		echo '<div class="gwr-booking-detail-grid"><main class="gwr-booking-detail-main">';
		self::summary_section( $booking, $items );
		self::people_section( $booking );
		self::snapshot_section( $booking );
		GWR_Documents_Admin::booking_documents_panel( $booking );
		GWR_Customer_Request_Service::admin_panel( $booking );
		GWR_Notification_Service::booking_panel( $booking );
		self::operations_section( $booking );
		self::logs_section( $logs );
		echo '</main><aside class="gwr-booking-detail-side">';
		self::status_section( $booking );
		self::edit_section( $booking, $vehicles );
		echo '</aside></div>';
		GWR_Admin::page_end();
	}

	/** Handle status transition. */
	public static function handle_status() {
		self::require_capability();
		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		check_admin_referer( 'gwr_booking_status_' . $booking_id, 'gwr_booking_nonce' );
		$result = GWR_Bookings::change_status( $booking_id, sanitize_key( $_POST['new_status'] ?? '' ), sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ), $_POST['operation'] ?? array() );
		self::redirect( $booking_id, $result );
	}

	/** Handle controlled data update. */
	public static function handle_update() {
		self::require_capability();
		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		check_admin_referer( 'gwr_booking_update_' . $booking_id, 'gwr_booking_nonce' );
		$result = GWR_Bookings::update_admin( $booking_id, $_POST['booking'] ?? array() );
		self::redirect( $booking_id, $result );
	}

	/** Print a safe booking summary. */
	public static function handle_print() {
		self::require_capability();
		$booking_id = absint( $_GET['booking_id'] ?? 0 );
		check_admin_referer( 'gwr_booking_print_' . $booking_id );
		$document = GWR_Document_Service::generate( $booking_id, 'booking_summary' );
		if ( is_wp_error( $document ) ) { wp_die( esc_html( $document->get_error_message() ) ); }
		GWR_PDF_Service::stream_html( $document, '', false );
	}

	private static function list_row( $booking ) {
		$customer = json_decode( (string) $booking['customer_data_json'], true );
		$name = trim( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) );
		$url = admin_url( 'admin.php?page=gwr-booking-detail&booking_id=' . absint( $booking['id'] ) );
		echo '<tr><td><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $booking['booking_code'] ) . '</strong></a></td><td><span class="gwr-inline-status is-' . esc_attr( $booking['status'] ) . '">' . esc_html( GWR_Bookings::statuses()[ $booking['status'] ] ?? $booking['status'] ) . '</span></td><td>' . esc_html( $name ) . '<small>' . esc_html( $booking['customer_email'] ) . '</small></td><td>' . esc_html( $booking['vehicle_title'] ) . '</td><td>' . esc_html( GWR_Bookings::format_datetime( $booking['pickup_datetime'] ) ) . '</td><td>' . esc_html( GWR_Bookings::format_datetime( $booking['return_datetime'] ) ) . '</td><td>' . esc_html( GWR_Bookings::format_money( $booking['total_amount'], $booking['currency'] ) ) . '</td><td>' . esc_html( GWR_Bookings::format_datetime( $booking['created_at'] ) ) . '</td><td><a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html__( 'Apri', 'gest-web-rent' ) . '</a></td></tr>';
	}

	private static function summary_section( $booking, $items ) {
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><h2>' . esc_html__( 'Riepilogo', 'gest-web-rent' ) . '</h2><span class="gwr-inline-status is-' . esc_attr( $booking['status'] ) . '">' . esc_html( GWR_Bookings::statuses()[ $booking['status'] ] ) . '</span></div><div class="gwr-booking-kpis"><div><span>' . esc_html__( 'Veicolo', 'gest-web-rent' ) . '</span><strong>' . esc_html( $booking['vehicle_title'] ) . '</strong></div><div><span>' . esc_html__( 'Periodo', 'gest-web-rent' ) . '</span><strong>' . esc_html( GWR_Bookings::format_datetime( $booking['pickup_datetime'] ) . ' - ' . GWR_Bookings::format_datetime( $booking['return_datetime'] ) ) . '</strong></div><div><span>' . esc_html__( 'Localita', 'gest-web-rent' ) . '</span><strong>' . esc_html( $booking['pickup_location'] . ' - ' . $booking['return_location'] ) . '</strong></div><div><span>' . esc_html__( 'Totale', 'gest-web-rent' ) . '</span><strong>' . esc_html( GWR_Bookings::format_money( $booking['total_amount'], $booking['currency'] ) ) . '</strong></div></div><table class="widefat"><thead><tr><th>' . esc_html__( 'Voce', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Tipo', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Quantita', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Totale', 'gest-web-rent' ) . '</th></tr></thead><tbody>';
		foreach ( $items as $item ) { echo '<tr><td>' . esc_html( $item['item_name'] ) . '</td><td>' . esc_html( $item['item_type'] ) . '</td><td>' . esc_html( $item['quantity'] ) . '</td><td>' . esc_html( GWR_Bookings::format_money( $item['total_price'], $booking['currency'] ) ) . '</td></tr>'; }
		echo '</tbody></table></section>';
	}

	private static function people_section( $booking ) {
		echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Cliente e conducente', 'gest-web-rent' ) . '</h2><div class="gwr-surface-grid"><div><h3>' . esc_html__( 'Cliente', 'gest-web-rent' ) . '</h3>' . self::data_list( $booking['customer'], array( 'first_name', 'last_name', 'email', 'phone', 'tax_code', 'company_name', 'vat_number', 'address', 'city', 'country' ) ) . '</div><div><h3>' . esc_html__( 'Conducente', 'gest-web-rent' ) . '</h3>' . self::data_list( $booking['driver'], array( 'first_name', 'last_name', 'birth_date', 'license_type', 'license_number', 'license_country', 'license_issue_date', 'license_expiry_date' ) ) . '</div></div><p><strong>' . esc_html__( 'Consensi:', 'gest-web-rent' ) . '</strong> ' . esc_html( sprintf( __( 'privacy %s, condizioni %s, marketing %s', 'gest-web-rent' ), $booking['privacy_accepted_at'], $booking['terms_accepted_at'], $booking['marketing_consent'] ? __( 'si', 'gest-web-rent' ) : __( 'no', 'gest-web-rent' ) ) ) . '</p></section>';
	}

	private static function snapshot_section( $booking ) {
		$snapshot = $booking['snapshot'];
		echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Snapshot commerciale', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Questi dati restano invariati anche se condizioni e prezzi correnti vengono modificati.', 'gest-web-rent' ) . '</p><div class="gwr-booking-price-grid">';
		foreach ( array( 'base_amount' => 'Base', 'extras_amount' => 'Extra', 'coverages_amount' => 'Coperture', 'supplements_amount' => 'Supplementi', 'discounts_amount' => 'Sconti', 'taxes_amount' => 'Tasse', 'total_amount' => 'Totale noleggio', 'pay_now_amount' => 'Da pagare ora', 'pay_later_amount' => 'Saldo', 'deposit_amount' => 'Deposito al ritiro' ) as $key => $label ) { echo '<div><span>' . esc_html( $label ) . '</span><strong>' . esc_html( GWR_Bookings::format_money( $booking[ $key ] ?? 0, $booking['currency'] ) ) . '</strong></div>'; }
		echo '</div><details><summary>' . esc_html__( 'Condizioni applicate', 'gest-web-rent' ) . '</summary><pre class="gwr-snapshot-json">' . esc_html( wp_json_encode( $snapshot['rental_terms'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre></details><details><summary>' . esc_html__( 'Breakdown prezzo', 'gest-web-rent' ) . '</summary><pre class="gwr-snapshot-json">' . esc_html( wp_json_encode( $booking['pricing_snapshot'] ?? ( $snapshot['price'] ?? array() ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre></details></section>';
	}

	private static function operations_section( $booking ) {
		if ( empty( $booking['delivery'] ) && empty( $booking['return'] ) && empty( $booking['customer_notes'] ) && empty( $booking['admin_notes'] ) ) { return; }
		echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Operativita e note', 'gest-web-rent' ) . '</h2><div class="gwr-surface-grid">';
		if ( $booking['delivery'] ) { echo '<div><h3>' . esc_html__( 'Consegna', 'gest-web-rent' ) . '</h3>' . self::data_list( $booking['delivery'], array( 'effective_datetime', 'odometer', 'fuel_level', 'deposit_verified', 'notes' ) ) . '</div>'; }
		if ( $booking['return'] ) { echo '<div><h3>' . esc_html__( 'Riconsegna', 'gest-web-rent' ) . '</h3>' . self::data_list( $booking['return'], array( 'effective_datetime', 'odometer', 'fuel_level', 'damage', 'delay_minutes', 'notes' ) ) . '</div>'; }
		echo '</div>';
		if ( $booking['customer_notes'] ) { echo '<p><strong>' . esc_html__( 'Note cliente:', 'gest-web-rent' ) . '</strong> ' . nl2br( esc_html( $booking['customer_notes'] ) ) . '</p>'; }
		if ( $booking['admin_notes'] ) { echo '<p><strong>' . esc_html__( 'Note amministrative:', 'gest-web-rent' ) . '</strong> ' . nl2br( esc_html( $booking['admin_notes'] ) ) . '</p>'; }
		echo '</section>';
	}

	private static function logs_section( $logs ) {
		echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Cronologia', 'gest-web-rent' ) . '</h2><div class="gwr-booking-timeline">';
		foreach ( $logs as $log ) { echo '<article><time>' . esc_html( GWR_Bookings::format_datetime( $log['created_at'] ) ) . '</time><div><strong>' . esc_html( $log['action'] ) . '</strong><p>' . esc_html( trim( ( $log['old_status'] ? $log['old_status'] . ' -> ' : '' ) . $log['new_status'] . ( $log['note'] ? ' - ' . $log['note'] : '' ) ) ) . '</p><small>' . esc_html( $log['display_name'] ?: __( 'Sistema', 'gest-web-rent' ) ) . '</small></div></article>'; }
		echo '</div></section>';
	}

	private static function status_section( $booking ) {
		$next = GWR_Bookings::transitions()[ $booking['status'] ] ?? array();
		echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Cambia stato', 'gest-web-rent' ) . '</h2>';
		if ( empty( $next ) ) { echo '<p>' . esc_html__( 'Nessuna transizione disponibile.', 'gest-web-rent' ) . '</p></section>'; return; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-booking-status-form"><input type="hidden" name="action" value="gwr_booking_status" /><input type="hidden" name="booking_id" value="' . esc_attr( $booking['id'] ) . '" />'; wp_nonce_field( 'gwr_booking_status_' . $booking['id'], 'gwr_booking_nonce' );
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Nuovo stato', 'gest-web-rent' ) . '</span><select name="new_status" required><option value="">' . esc_html__( 'Seleziona', 'gest-web-rent' ) . '</option>'; foreach ( $next as $status ) { echo '<option value="' . esc_attr( $status ) . '">' . esc_html( GWR_Bookings::statuses()[ $status ] ) . '</option>'; } echo '</select></label><label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Nota o motivazione', 'gest-web-rent' ) . '</span><textarea name="note"></textarea></label>';
		echo '<details><summary>' . esc_html__( 'Dati consegna / riconsegna', 'gest-web-rent' ) . '</summary><div class="gwr-field-grid"><label class="gwr-field"><span>Data e ora effettiva</span><input type="datetime-local" name="operation[effective_datetime]" /></label><label class="gwr-field"><span>Chilometraggio</span><input type="number" min="0" name="operation[odometer]" /></label><label class="gwr-field"><span>Livello carburante</span><input type="text" name="operation[fuel_level]" /></label><label class="gwr-field"><span>Ritardo (minuti)</span><input type="number" min="0" name="operation[delay_minutes]" /></label><label class="gwr-check-card"><input type="checkbox" name="operation[deposit_verified]" value="1" /><span>Deposito verificato</span></label><label class="gwr-field gwr-field--wide"><span>Danni rilevati</span><textarea name="operation[damage]"></textarea></label><label class="gwr-field gwr-field--wide"><span>Note operative</span><textarea name="operation[notes]"></textarea></label></div></details><button class="button button-primary">' . esc_html__( 'Aggiorna stato', 'gest-web-rent' ) . '</button></form></section>';
	}

	private static function edit_section( $booking, $vehicles ) {
		if ( in_array( $booking['status'], array( 'rejected', 'cancelled', 'expired', 'completed', 'no_show' ), true ) ) {
			echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Pratica chiusa', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'I dati storici non possono essere modificati dopo la chiusura della prenotazione.', 'gest-web-rent' ) . '</p></section>';
			return;
		}
		$vehicle = GWR_CPT::get_vehicle( $booking['vehicle_id'] );
		$terms = $vehicle ? GWR_Rental_Terms::public_payload( $booking['vehicle_id'], $vehicle ) : array( 'extras' => array(), 'rental_terms' => array() );
		$selected_extras = array();
		foreach ( $booking['selection']['extras'] ?? array() as $row ) { $selected_extras[ $row['id'] ] = absint( $row['quantity'] ); }
		$selected_coverages = array_flip( $booking['selection']['coverages'] ?? array() );
		echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Modifica controllata', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Il salvataggio ricontrolla disponibilita e prezzo e aggiorna il blocco.', 'gest-web-rent' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-booking-edit-form"><input type="hidden" name="action" value="gwr_booking_update" /><input type="hidden" name="booking_id" value="' . esc_attr( $booking['id'] ) . '" />'; wp_nonce_field( 'gwr_booking_update_' . $booking['id'], 'gwr_booking_nonce' );
		echo '<label class="gwr-field"><span>Veicolo</span><select name="booking[vehicle_id]">'; foreach ( $vehicles as $vehicle ) { echo '<option value="' . esc_attr( $vehicle['id'] ) . '" ' . selected( $booking['vehicle_id'], $vehicle['id'], false ) . '>' . esc_html( $vehicle['title'] ) . '</option>'; } echo '</select></label>';
		foreach ( array( 'start_date' => substr( $booking['pickup_datetime'], 0, 10 ), 'end_date' => substr( $booking['return_datetime'], 0, 10 ) ) as $name => $value ) { echo '<label class="gwr-field"><span>' . esc_html( 'start_date' === $name ? __( 'Data ritiro', 'gest-web-rent' ) : __( 'Data riconsegna', 'gest-web-rent' ) ) . '</span><input type="date" name="booking[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '" required /></label>'; }
		foreach ( array( 'pickup_time' => substr( $booking['pickup_datetime'], 11, 5 ), 'return_time' => substr( $booking['return_datetime'], 11, 5 ) ) as $name => $value ) { echo '<label class="gwr-field"><span>' . esc_html( 'pickup_time' === $name ? __( 'Ora ritiro', 'gest-web-rent' ) : __( 'Ora riconsegna', 'gest-web-rent' ) ) . '</span><input type="time" name="booking[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '" required /></label>'; }
		foreach ( array( 'pickup_location' => $booking['pickup_location'], 'return_location' => $booking['return_location'], 'customer_first_name' => $booking['customer']['first_name'] ?? '', 'customer_last_name' => $booking['customer']['last_name'] ?? '', 'customer_email' => $booking['customer_email'], 'customer_phone' => $booking['customer_phone'], 'driver_first_name' => $booking['driver']['first_name'] ?? '', 'driver_last_name' => $booking['driver']['last_name'] ?? '', 'driver_birth_date' => $booking['driver']['birth_date'] ?? '', 'driver_license_type' => $booking['driver']['license_type'] ?? '', 'driver_license_number' => $booking['driver']['license_number'] ?? '', 'driver_license_country' => $booking['driver']['license_country'] ?? '', 'driver_license_issue_date' => $booking['driver']['license_issue_date'] ?? '', 'driver_license_expiry_date' => $booking['driver']['license_expiry_date'] ?? '' ) as $name => $value ) { $type = false !== strpos( $name, 'date' ) ? 'date' : ( 'customer_email' === $name ? 'email' : 'text' ); echo '<label class="gwr-field"><span>' . esc_html( ucwords( str_replace( '_', ' ', $name ) ) ) . '</span><input type="' . esc_attr( $type ) . '" name="booking[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '" required /></label>'; }
		echo '<input type="hidden" name="booking[selection_present]" value="1" /><fieldset><legend>' . esc_html__( 'Extra e coperture', 'gest-web-rent' ) . '</legend><div class="gwr-status-stack">';
		foreach ( $terms['extras'] as $extra ) { $id = sanitize_key( $extra['id'] ); $selected = isset( $selected_extras[ $id ] ) || ! empty( $extra['mandatory'] ); echo '<div class="gwr-status-line"><label><input type="checkbox" name="booking[selection][extras][' . esc_attr( $id ) . '][selected]" value="1" ' . checked( $selected, true, false ) . ( ! empty( $extra['mandatory'] ) ? ' disabled' : '' ) . ' /> ' . esc_html( $extra['name'] ) . '</label><input type="number" min="1" max="' . esc_attr( max( 1, absint( $extra['max_quantity'] ?? 1 ) ) ) . '" name="booking[selection][extras][' . esc_attr( $id ) . '][quantity]" value="' . esc_attr( $selected_extras[ $id ] ?? 1 ) . '" /></div>'; }
		foreach ( $terms['rental_terms']['insurance_coverages'] ?? array() as $coverage ) { if ( 'optional' !== ( $coverage['status'] ?? '' ) ) { continue; } $id = sanitize_key( $coverage['id'] ); echo '<label class="gwr-check-card"><input type="checkbox" name="booking[selection][coverages][]" value="' . esc_attr( $id ) . '" ' . checked( isset( $selected_coverages[ $id ] ), true, false ) . ' /><span>' . esc_html( $coverage['name'] ) . '</span></label>'; }
		echo '</div></fieldset>';
		echo '<label class="gwr-field"><span>' . esc_html__( 'Note amministrative', 'gest-web-rent' ) . '</span><textarea name="booking[admin_notes]">' . esc_textarea( $booking['admin_notes'] ) . '</textarea></label><button class="button button-primary">' . esc_html__( 'Salva e ricalcola', 'gest-web-rent' ) . '</button></form></section>';
	}

	private static function data_list( $data, $keys ) { $html = '<dl class="gwr-booking-data-list">'; foreach ( $keys as $key ) { if ( ! empty( $data[ $key ] ) ) { $html .= '<div><dt>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . '</dt><dd>' . esc_html( is_bool( $data[ $key ] ) ? ( $data[ $key ] ? 'Si' : 'No' ) : $data[ $key ] ) . '</dd></div>'; } } return $html . '</dl>'; }

	private static function print_people( $booking ) { echo '<section><h2>' . esc_html__( 'Cliente', 'gest-web-rent' ) . '</h2>' . self::data_list( $booking['customer'], array( 'first_name', 'last_name', 'email', 'phone', 'tax_code', 'company_name', 'vat_number', 'address', 'city' ) ) . '</section><section><h2>' . esc_html__( 'Conducente', 'gest-web-rent' ) . '</h2>' . self::data_list( $booking['driver'], array( 'first_name', 'last_name', 'birth_date', 'license_type', 'license_number', 'license_country', 'license_expiry_date' ) ) . '</section>'; }

	private static function notice() { if ( empty( $_GET['gwr_message'] ) ) { return; } $error = ! empty( $_GET['gwr_error'] ); echo '<div class="notice ' . esc_attr( $error ? 'notice-error' : 'notice-success' ) . ' inline is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['gwr_message'] ) ) ) . '</p></div>'; }

	private static function redirect( $booking_id, $result ) { $error = is_wp_error( $result ); $message = $error ? $result->get_error_message() : __( 'Prenotazione aggiornata.', 'gest-web-rent' ); wp_safe_redirect( add_query_arg( array( 'page' => 'gwr-booking-detail', 'booking_id' => absint( $booking_id ), 'gwr_message' => $message, 'gwr_error' => $error ? 1 : 0 ), admin_url( 'admin.php' ) ) ); exit; }

	private static function require_capability() { if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) ); } }
}
