<?php
/**
 * Secure self-service customer portal.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

class GWR_Customer_Portal {
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'private_headers' ), 1 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_action( 'admin_post_gwr_booking_recover', array( __CLASS__, 'handle_recover' ) );
		add_action( 'admin_post_nopriv_gwr_booking_recover', array( __CLASS__, 'handle_recover' ) );
		add_action( 'admin_post_gwr_booking_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_nopriv_gwr_booking_export', array( __CLASS__, 'handle_export' ) );
	}

	public static function private_headers() {
		if ( empty( $_GET['booking_code'] ) && empty( $_GET['gwr_token'] ) && empty( $_GET['booking_token'] ) ) { return; }
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Referrer-Policy: no-referrer', true );
	}

	public static function robots( $robots ) {
		if ( ! empty( $_GET['booking_code'] ) || ! empty( $_GET['gwr_token'] ) || ! empty( $_GET['booking_token'] ) ) {
			$robots['noindex'] = true; $robots['nofollow'] = true; $robots['noarchive'] = true;
		}
		return $robots;
	}

	public static function status_definitions() {
		return array(
			'pending' => array( 'label' => __( 'Richiesta ricevuta', 'gest-web-rent' ), 'description' => __( 'La richiesta e in valutazione.', 'gest-web-rent' ), 'tone' => 'info' ),
			'awaiting_payment' => array( 'label' => __( 'In attesa di pagamento', 'gest-web-rent' ), 'description' => __( 'Completa il pagamento per proseguire.', 'gest-web-rent' ), 'tone' => 'warning' ),
			'awaiting_bank_transfer' => array( 'label' => __( 'In attesa di bonifico', 'gest-web-rent' ), 'description' => __( 'Il bonifico sara verificato manualmente.', 'gest-web-rent' ), 'tone' => 'warning' ),
			'payment_failed' => array( 'label' => __( 'Pagamento non completato', 'gest-web-rent' ), 'description' => __( 'Puoi effettuare un nuovo tentativo.', 'gest-web-rent' ), 'tone' => 'danger' ),
			'confirmed' => array( 'label' => __( 'Confermata', 'gest-web-rent' ), 'description' => __( 'La prenotazione e confermata.', 'gest-web-rent' ), 'tone' => 'success' ),
			'delivered' => array( 'label' => __( 'Pronta per il ritiro', 'gest-web-rent' ), 'description' => __( 'Il veicolo e stato consegnato o predisposto.', 'gest-web-rent' ), 'tone' => 'success' ),
			'in_progress' => array( 'label' => __( 'Noleggio in corso', 'gest-web-rent' ), 'description' => __( 'Il noleggio e attivo.', 'gest-web-rent' ), 'tone' => 'info' ),
			'returned' => array( 'label' => __( 'Riconsegnata', 'gest-web-rent' ), 'description' => __( 'Il veicolo e stato riconsegnato.', 'gest-web-rent' ), 'tone' => 'success' ),
			'completed' => array( 'label' => __( 'Completata', 'gest-web-rent' ), 'description' => __( 'La pratica e conclusa.', 'gest-web-rent' ), 'tone' => 'success' ),
			'cancelled' => array( 'label' => __( 'Annullata', 'gest-web-rent' ), 'description' => __( 'La prenotazione e stata annullata.', 'gest-web-rent' ), 'tone' => 'danger' ),
			'rejected' => array( 'label' => __( 'Non confermata', 'gest-web-rent' ), 'description' => __( 'La richiesta non e stata accettata.', 'gest-web-rent' ), 'tone' => 'danger' ),
			'expired' => array( 'label' => __( 'Scaduta', 'gest-web-rent' ), 'description' => __( 'Il termine della richiesta e scaduto.', 'gest-web-rent' ), 'tone' => 'muted' ),
			'refunded' => array( 'label' => __( 'Rimborsata', 'gest-web-rent' ), 'description' => __( 'Il pagamento risulta rimborsato.', 'gest-web-rent' ), 'tone' => 'muted' ),
		);
	}

	public static function render_shortcode( $atts = array() ) {
		$query = is_array( $_GET ) ? wp_unslash( $_GET ) : array();
		$atts = shortcode_atts( array( 'booking_code' => '', 'booking_token' => '' ), $atts, 'gwr_booking_portal' );
		$code = sanitize_text_field( $query['booking_code'] ?? $atts['booking_code'] );
		$token = sanitize_text_field( $query['gwr_token'] ?? ( $query['booking_token'] ?? $atts['booking_token'] ) );
		$booking = $code ? self::booking_by_code( $code ) : null;
		$authorized = $booking && GWR_Bookings::verify_public_token( $booking, $token );
		ob_start();
		echo '<main class="gwr-customer-portal gwr-block" data-gwr-customer-portal>';
		if ( ! $authorized ) {
			self::render_access( $code, ( $code || $token ) );
			echo '</main>';
			return ob_get_clean();
		}
		self::render_notice();
		$status = self::status_definitions()[ $booking['status'] ] ?? array( 'label' => $booking['status'], 'description' => '', 'tone' => 'muted' );
		echo '<header class="gwr-portal-header"><div><span>' . esc_html__( 'La tua prenotazione', 'gest-web-rent' ) . '</span><h1>' . esc_html( $booking['booking_code'] ) . '</h1><p>' . esc_html( $booking['vehicle_title'] ) . '</p></div><div class="gwr-portal-status is-' . esc_attr( $status['tone'] ) . '"><strong>' . esc_html( $status['label'] ) . '</strong><span>' . esc_html( $status['description'] ) . '</span></div></header>';
		echo '<div class="gwr-portal-layout"><div class="gwr-portal-main">';
		self::render_trip( $booking );
		self::render_timeline( $booking );
		self::render_financials( $booking, $token );
		self::render_documents( $booking, $token );
		self::render_checklist_and_upload( $booking, $token );
		self::render_requests( $booking, $token );
		echo '</div><aside class="gwr-portal-side">';
		self::render_actions( $booking, $token );
		self::render_support( $booking );
		echo '</aside></div></main>';
		return ob_get_clean();
	}

	private static function render_access( $code, $invalid ) {
		echo '<section class="gwr-portal-access"><span>' . esc_html__( 'Area cliente', 'gest-web-rent' ) . '</span><h1>' . esc_html__( 'Recupera la prenotazione', 'gest-web-rent' ) . '</h1><p>' . esc_html__( 'Inserisci codice ed email. Per proteggere i tuoi dati invieremo un nuovo link sicuro senza mostrare qui il risultato della ricerca.', 'gest-web-rent' ) . '</p>';
		if ( $invalid ) { echo '<div class="gwr-portal-notice is-error" role="alert">' . esc_html__( 'Il link non e valido o e scaduto. Richiedine uno nuovo.', 'gest-web-rent' ) . '</div>'; }
		self::render_notice();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-portal-form"><input type="hidden" name="action" value="gwr_booking_recover" />';
		wp_nonce_field( 'gwr_booking_recover', 'gwr_recover_nonce' );
		echo '<label><span>' . esc_html__( 'Codice prenotazione', 'gest-web-rent' ) . '</span><input type="text" name="booking_code" value="' . esc_attr( $code ) . '" autocomplete="off" required /></label><label><span>' . esc_html__( 'Email utilizzata', 'gest-web-rent' ) . '</span><input type="email" name="customer_email" autocomplete="email" required /></label><label class="gwr-honeypot" aria-hidden="true"><span>Website</span><input type="text" name="website" tabindex="-1" autocomplete="off" /></label><button class="gwr-button">' . esc_html__( 'Invia link sicuro', 'gest-web-rent' ) . '</button></form></section>';
	}

	private static function render_trip( $booking ) {
		$vehicle = GWR_CPT::get_vehicle( $booking['vehicle_id'] );
		$cover_url = $vehicle ? GWR_CPT::cover_image_url( $vehicle['id'] ) : '';
		echo '<section class="gwr-portal-section gwr-portal-trip"><div class="gwr-portal-section__head"><h2>' . esc_html__( 'Il tuo noleggio', 'gest-web-rent' ) . '</h2></div><div class="gwr-portal-trip__body">';
		if ( $cover_url ) { echo '<img src="' . esc_url( $cover_url ) . '" alt="' . esc_attr( $booking['vehicle_title'] ) . '" width="480" height="320" />'; }
		echo '<dl><div><dt>' . esc_html__( 'Veicolo', 'gest-web-rent' ) . '</dt><dd>' . esc_html( $booking['vehicle_title'] ) . '</dd></div><div><dt>' . esc_html__( 'Ritiro', 'gest-web-rent' ) . '</dt><dd>' . esc_html( GWR_Bookings::format_datetime( $booking['pickup_datetime'] ) . ' - ' . $booking['pickup_location'] ) . '</dd></div><div><dt>' . esc_html__( 'Riconsegna', 'gest-web-rent' ) . '</dt><dd>' . esc_html( GWR_Bookings::format_datetime( $booking['return_datetime'] ) . ' - ' . $booking['return_location'] ) . '</dd></div><div><dt>' . esc_html__( 'Durata', 'gest-web-rent' ) . '</dt><dd>' . esc_html( sprintf( _n( '%d giorno', '%d giorni', absint( $booking['rental_days'] ), 'gest-web-rent' ), absint( $booking['rental_days'] ) ) ) . '</dd></div></dl></div>';
		$items = GWR_Bookings::items( $booking['id'] );
		if ( $items ) { echo '<ul class="gwr-portal-items">'; foreach ( $items as $item ) { echo '<li><span>' . esc_html( $item['item_name'] ) . '</span><strong>' . esc_html( GWR_Bookings::format_money( $item['total_price'], $booking['currency'] ) ) . '</strong></li>'; } echo '</ul>'; }
		echo '</section>';
	}

	private static function render_timeline( $booking ) {
		$events = array( array( 'label' => __( 'Richiesta inviata', 'gest-web-rent' ), 'date' => $booking['created_at'] ) );
		if ( in_array( $booking['payment_status'], array( 'paid', 'partially_refunded', 'refunded' ), true ) ) { $events[] = array( 'label' => __( 'Pagamento ricevuto', 'gest-web-rent' ), 'date' => self::payment_date( $booking['id'] ) ); }
		if ( $booking['confirmed_at'] ) { $events[] = array( 'label' => __( 'Prenotazione confermata', 'gest-web-rent' ), 'date' => $booking['confirmed_at'] ); }
		if ( in_array( $booking['status'], array( 'delivered', 'in_progress', 'returned', 'completed' ), true ) ) { $events[] = array( 'label' => __( 'Ritiro', 'gest-web-rent' ), 'date' => $booking['delivery']['effective_datetime'] ?? $booking['pickup_datetime'] ); }
		if ( in_array( $booking['status'], array( 'returned', 'completed' ), true ) ) { $events[] = array( 'label' => __( 'Riconsegna', 'gest-web-rent' ), 'date' => $booking['return']['effective_datetime'] ?? $booking['return_datetime'] ); }
		echo '<section class="gwr-portal-section"><div class="gwr-portal-section__head"><h2>' . esc_html__( 'Avanzamento', 'gest-web-rent' ) . '</h2></div><ol class="gwr-portal-timeline">';
		foreach ( $events as $event ) { echo '<li><span aria-hidden="true"></span><div><strong>' . esc_html( $event['label'] ) . '</strong><time>' . esc_html( GWR_Bookings::format_datetime( $event['date'] ) ) . '</time></div></li>'; }
		echo '</ol></section>';
	}

	private static function render_financials( $booking, $token ) {
		$paid = self::paid_amount( $booking['id'] );
		$balance = max( 0, (float) $booking['total_amount'] - $paid );
		echo '<section id="gwr-payment" class="gwr-portal-section"><div class="gwr-portal-section__head"><h2>' . esc_html__( 'Riepilogo economico', 'gest-web-rent' ) . '</h2><span>' . esc_html( self::payment_label( $booking['payment_status'] ) ) . '</span></div><div class="gwr-portal-money"><div><span>' . esc_html__( 'Totale', 'gest-web-rent' ) . '</span><strong>' . esc_html( GWR_Bookings::format_money( $booking['total_amount'], $booking['currency'] ) ) . '</strong></div><div><span>' . esc_html__( 'Pagato', 'gest-web-rent' ) . '</span><strong>' . esc_html( GWR_Bookings::format_money( $paid, $booking['currency'] ) ) . '</strong></div><div><span>' . esc_html__( 'Saldo', 'gest-web-rent' ) . '</span><strong>' . esc_html( GWR_Bookings::format_money( $balance, $booking['currency'] ) ) . '</strong></div><div><span>' . esc_html__( 'Deposito', 'gest-web-rent' ) . '</span><strong>' . esc_html( GWR_Bookings::format_money( $booking['deposit_amount'], $booking['currency'] ) ) . '</strong></div></div>';
		$payable = $balance > 0 && in_array( $booking['status'], array( 'pending', 'awaiting_payment', 'payment_failed', 'awaiting_bank_transfer' ), true ) && ( empty( $booking['expires_at'] ) || $booking['expires_at'] > current_time( 'mysql' ) );
		if ( $payable && 'stripe' === $booking['payment_method'] ) {
			echo '<div class="gwr-payment-status gwr-portal-payment-action" data-gwr-payment-status data-booking-code="' . esc_attr( $booking['booking_code'] ) . '" data-booking-token="' . esc_attr( $token ) . '" data-payment-state="portal"><p data-gwr-payment-message>' . esc_html__( 'Lo stato viene verificato direttamente dal server.', 'gest-web-rent' ) . '</p><div data-gwr-payment-summary aria-live="polite"></div><div class="gwr-payment-status__actions"><button type="button" class="gwr-button-secondary" data-gwr-payment-refresh>' . esc_html__( 'Verifica stato', 'gest-web-rent' ) . '</button><button type="button" class="gwr-button" data-gwr-payment-retry>' . esc_html( sprintf( __( 'Paga %s con Stripe', 'gest-web-rent' ), GWR_Bookings::format_money( $balance, $booking['currency'] ) ) ) . '</button></div></div>';
		}
		if ( $payable && 'bank_transfer' === $booking['payment_method'] ) { self::render_bank( $booking, $balance ); }
		echo '</section>';
	}

	private static function render_bank( $booking, $balance ) {
		$settings = GWR_Pricing_Service::get_settings();
		if ( empty( $settings['bank_transfer_enabled'] ) || empty( $settings['bank_iban'] ) ) { return; }
		$reason = str_replace( '{booking_code}', $booking['booking_code'], $settings['bank_reason_template'] ?? $booking['booking_code'] );
		$due_date = wp_date( 'd-m-Y', time() + max( 1, absint( $settings['bank_due_days'] ?? 2 ) ) * DAY_IN_SECONDS );
		echo '<div class="gwr-portal-bank"><h3>' . esc_html__( 'Istruzioni bonifico', 'gest-web-rent' ) . '</h3><dl>';
		foreach ( array( __( 'Intestatario', 'gest-web-rent' ) => $settings['bank_account_holder'], 'IBAN' => $settings['bank_iban'], 'BIC' => $settings['bank_bic'], __( 'Banca', 'gest-web-rent' ) => $settings['bank_name'], __( 'Importo', 'gest-web-rent' ) => GWR_Bookings::format_money( $balance, $booking['currency'] ), __( 'Causale', 'gest-web-rent' ) => $reason, __( 'Scadenza', 'gest-web-rent' ) => $due_date ) as $label => $value ) { if ( $value ) { echo '<div><dt>' . esc_html( $label ) . '</dt><dd><code>' . esc_html( $value ) . '</code><button type="button" class="gwr-copy-button" data-gwr-copy="' . esc_attr( $value ) . '">' . esc_html__( 'Copia', 'gest-web-rent' ) . '</button></dd></div>'; } }
		echo '</dl><p>' . nl2br( esc_html( $settings['bank_instructions'] ?? '' ) ) . '</p><small>' . esc_html__( 'Il bonifico sara registrato solo dopo la verifica amministrativa.', 'gest-web-rent' ) . '</small></div>';
	}

	private static function render_documents( $booking, $token ) {
		$types = GWR_Document_Service::document_types();
		$documents = GWR_Document_Service::documents_for_booking( $booking['id'] );
		$visible = array();
		foreach ( $documents as $document ) {
			$config = $types[ $document['document_type'] ] ?? array();
			if ( ! empty( $config['public'] ) && in_array( $document['status'], array( 'generated', 'sent', 'signed' ), true ) && ! isset( $visible[ $document['document_type'] ] ) ) { $visible[ $document['document_type'] ] = $document; }
		}
		echo '<section class="gwr-portal-section"><div class="gwr-portal-section__head"><h2>' . esc_html__( 'Documenti', 'gest-web-rent' ) . '</h2></div>';
		if ( ! $visible ) { echo '<p>' . esc_html__( 'Non ci sono ancora documenti disponibili.', 'gest-web-rent' ) . '</p>'; }
		else { echo '<div class="gwr-portal-documents">'; foreach ( $visible as $document ) { $label = $types[ $document['document_type'] ]['label']; $url = GWR_Document_Service::document_url( $document, 'download', array( 'booking_code' => $booking['booking_code'], 'gwr_token' => $token ) ); echo '<article><div><strong>' . esc_html( $label ) . '</strong><span>' . esc_html( $document['document_number'] . ' v' . $document['version'] ) . '</span></div><a class="gwr-button-secondary" href="' . esc_url( $url ) . '">' . esc_html( sprintf( __( 'Scarica %s', 'gest-web-rent' ), $label ) ) . '</a></article>'; } echo '</div>'; }
		echo '</section>';
	}

	private static function render_checklist_and_upload( $booking, $token ) {
		$required = $booking['snapshot']['rental_terms']['required_documents'] ?? array();
		$attachments = GWR_Attachment_Service::attachments_for_booking( $booking['id'], true );
		echo '<section class="gwr-portal-section"><div class="gwr-portal-section__head"><h2>' . esc_html__( 'Documenti da fornire', 'gest-web-rent' ) . '</h2></div>';
		if ( $required ) { echo '<ul class="gwr-document-checklist">'; foreach ( $required as $document ) { $label = is_array( $document ) ? ( $document['label'] ?? $document['name'] ?? __( 'Documento', 'gest-web-rent' ) ) : $document; $state = __( 'Da caricare', 'gest-web-rent' ); foreach ( $attachments as $attachment ) { if ( false !== stripos( (string) $attachment['description'], (string) $label ) ) { $states = array( 'pending' => __( 'Caricato - da verificare', 'gest-web-rent' ), 'reviewing' => __( 'Da verificare', 'gest-web-rent' ), 'approved' => __( 'Approvato', 'gest-web-rent' ), 'rejected' => __( 'Rifiutato', 'gest-web-rent' ) ); $state = $states[ $attachment['verification_status'] ?? 'pending' ] ?? __( 'Caricato', 'gest-web-rent' ); break; } } echo '<li><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $state ) . '</strong></li>'; } echo '</ul>'; }
		else { echo '<p>' . esc_html__( 'Nessun documento aggiuntivo richiesto.', 'gest-web-rent' ) . '</p>'; }
		$settings = GWR_Document_Service::get_settings();
		if ( ! empty( $settings['attachments_enabled'] ) && ! in_array( $booking['status'], array( 'cancelled', 'expired', 'rejected', 'completed' ), true ) ) {
			echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-portal-upload"><input type="hidden" name="action" value="gwr_customer_attachment_upload" /><input type="hidden" name="booking_code" value="' . esc_attr( $booking['booking_code'] ) . '" /><input type="hidden" name="gwr_token" value="' . esc_attr( $token ) . '" />'; wp_nonce_field( 'gwr_customer_attachment_upload_' . $booking['id'], 'gwr_upload_nonce' );
			echo '<label><span>' . esc_html__( 'Tipo documento', 'gest-web-rent' ) . '</span><input type="text" name="description" maxlength="120" required /></label><label><span>' . esc_html__( 'File PDF, JPG o PNG', 'gest-web-rent' ) . '</span><input type="file" name="gwr_attachment_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required /></label><button class="gwr-button-secondary">' . esc_html__( 'Invia documento', 'gest-web-rent' ) . '</button><small>' . esc_html( sprintf( __( 'Dimensione massima %d MB. Il file resta in storage protetto.', 'gest-web-rent' ), absint( $settings['max_upload_mb'] ) ) ) . '</small></form>';
		}
		echo '</section>';
	}

	private static function render_requests( $booking, $token ) {
		$requests = GWR_Customer_Request_Service::for_booking( $booking['id'] );
		echo '<section class="gwr-portal-section"><div class="gwr-portal-section__head"><h2>' . esc_html__( 'Richieste', 'gest-web-rent' ) . '</h2></div>';
		if ( $requests ) { echo '<div class="gwr-portal-requests">'; foreach ( $requests as $request ) { echo '<article><div><strong>' . esc_html( 'cancellation' === $request['request_kind'] ? __( 'Richiesta annullamento', 'gest-web-rent' ) : ( GWR_Customer_Request_Service::types()[ $request['request_type'] ] ?? $request['request_type'] ) ) . '</strong><time>' . esc_html( GWR_Bookings::format_datetime( $request['created_at'] ) ) . '</time>' . ( null !== $request['estimated_difference'] ? '<small>' . esc_html( sprintf( __( 'Stima non definitiva: %s', 'gest-web-rent' ), GWR_Bookings::format_money( $request['estimated_difference'], $booking['currency'] ) ) ) . '</small>' : '' ) . '</div><span>' . esc_html( GWR_Customer_Request_Service::statuses()[ $request['status'] ] ?? $request['status'] ) . '</span></article>'; } echo '</div>'; }
		if ( ! in_array( $booking['status'], array( 'cancelled', 'expired', 'rejected', 'completed', 'returned' ), true ) ) {
			echo '<details class="gwr-portal-request-form"><summary>' . esc_html__( 'Richiedi una modifica', 'gest-web-rent' ) . '</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gwr_customer_request" /><input type="hidden" name="request_kind" value="change" /><input type="hidden" name="booking_code" value="' . esc_attr( $booking['booking_code'] ) . '" /><input type="hidden" name="gwr_token" value="' . esc_attr( $token ) . '" />'; wp_nonce_field( 'gwr_customer_request_' . $booking['id'], 'gwr_request_nonce' );
			echo '<label><span>' . esc_html__( 'Tipo', 'gest-web-rent' ) . '</span><select name="request_type">'; foreach ( GWR_Customer_Request_Service::types() as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; } echo '</select></label><div class="gwr-portal-proposed-grid"><label><span>' . esc_html__( 'Nuova data ritiro', 'gest-web-rent' ) . '</span><input type="date" name="proposed[start_date]" /></label><label><span>' . esc_html__( 'Nuova data riconsegna', 'gest-web-rent' ) . '</span><input type="date" name="proposed[end_date]" /></label><label><span>' . esc_html__( 'Nuovo orario ritiro', 'gest-web-rent' ) . '</span><input type="time" name="proposed[pickup_time]" /></label><label><span>' . esc_html__( 'Nuovo orario riconsegna', 'gest-web-rent' ) . '</span><input type="time" name="proposed[return_time]" /></label><label><span>' . esc_html__( 'Nuova sede ritiro', 'gest-web-rent' ) . '</span><input type="text" name="proposed[pickup_location]" /></label><label><span>' . esc_html__( 'Nuova sede riconsegna', 'gest-web-rent' ) . '</span><input type="text" name="proposed[return_location]" /></label></div><label><span>' . esc_html__( 'Descrizione e motivazione', 'gest-web-rent' ) . '</span><textarea name="description" rows="4" minlength="10" required></textarea></label><button class="gwr-button-secondary">' . esc_html__( 'Invia richiesta', 'gest-web-rent' ) . '</button><small>' . esc_html__( 'La richiesta non modifica automaticamente disponibilita, prezzo o prenotazione.', 'gest-web-rent' ) . '</small></form></details>';
		}
		if ( in_array( $booking['status'], array( 'pending', 'awaiting_payment', 'awaiting_bank_transfer', 'payment_failed', 'confirmed' ), true ) ) {
			$policy = $booking['snapshot']['rental_terms']['cancellation_policy'] ?? array();
			echo '<details class="gwr-portal-request-form is-cancellation"><summary>' . esc_html__( 'Richiedi annullamento', 'gest-web-rent' ) . '</summary><p>' . esc_html( is_array( $policy ) ? ( $policy['description'] ?? __( 'Penali e rimborso saranno verificati secondo le condizioni accettate.', 'gest-web-rent' ) ) : $policy ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gwr_customer_request" /><input type="hidden" name="request_kind" value="cancellation" /><input type="hidden" name="booking_code" value="' . esc_attr( $booking['booking_code'] ) . '" /><input type="hidden" name="gwr_token" value="' . esc_attr( $token ) . '" />'; wp_nonce_field( 'gwr_customer_request_' . $booking['id'], 'gwr_request_nonce' );
			echo '<label><span>' . esc_html__( 'Motivazione', 'gest-web-rent' ) . '</span><textarea name="description" rows="4" minlength="10" required></textarea></label><label class="gwr-check-card"><input type="checkbox" name="confirmed" value="1" required /><span>' . esc_html__( 'Confermo di voler inviare la richiesta. La prenotazione non sara annullata automaticamente.', 'gest-web-rent' ) . '</span></label><button class="gwr-button-secondary">' . esc_html__( 'Invia richiesta di annullamento', 'gest-web-rent' ) . '</button></form></details>';
		}
		echo '</section>';
	}

	private static function render_actions( $booking, $token ) {
		$settings = GWR_Notification_Service::settings();
		$export = wp_nonce_url( add_query_arg( array( 'action' => 'gwr_booking_export', 'booking_code' => $booking['booking_code'], 'gwr_token' => $token ), admin_url( 'admin-post.php' ) ), 'gwr_booking_export_' . $booking['id'] );
		echo '<section class="gwr-portal-actions"><h2>' . esc_html__( 'Azioni', 'gest-web-rent' ) . '</h2><a class="gwr-button-secondary" href="#gwr-payment">' . esc_html__( 'Vai al pagamento', 'gest-web-rent' ) . '</a><a class="gwr-button-secondary" href="' . esc_url( $export ) . '">' . esc_html__( 'Esporta i miei dati', 'gest-web-rent' ) . '</a><a class="gwr-button-secondary" href="' . esc_url( remove_query_arg( array( 'booking_code', 'gwr_token', 'booking_token' ), $settings['portal_url'] ) ) . '">' . esc_html__( 'Esci dall area cliente', 'gest-web-rent' ) . '</a></section>';
	}

	private static function render_support( $booking ) {
		$settings = GWR_Admin::get_settings();
		$email = sanitize_email( $settings['contact_email'] ?? '' );
		$phone = preg_replace( '/\D+/', '', (string) ( $settings['whatsapp_number'] ?? '' ) );
		echo '<section class="gwr-portal-support"><h2>' . esc_html__( 'Assistenza', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Indica sempre il codice prenotazione quando ci contatti.', 'gest-web-rent' ) . '</p>';
		if ( $phone ) { $message = rawurlencode( sprintf( __( 'Ciao, richiedo assistenza per la prenotazione %s.', 'gest-web-rent' ), $booking['booking_code'] ) ); echo '<a class="gwr-button" target="_blank" rel="noopener noreferrer" href="' . esc_url( 'https://wa.me/' . $phone . '?text=' . $message ) . '">' . esc_html__( 'WhatsApp', 'gest-web-rent' ) . '</a>'; }
		if ( is_email( $email ) ) { echo '<a class="gwr-button-secondary" href="mailto:' . esc_attr( $email ) . '?subject=' . rawurlencode( 'Prenotazione ' . $booking['booking_code'] ) . '">' . esc_html__( 'Email', 'gest-web-rent' ) . '</a>'; }
		if ( ! $phone && ! is_email( $email ) ) { echo '<p>' . esc_html__( 'Contatti non ancora configurati. Riprova piu tardi.', 'gest-web-rent' ) . '</p>'; }
		echo '</section>';
	}

	public static function handle_recover() {
		check_admin_referer( 'gwr_booking_recover', 'gwr_recover_nonce' );
		$neutral = __( 'Se i dati inseriti corrispondono a una prenotazione, riceverai un link per accedervi.', 'gest-web-rent' );
		if ( ! empty( $_POST['website'] ) || is_wp_error( self::rate_limit( 'recover', 5, HOUR_IN_SECONDS, sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) ) ) ) ) { self::redirect_message( $neutral, false ); }
		$code = sanitize_text_field( wp_unslash( $_POST['booking_code'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) );
		$booking = self::booking_by_code( $code );
		if ( $booking && is_email( $email ) && hash_equals( strtolower( $booking['customer_email'] ), strtolower( $email ) ) ) {
			$token = GWR_Bookings::rotate_public_token( $booking['id'] );
			if ( ! is_wp_error( $token ) ) { $booking = GWR_Bookings::get( $booking['id'] ); $booking['public_token'] = $token; GWR_Notification_Service::send_event( $booking, 'booking_pending_confirmation', array( 'reference' => 'recovery:' . wp_date( 'Y-m-d-H' ), 'booking_token' => $token, 'manual' => true ) ); }
		}
		self::redirect_message( $neutral, false );
	}

	public static function handle_export() {
		$code = sanitize_text_field( wp_unslash( $_GET['booking_code'] ?? '' ) ); $token = sanitize_text_field( wp_unslash( $_GET['gwr_token'] ?? '' ) ); $booking = self::booking_by_code( $code );
		if ( ! $booking || ! GWR_Bookings::verify_public_token( $booking, $token ) ) { wp_die( esc_html__( 'Accesso non autorizzato.', 'gest-web-rent' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'gwr_booking_export_' . $booking['id'] );
		$data = array( 'booking_code' => $booking['booking_code'], 'status' => self::status_definitions()[ $booking['status'] ]['label'] ?? $booking['status'], 'vehicle' => $booking['vehicle_title'], 'pickup' => array( 'datetime' => $booking['pickup_datetime'], 'location' => $booking['pickup_location'] ), 'return' => array( 'datetime' => $booking['return_datetime'], 'location' => $booking['return_location'] ), 'customer' => $booking['customer'], 'driver' => $booking['driver'], 'items' => GWR_Bookings::items( $booking['id'] ), 'amounts' => array( 'total' => $booking['total_amount'], 'paid' => self::paid_amount( $booking['id'] ), 'deposit' => $booking['deposit_amount'], 'currency' => $booking['currency'] ), 'requests' => GWR_Customer_Request_Service::for_booking( $booking['id'] ) );
		nocache_headers(); header( 'Content-Type: application/json; charset=UTF-8' ); header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( strtolower( $booking['booking_code'] ) . '-dati.json' ) . '"' ); echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); exit;
	}

	private static function booking_by_code( $code ) { global $wpdb; $id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GWR_Bookings::table() . ' WHERE booking_code=%s', $code ) ); return $id ? GWR_Bookings::get( $id ) : null; }
	private static function paid_amount( $booking_id ) { global $wpdb; return ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(amount-amount_refunded),0) FROM ' . GWR_Payment_Service::table() . " WHERE booking_id=%d AND status IN ('paid','partially_refunded')", $booking_id ) ) ) / 100; }
	private static function payment_date( $booking_id ) { global $wpdb; return $wpdb->get_var( $wpdb->prepare( 'SELECT paid_at FROM ' . GWR_Payment_Service::table() . " WHERE booking_id=%d AND paid_at IS NOT NULL ORDER BY paid_at ASC LIMIT 1", $booking_id ) ); }
	private static function payment_label( $status ) { $labels = array( 'not_required' => __( 'Non richiesto', 'gest-web-rent' ), 'pending' => __( 'In attesa', 'gest-web-rent' ), 'processing' => __( 'In elaborazione', 'gest-web-rent' ), 'paid' => __( 'Pagato', 'gest-web-rent' ), 'failed' => __( 'Non riuscito', 'gest-web-rent' ), 'partially_refunded' => __( 'Parzialmente rimborsato', 'gest-web-rent' ), 'refunded' => __( 'Rimborsato', 'gest-web-rent' ) ); return $labels[ $status ] ?? sanitize_text_field( $status ); }
	private static function rate_limit( $action, $max, $ttl, $identity = '' ) { $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ); $key = 'gwr_portal_' . sanitize_key( $action ) . '_' . substr( hash_hmac( 'sha256', $ip . '|' . strtolower( $identity ), wp_salt( 'nonce' ) ), 0, 32 ); $count = absint( get_transient( $key ) ); if ( $count >= $max ) { return new WP_Error( 'gwr_rate_limit', __( 'Troppe richieste.', 'gest-web-rent' ) ); } set_transient( $key, $count + 1, $ttl ); return true; }
	private static function render_notice() { if ( empty( $_GET['gwr_portal_message'] ) ) { return; } echo '<div class="gwr-portal-notice' . ( ! empty( $_GET['gwr_portal_error'] ) ? ' is-error' : '' ) . '" role="status">' . esc_html( sanitize_text_field( wp_unslash( $_GET['gwr_portal_message'] ) ) ) . '</div>'; }
	private static function redirect_message( $message, $error ) { $url = wp_get_referer() ?: GWR_Notification_Service::settings()['portal_url']; wp_safe_redirect( add_query_arg( array( 'gwr_portal_message' => rawurlencode( $message ), 'gwr_portal_error' => $error ? 1 : 0 ), remove_query_arg( array( 'booking_code', 'gwr_token', 'booking_token' ), $url ) ) ); exit; }
}
