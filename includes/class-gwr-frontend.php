<?php
/**
 * Frontend catalog component.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public catalog, AJAX filtering and modal data.
 */
class GWR_Frontend {
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'gwr_catalog', array( __CLASS__, 'catalog_shortcode' ) );
		add_shortcode( 'gest_web_rent_catalog', array( __CLASS__, 'catalog_shortcode' ) );
		add_shortcode( 'gwr_payment_status', array( __CLASS__, 'payment_status_shortcode' ) );
		add_shortcode( 'gwr_booking_portal', array( __CLASS__, 'booking_portal_shortcode' ) );
		add_filter( 'the_content', array( __CLASS__, 'maybe_prepend_payment_status' ), 8 );
		add_action( 'wp_ajax_gwr_filter_catalog', array( __CLASS__, 'ajax_filter_catalog' ) );
		add_action( 'wp_ajax_nopriv_gwr_filter_catalog', array( __CLASS__, 'ajax_filter_catalog' ) );
		add_action( 'wp_ajax_gwr_vehicle_detail', array( __CLASS__, 'ajax_vehicle_detail' ) );
		add_action( 'wp_ajax_nopriv_gwr_vehicle_detail', array( __CLASS__, 'ajax_vehicle_detail' ) );
		add_action( 'wp_ajax_gwr_create_booking', array( __CLASS__, 'ajax_create_booking' ) );
		add_action( 'wp_ajax_nopriv_gwr_create_booking', array( __CLASS__, 'ajax_create_booking' ) );
		add_action( 'wp_ajax_gwr_payment_status', array( __CLASS__, 'ajax_payment_status' ) );
		add_action( 'wp_ajax_nopriv_gwr_payment_status', array( __CLASS__, 'ajax_payment_status' ) );
		add_action( 'wp_ajax_gwr_retry_payment', array( __CLASS__, 'ajax_retry_payment' ) );
		add_action( 'wp_ajax_nopriv_gwr_retry_payment', array( __CLASS__, 'ajax_retry_payment' ) );
	}

	/** Validate and create a booking request. */
	public static function ajax_create_booking() {
		check_ajax_referer( 'gwr_catalog_nonce', 'nonce' );
		$raw = isset( $_POST['booking'] ) ? json_decode( wp_unslash( $_POST['booking'] ), true ) : array();
		$raw = is_array( $raw ) ? $raw : array();
		$antispam = GWR_Bookings::check_antispam( $raw );
		if ( is_wp_error( $antispam ) ) {
			wp_send_json_error( array( 'message' => $antispam->get_error_message(), 'code' => $antispam->get_error_code() ), 429 );
		}
		$result = GWR_Bookings::create( $raw );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code(), 'field' => is_array( $data ) ? ( $data['field'] ?? '' ) : '' ), 400 );
		}
		$payment = GWR_Payment_Service::start_payment_for_booking( $result, $raw['payment_method'] ?? '' );
		if ( is_wp_error( $payment ) ) {
			wp_send_json_error( array( 'message' => $payment->get_error_message(), 'code' => $payment->get_error_code(), 'booking_code' => $result['booking_code'] ), 400 );
		}
		wp_send_json_success( array(
			'booking_code' => $result['booking_code'],
			'booking_token' => sanitize_text_field( $result['public_token'] ?? '' ),
			'status' => GWR_Bookings::statuses()[ $result['status'] ] ?? $result['status'],
			'vehicle' => $result['vehicle_title'],
			'period' => GWR_Bookings::format_datetime( $result['pickup_datetime'] ) . ' - ' . GWR_Bookings::format_datetime( $result['return_datetime'] ),
			'total' => GWR_Bookings::format_money( $result['total_amount'], $result['currency'] ),
			'pay_now' => GWR_Bookings::format_money( $result['pay_now_amount'], $result['currency'] ),
			'pay_later' => GWR_Bookings::format_money( $result['pay_later_amount'], $result['currency'] ),
			'deposit' => GWR_Bookings::format_money( $result['deposit_amount'], $result['currency'] ),
			'payment_method' => sanitize_key( $payment['method'] ?? ( $result['payment_method'] ?? 'request' ) ),
			'payment_required' => ! empty( $payment['required'] ),
			'redirect_url' => esc_url_raw( $payment['redirect_url'] ?? '' ),
			'bank' => $payment['bank'] ?? null,
		) );
	}

	/** Return the trusted payment state for a booking/session lookup. */
	public static function ajax_payment_status() {
		check_ajax_referer( 'gwr_catalog_nonce', 'nonce' );
		$code = isset( $_POST['booking_code'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_code'] ) ) : '';
		$token = isset( $_POST['booking_token'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_token'] ) ) : '';
		if ( ! $code ) {
			wp_send_json_error( array( 'message' => __( 'Codice prenotazione mancante.', 'gest-web-rent' ) ), 400 );
		}
		$booking = self::booking_by_code( $code );
		if ( ! $booking ) {
			wp_send_json_error( array( 'message' => __( 'Prenotazione non trovata.', 'gest-web-rent' ) ), 404 );
		}
		if ( ! current_user_can( 'manage_options' ) && ! GWR_Bookings::verify_public_token( $booking, $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Token prenotazione non valido.', 'gest-web-rent' ) ), 403 );
		}
		wp_send_json_success( array(
			'booking_code' => $booking['booking_code'],
			'booking_status' => $booking['status'],
			'booking_status_label' => GWR_Bookings::statuses()[ $booking['status'] ] ?? $booking['status'],
			'payment_status' => $booking['payment_status'],
			'payment_method' => $booking['payment_method'],
			'total' => GWR_Bookings::format_money( $booking['total_amount'], $booking['currency'] ),
			'pay_now' => GWR_Bookings::format_money( $booking['pay_now_amount'], $booking['currency'] ),
			'pay_later' => GWR_Bookings::format_money( $booking['pay_later_amount'], $booking['currency'] ),
		) );
	}

	/** Retry a payment from the trusted public token. */
	public static function ajax_retry_payment() {
		check_ajax_referer( 'gwr_catalog_nonce', 'nonce' );
		$code = isset( $_POST['booking_code'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_code'] ) ) : '';
		$token = isset( $_POST['booking_token'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_token'] ) ) : '';
		$method = isset( $_POST['payment_method'] ) ? sanitize_key( wp_unslash( $_POST['payment_method'] ) ) : '';
		if ( ! $code ) {
			wp_send_json_error( array( 'message' => __( 'Codice prenotazione mancante.', 'gest-web-rent' ) ), 400 );
		}
		$booking = self::booking_by_code( $code );
		if ( ! $booking ) {
			wp_send_json_error( array( 'message' => __( 'Prenotazione non trovata.', 'gest-web-rent' ) ), 404 );
		}
		if ( ! current_user_can( 'manage_options' ) && ! GWR_Bookings::verify_public_token( $booking, $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Token prenotazione non valido.', 'gest-web-rent' ) ), 403 );
		}
		$result = GWR_Payment_Service::retry_payment_for_booking( $booking, $method );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ), 400 );
		}
		wp_send_json_success( array(
			'booking_code' => $booking['booking_code'],
			'payment_method' => sanitize_key( $result['method'] ?? $booking['payment_method'] ),
			'payment_required' => ! empty( $result['required'] ),
			'redirect_url' => esc_url_raw( $result['redirect_url'] ?? '' ),
			'status' => sanitize_key( $result['status'] ?? '' ),
			'bank' => $result['bank'] ?? null,
		) );
	}

	/** Load the extended modal payload only when requested. */
	public static function ajax_vehicle_detail() {
		check_ajax_referer( 'gwr_catalog_nonce', 'nonce' );
		$vehicle_id = isset( $_POST['vehicle_id'] ) ? absint( $_POST['vehicle_id'] ) : 0;
		$vehicle = $vehicle_id ? GWR_CPT::get_vehicle( $vehicle_id ) : null;
		if ( ! $vehicle || 'active' !== $vehicle['status'] ) {
			wp_send_json_error( array( 'message' => __( 'Veicolo non disponibile.', 'gest-web-rent' ) ), 404 );
		}
		wp_send_json_success( array( 'vehicle' => GWR_CPT::public_payload( $vehicle, true ) ) );
	}

	public static function register_assets() {
		wp_register_style( 'gwr-public', GWR_PLUGIN_URL . 'public/assets/css/gwr-public.css', array(), gwr_asset_version( 'public/assets/css/gwr-public.css' ) );
		wp_register_script( 'gwr-public', GWR_PLUGIN_URL . 'public/assets/js/gwr-public.js', array(), gwr_asset_version( 'public/assets/js/gwr-public.js' ), true );
		if ( ! empty( $_GET['gwr_payment'] ) ) {
			self::enqueue_public_assets();
		}
	}

	public static function catalog_shortcode( $atts ) {
		self::enqueue_public_assets();
		$atts = shortcode_atts( array( 'title' => __( 'Catalogo noleggio', 'gest-web-rent' ), 'subtitle' => __( 'Scegli le date, filtra il parco veicoli e apri i dettagli senza cambiare pagina.', 'gest-web-rent' ), 'max_width' => '1380' ), $atts, 'gwr_catalog' );
		$filters = self::filters_from_request( $_GET );
		$error = '';
		$vehicles = self::filtered_vehicles( $filters, $error );
		$category_filters = $filters;
		$category_filters['category'] = '';
		$category_error = '';
		$category_vehicles = self::filtered_vehicles( $category_filters, $category_error );
		$advanced_id = wp_unique_id( 'gwr-advanced-filters-' );
		ob_start();
		echo '<section class="gwr-rent-catalog gwr-block" data-gwr-catalog style="' . esc_attr( self::style_vars( $atts['max_width'] ) ) . '">';
		echo self::filter_form( $filters, $error, $advanced_id );
		$count_label = sprintf( _n( '%d veicolo disponibile', '%d veicoli disponibili', count( $vehicles ), 'gest-web-rent' ), count( $vehicles ) );
		echo '<section class="gwr-search-summary" data-gwr-search-summary aria-live="polite" hidden><div class="gwr-search-summary__route"><span data-gwr-summary-location></span><strong data-gwr-summary-period></strong><span data-gwr-summary-duration></span></div><div class="gwr-search-summary__actions"><strong data-gwr-count>' . esc_html( $count_label ) . '</strong><button type="button" class="gwr-button-secondary" data-gwr-edit-search>' . esc_html__( 'Modifica ricerca', 'gest-web-rent' ) . '</button></div></section>';
		echo '<div class="gwr-results-layout">';
		echo self::filters_sidebar( $filters, $advanced_id );
		echo '<main class="gwr-results-main" data-gwr-results-main>';
		echo '<div data-gwr-category-slot>' . self::category_shortcuts( $category_vehicles, $filters['category'] ) . '</div>';
		echo self::results_toolbar( count( $vehicles ), $filters, $advanced_id );
		echo '<div class="gwr-active-filters" data-gwr-active-filters hidden></div>';
		echo '<div class="gwr-catalog-error" data-gwr-error role="alert" aria-live="assertive" ' . ( $error ? '' : 'hidden' ) . '>' . esc_html( $error ) . '</div>';
		echo '<div class="gwr-results-shell" aria-live="polite"><div class="gwr-catalog-grid is-list-view" data-gwr-results>' . self::cards_html( $vehicles, $filters ) . '</div></div>';
		echo '</main></div>';
		echo self::modal_markup();
		echo '</section>';
		return ob_get_clean();
	}

	/**
	 * Payment status block for Stripe success/cancel redirects.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function payment_status_shortcode( $atts = array() ) {
		self::enqueue_public_assets();
		$query = is_array( $_GET ) ? wp_unslash( $_GET ) : array();
		$atts = shortcode_atts( array( 'booking_code' => '', 'booking_token' => '', 'state' => '' ), $atts, 'gwr_payment_status' );
		$state = sanitize_key( $query['gwr_payment'] ?? $atts['state'] );
		$code  = sanitize_text_field( $query['booking_code'] ?? $atts['booking_code'] );
		$token = sanitize_text_field( $query['gwr_token'] ?? ( $query['booking_token'] ?? $atts['booking_token'] ) );
		$title = 'cancel' === $state ? __( 'Pagamento non completato', 'gest-web-rent' ) : __( 'Verifica pagamento', 'gest-web-rent' );
		$message = 'cancel' === $state
			? __( 'Il checkout e stato interrotto. Verifica lo stato o riprova il pagamento dalla stessa prenotazione.', 'gest-web-rent' )
			: __( 'Stiamo leggendo lo stato reale della prenotazione dal server.', 'gest-web-rent' );

		ob_start();
		echo '<section class="gwr-payment-status gwr-block" data-gwr-payment-status data-booking-code="' . esc_attr( $code ) . '" data-booking-token="' . esc_attr( $token ) . '" data-payment-state="' . esc_attr( $state ) . '">';
		echo '<div class="gwr-payment-status__panel">';
		echo '<span class="gwr-payment-status__eyebrow">' . esc_html__( 'Gest Web Rent', 'gest-web-rent' ) . '</span>';
		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<p data-gwr-payment-message>' . esc_html( $message ) . '</p>';
		echo '<div class="gwr-payment-status__summary" data-gwr-payment-summary aria-live="polite"></div>';
		echo '<div class="gwr-payment-status__actions"><button type="button" class="gwr-button" data-gwr-payment-refresh>' . esc_html__( 'Verifica stato', 'gest-web-rent' ) . '</button><button type="button" class="gwr-button-secondary" data-gwr-payment-retry hidden>' . esc_html__( 'Riprova pagamento', 'gest-web-rent' ) . '</button></div>';
		echo '</div></section>';
		return ob_get_clean();
	}

	/**
	 * Minimal booking portal with secure document links.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function booking_portal_shortcode( $atts = array() ) {
		self::enqueue_public_assets();
		$query = is_array( $_GET ) ? wp_unslash( $_GET ) : array();
		$atts = shortcode_atts( array( 'booking_code' => '', 'booking_token' => '' ), $atts, 'gwr_booking_portal' );
		$code = sanitize_text_field( $query['booking_code'] ?? $atts['booking_code'] );
		$token = sanitize_text_field( $query['gwr_token'] ?? ( $query['booking_token'] ?? $atts['booking_token'] ) );
		$booking = $code ? self::booking_by_code( $code ) : null;

		ob_start();
		echo '<section class="gwr-booking-portal gwr-block">';
		echo '<div class="gwr-booking-portal__panel">';
		echo '<span class="gwr-payment-status__eyebrow">' . esc_html__( 'Area prenotazione', 'gest-web-rent' ) . '</span>';
		echo '<h2>' . esc_html__( 'Documenti e stato prenotazione', 'gest-web-rent' ) . '</h2>';
		if ( ! $booking || ! GWR_Bookings::verify_public_token( $booking, $token ) ) {
			if ( $code || $token ) {
				echo '<div class="gwr-booking-portal__alert">' . esc_html__( 'Codice o token non valido.', 'gest-web-rent' ) . '</div>';
			}
			echo '<form method="get" class="gwr-booking-portal__form"><label><span>' . esc_html__( 'Codice prenotazione', 'gest-web-rent' ) . '</span><input type="text" name="booking_code" value="' . esc_attr( $code ) . '" required /></label><label><span>' . esc_html__( 'Token prenotazione', 'gest-web-rent' ) . '</span><input type="text" name="gwr_token" value="" required /></label><button class="gwr-button">' . esc_html__( 'Apri area', 'gest-web-rent' ) . '</button></form>';
			echo '</div></section>';
			return ob_get_clean();
		}

		echo '<div class="gwr-booking-portal__summary">';
		echo '<div><span>' . esc_html__( 'Prenotazione', 'gest-web-rent' ) . '</span><strong>' . esc_html( $booking['booking_code'] ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</span><strong>' . esc_html( GWR_Bookings::statuses()[ $booking['status'] ] ?? $booking['status'] ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Veicolo', 'gest-web-rent' ) . '</span><strong>' . esc_html( $booking['vehicle_title'] ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Periodo', 'gest-web-rent' ) . '</span><strong>' . esc_html( GWR_Bookings::format_datetime( $booking['pickup_datetime'] ) . ' - ' . GWR_Bookings::format_datetime( $booking['return_datetime'] ) ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Totale', 'gest-web-rent' ) . '</span><strong>' . esc_html( GWR_Bookings::format_money( $booking['total_amount'], $booking['currency'] ) ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Pagamento', 'gest-web-rent' ) . '</span><strong>' . esc_html( $booking['payment_status'] ) . '</strong></div>';
		echo '</div>';

		self::portal_documents( $booking, $token );
		self::portal_attachments( $booking, $token );
		echo '</div></section>';
		return ob_get_clean();
	}

	/**
	 * Show the payment status block automatically on default Stripe redirect URLs.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function maybe_prepend_payment_status( $content ) {
		static $rendered = false;
		if ( $rendered || is_admin() || wp_doing_ajax() || ! in_the_loop() || ! is_main_query() || empty( $_GET['gwr_payment'] ) ) {
			return $content;
		}
		$rendered = true;
		return self::payment_status_shortcode() . $content;
	}

	public static function ajax_filter_catalog() {
		check_ajax_referer( 'gwr_catalog_nonce', 'nonce' );
		$filters = self::filters_from_request( $_POST );
		$error = '';
		$vehicles = self::filtered_vehicles( $filters, $error );
		$category_filters = $filters;
		$category_filters['category'] = '';
		$category_error = '';
		$category_vehicles = self::filtered_vehicles( $category_filters, $category_error );
		wp_send_json_success(
			array(
				'html'       => self::cards_html( $vehicles, $filters ),
				'categories' => self::category_shortcuts( $category_vehicles, $filters['category'] ),
				'count'      => count( $vehicles ),
				'error'      => $error,
				'summary'    => self::date_summary( $filters ),
			)
		);
	}

	private static function enqueue_public_assets() {
		wp_enqueue_style( 'gwr-public' );
		wp_enqueue_script( 'gwr-public' );
		$settings = GWR_Admin::get_settings();
		$whatsapp_number = preg_replace( '/\D+/', '', (string) $settings['whatsapp_number'] );
		$contact_email = sanitize_email( (string) $settings['contact_email'] );
		if ( ! is_email( $contact_email ) ) {
			$contact_email = '';
		}

		wp_localize_script(
			'gwr-public',
			'gwrCatalog',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gwr_catalog_nonce' ),
				'i18n'    => array(
					'available'          => __( 'Disponibile', 'gest-web-rent' ),
					'noVehicles'         => __( 'Nessun veicolo disponibile con questi filtri.', 'gest-web-rent' ),
					'countOne'           => __( '1 veicolo disponibile', 'gest-web-rent' ),
					'countMany'          => __( '%d veicoli disponibili', 'gest-web-rent' ),
					'dateError'          => __( 'La data fine %2$s non puo precedere la data inizio %1$s.', 'gest-web-rent' ),
					'invalidDate'        => __( 'Inserisci una data valida nel formato GG-MM-AAAA.', 'gest-web-rent' ),
					'formError'          => __( 'Controlla i campi evidenziati.', 'gest-web-rent' ),
					'pickupDateRequired' => __( 'Inserisci la data di ritiro.', 'gest-web-rent' ),
					'returnDateRequired' => __( 'Inserisci la data di riconsegna.', 'gest-web-rent' ),
					'pastDate'           => __( 'La data di ritiro non puo essere precedente a oggi.', 'gest-web-rent' ),
					'returnAfterPickup'  => __( 'La riconsegna deve essere successiva al ritiro.', 'gest-web-rent' ),
					'timeRequired'       => __( 'Inserisci un orario valido nel formato HH:MM.', 'gest-web-rent' ),
					'pickupLocation'     => __( 'Seleziona la localita di ritiro.', 'gest-web-rent' ),
					'returnLocation'     => __( 'Seleziona la localita di riconsegna.', 'gest-web-rent' ),
					'datesGeneric'       => __( 'Date da definire', 'gest-web-rent' ),
					'searchLabel'        => __( 'Cerca veicoli', 'gest-web-rent' ),
					'searchingLabel'     => __( 'Ricerca in corso...', 'gest-web-rent' ),
					'contactsMissing'    => __( 'Contatti non configurati.', 'gest-web-rent' ),
					'close'              => __( 'Chiudi', 'gest-web-rent' ),
					'modalError'         => __( 'Impossibile aprire i dettagli del veicolo.', 'gest-web-rent' ),
					'technicalError'     => __( 'Non e stato possibile completare la ricerca. Riprova tra qualche istante.', 'gest-web-rent' ),
				),
				'contact' => array(
					'dealerName'       => sanitize_text_field( (string) $settings['dealer_name'] ),
					'whatsappNumber'   => $whatsapp_number,
					'contactEmail'     => $contact_email,
					'whatsappTemplate' => sanitize_textarea_field( (string) $settings['whatsapp_message'] ),
					'emailSubject'     => sanitize_text_field( (string) $settings['email_subject'] ),
					'emailBody'        => sanitize_textarea_field( (string) $settings['email_body'] ),
					'privacyNote'      => sanitize_textarea_field( (string) $settings['privacy_note'] ),
					'siteUrl'          => home_url( '/' ),
				),
				'booking' => array(
					'privacyUrl' => esc_url_raw( (string) ( $settings['privacy_url'] ?? '' ) ),
					'paymentMethods' => self::payment_methods_payload(),
				),
			)
		);
	}

	private static function payment_methods_payload() {
		$settings = GWR_Pricing_Service::get_settings();
		$methods = array_filter( array_map( 'sanitize_key', explode( ',', (string) $settings['active_payment_methods'] ) ) );
		if ( ! empty( $settings['stripe_enabled'] ) && ! in_array( 'stripe', $methods, true ) ) {
			$methods[] = 'stripe';
		}
		if ( ! empty( $settings['bank_transfer_enabled'] ) && ! in_array( 'bank_transfer', $methods, true ) ) {
			$methods[] = 'bank_transfer';
		}
		$labels = array(
			'stripe'        => __( 'Carta o wallet con Stripe', 'gest-web-rent' ),
			'bank_transfer' => __( 'Bonifico bancario', 'gest-web-rent' ),
			'pickup'        => __( 'Pagamento al ritiro', 'gest-web-rent' ),
			'request'       => __( 'Solo richiesta', 'gest-web-rent' ),
		);
		$output = array();
		foreach ( $methods ?: array( 'request' ) as $method ) {
			$output[] = array( 'id' => $method, 'label' => $labels[ $method ] ?? $method, 'default' => $method === ( $settings['default_payment_method'] ?? 'request' ) );
		}
		return $output;
	}

	private static function booking_by_code( $code ) {
		global $wpdb;
		$booking_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GWR_Bookings::table() . ' WHERE booking_code=%s', sanitize_text_field( $code ) ) );
		return $booking_id ? GWR_Bookings::get( $booking_id ) : null;
	}

	/**
	 * Portal documents list.
	 *
	 * @param array  $booking Booking.
	 * @param string $token Token.
	 * @return void
	 */
	private static function portal_documents( $booking, $token ) {
		if ( ! class_exists( 'GWR_Document_Service' ) ) {
			return;
		}
		$types = GWR_Document_Service::document_types();
		echo '<section class="gwr-booking-portal__section"><h3>' . esc_html__( 'Documenti disponibili', 'gest-web-rent' ) . '</h3><div class="gwr-booking-portal__list">';
		$has_documents = false;
		foreach ( $types as $type => $config ) {
			if ( empty( $config['public'] ) ) {
				continue;
			}
			$document = GWR_Document_Service::latest_for_booking( $booking['id'], $type );
			if ( ! $document ) {
				continue;
			}
			$has_documents = true;
			$args = array( 'booking_code' => $booking['booking_code'], 'gwr_token' => $token );
			echo '<article><div><strong>' . esc_html( $config['label'] ) . '</strong><span>' . esc_html( $document['document_number'] . ' v' . $document['version'] ) . '</span></div><a class="gwr-button-secondary" href="' . esc_url( GWR_Document_Service::document_url( $document, 'download', $args ) ) . '">' . esc_html__( 'Scarica', 'gest-web-rent' ) . '</a></article>';
		}
		if ( ! $has_documents ) {
			echo '<p>' . esc_html__( 'Nessun documento disponibile al momento.', 'gest-web-rent' ) . '</p>';
		}
		echo '</div></section>';
	}

	/**
	 * Portal customer-visible attachments.
	 *
	 * @param array  $booking Booking.
	 * @param string $token Token.
	 * @return void
	 */
	private static function portal_attachments( $booking, $token ) {
		if ( ! class_exists( 'GWR_Attachment_Service' ) ) {
			return;
		}
		$attachments = GWR_Attachment_Service::attachments_for_booking( $booking['id'], false );
		if ( empty( $attachments ) ) {
			return;
		}
		echo '<section class="gwr-booking-portal__section"><h3>' . esc_html__( 'Allegati', 'gest-web-rent' ) . '</h3><div class="gwr-booking-portal__list">';
		foreach ( $attachments as $attachment ) {
			$args = array( 'booking_code' => $booking['booking_code'], 'gwr_token' => $token );
			echo '<article><div><strong>' . esc_html( $attachment['description'] ?: __( 'Allegato', 'gest-web-rent' ) ) . '</strong><span>' . esc_html( $attachment['mime_type'] ) . '</span></div><a class="gwr-button-secondary" href="' . esc_url( GWR_Attachment_Service::download_url( $attachment, $args ) ) . '">' . esc_html__( 'Scarica', 'gest-web-rent' ) . '</a></article>';
		}
		echo '</div></section>';
	}

	private static function filters_from_request( $source ) {
		$source = is_array( $source ) ? wp_unslash( $source ) : array();
		$start_date = $source['start_date'] ?? ( $source['pickup_date'] ?? ( $source['start_date_display'] ?? ( $source['gwr_date_from'] ?? '' ) ) );
		$end_date = $source['end_date'] ?? ( $source['return_date'] ?? ( $source['end_date_display'] ?? ( $source['gwr_date_to'] ?? '' ) ) );
		$pickup_location = isset( $source['pickup_location'] ) ? sanitize_text_field( $source['pickup_location'] ) : ( isset( $source['location'] ) ? sanitize_text_field( $source['location'] ) : '' );
		$different_return = ! empty( $source['different_return'] );
		$return_location = $different_return && isset( $source['return_location'] ) ? sanitize_text_field( $source['return_location'] ) : '';

		return array(
			'start_date'       => self::normalize_request_date( $start_date ),
			'end_date'         => self::normalize_request_date( $end_date ),
			'pickup_time'      => self::normalize_time( $source['pickup_time'] ?? '09:00' ),
			'return_time'      => self::normalize_time( $source['return_time'] ?? '09:00' ),
			'pickup_location'  => $pickup_location,
			'return_location'  => $return_location,
			'different_return' => $different_return,
			'location'         => $pickup_location,
			'search'           => isset( $source['search'] ) ? sanitize_text_field( $source['search'] ) : '',
			'category'         => isset( $source['category'] ) ? sanitize_text_field( $source['category'] ) : '',
			'brand'            => isset( $source['brand'] ) ? sanitize_text_field( $source['brand'] ) : '',
			'fuel'             => isset( $source['fuel'] ) ? sanitize_text_field( $source['fuel'] ) : '',
			'transmission'     => isset( $source['transmission'] ) ? sanitize_text_field( $source['transmission'] ) : '',
			'max_price'        => isset( $source['max_price'] ) ? (float) str_replace( ',', '.', sanitize_text_field( $source['max_price'] ) ) : 0,
			'seats'            => isset( $source['seats'] ) ? absint( $source['seats'] ) : 0,
		);
	}

	private static function normalize_time( $value ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
	}

	private static function filtered_vehicles( $filters, &$error = '' ) {
		$query_filters = $filters;
		$range = GWR_CPT::normalize_date_range( $filters['start_date'], $filters['end_date'] );
		if ( is_wp_error( $range ) ) { $error = $range->get_error_message(); $query_filters['start_date'] = ''; $query_filters['end_date'] = ''; } else { $query_filters['start_date'] = $range['start_date']; $query_filters['end_date'] = $range['end_date']; }
		return GWR_CPT::get_vehicles( array( 'frontend' => true, 'search' => $query_filters['search'], 'category' => $query_filters['category'], 'brand' => $query_filters['brand'], 'fuel' => $query_filters['fuel'], 'transmission' => $query_filters['transmission'], 'location' => $query_filters['location'], 'max_price' => $query_filters['max_price'], 'seats' => $query_filters['seats'], 'start_date' => $query_filters['start_date'], 'end_date' => $query_filters['end_date'] ) );
	}

	private static function normalize_request_date( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$iso_date = self::date_to_iso( $value );
		return $iso_date ?: $value;
	}

	private static function date_to_iso( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
			return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ? $value : '';
		}

		if ( preg_match( '/^(\d{2})-(\d{2})-(\d{4})$/', $value, $matches ) && checkdate( (int) $matches[2], (int) $matches[1], (int) $matches[3] ) ) {
			return sprintf( '%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1] );
		}

		return '';
	}

	private static function date_to_display( $value ) {
		$iso_date = self::date_to_iso( $value );
		if ( '' === $iso_date ) {
			return '';
		}

		$parts = explode( '-', $iso_date );
		return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
	}

	private static function date_summary( $filters ) {
		$start_date = self::date_to_display( $filters['start_date'] ?? '' );
		$end_date = self::date_to_display( $filters['end_date'] ?? '' );

		if ( $start_date && $end_date ) {
			return sprintf( __( 'Periodo selezionato: dal %1$s al %2$s.', 'gest-web-rent' ), $start_date, $end_date );
		}

		if ( $start_date || $end_date ) {
			return sprintf( __( 'Data selezionata: %s.', 'gest-web-rent' ), $start_date ?: $end_date );
		}

		return __( 'Seleziona le date e premi Cerca veicoli.', 'gest-web-rent' );
	}

	private static function filter_form( $filters, $error, $advanced_id ) {
		$return_location_id = wp_unique_id( 'gwr-return-location-' );
		$form_error_id = wp_unique_id( 'gwr-search-error-' );
		$locations = GWR_CPT::filter_values( 'location' );
		if ( 1 === count( $locations ) && empty( $filters['pickup_location'] ) ) {
			$filters['pickup_location'] = $locations[0];
		}

		$html = '<section class="gwr-search-panel" data-gwr-search-panel>';
		$html .= '<div class="gwr-search-panel__heading"><span class="gwr-filter-eyebrow">' . esc_html__( 'Noleggio veicoli', 'gest-web-rent' ) . '</span><h2>' . esc_html__( 'Noleggia il veicolo giusto', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Seleziona luogo, date e orari per verificare la disponibilita.', 'gest-web-rent' ) . '</p></div>';
		$html .= '<form class="gwr-search-form gwr-filter-panel" method="get" data-gwr-filter-form aria-describedby="' . esc_attr( $form_error_id ) . '" novalidate>';
		$html .= '<div id="' . esc_attr( $form_error_id ) . '" class="gwr-form-error" data-gwr-form-error role="alert" aria-live="assertive" hidden></div>';
		$html .= '<div class="gwr-search-form__main">';
		$html .= '<div class="gwr-search-form__location">';
		$html .= self::location_field( 'pickup_location', __( 'Localita di ritiro', 'gest-web-rent' ), $filters['pickup_location'], $locations, true, 'data-gwr-pickup-location' );
		$html .= '<label class="gwr-location-toggle"><input type="checkbox" name="different_return" value="1" data-gwr-different-return aria-expanded="' . ( $filters['different_return'] ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $return_location_id ) . '" ' . checked( $filters['different_return'], true, false ) . ' /><span>' . esc_html__( 'Riconsegna in una localita diversa', 'gest-web-rent' ) . '</span></label>';
		$html .= '<div id="' . esc_attr( $return_location_id ) . '" class="gwr-return-location" data-gwr-return-location ' . ( $filters['different_return'] ? '' : 'hidden' ) . '>';
		$html .= self::location_field( 'return_location', __( 'Localita di riconsegna', 'gest-web-rent' ), $filters['return_location'], $locations, false, 'data-gwr-return-location-field' );
		$html .= '</div></div>';
		$html .= '<div class="gwr-search-form__dates">';
		$html .= '<div class="gwr-date-time-group">' . self::date_field( 'start_date', __( 'Data di ritiro', 'gest-web-rent' ), $filters['start_date'], 'pickup' ) . self::time_field( 'pickup_time', __( 'Ora di ritiro', 'gest-web-rent' ), $filters['pickup_time'], 'pickup' ) . '</div>';
		$html .= '<div class="gwr-date-time-group">' . self::date_field( 'end_date', __( 'Data di riconsegna', 'gest-web-rent' ), $filters['end_date'], 'return' ) . self::time_field( 'return_time', __( 'Ora di riconsegna', 'gest-web-rent' ), $filters['return_time'], 'return' ) . '</div>';
		$html .= '</div>';
		$html .= '<button type="submit" class="gwr-search-button" data-gwr-search-submit><span data-gwr-search-label>' . esc_html__( 'Cerca veicoli', 'gest-web-rent' ) . '</span><span class="gwr-search-button__spinner" aria-hidden="true"></span></button>';
		$html .= '</div>';
		$html .= '<button type="button" class="gwr-filter-toggle" data-gwr-filter-toggle aria-expanded="false" aria-controls="' . esc_attr( $advanced_id ) . '"><span>' . esc_html__( 'Altri filtri', 'gest-web-rent' ) . ' <span class="gwr-filter-count" data-gwr-filter-count hidden></span></span><span class="gwr-filter-toggle__icon" aria-hidden="true"></span></button>';
		$html .= '</form></section>';
		return $html;
	}

	private static function filters_sidebar( $filters, $advanced_id ) {
		$html = '<aside id="' . esc_attr( $advanced_id ) . '" class="gwr-results-sidebar gwr-filter-advanced" data-gwr-filter-advanced hidden>';
		$html .= '<form class="gwr-results-sidebar__form gwr-filter-panel" data-gwr-secondary-form>';
		$html .= '<div class="gwr-filter-advanced__header"><div><span class="gwr-filter-eyebrow">' . esc_html__( 'Catalogo', 'gest-web-rent' ) . '</span><strong>' . esc_html__( 'Filtra i risultati', 'gest-web-rent' ) . '</strong></div><button type="button" class="gwr-filter-advanced__close" data-gwr-close-filters aria-label="' . esc_attr__( 'Chiudi filtri', 'gest-web-rent' ) . '">&times;</button></div>';
		$html .= self::filter_section( __( 'Ricerca', 'gest-web-rent' ), self::text_field( 'search', __( 'Veicolo', 'gest-web-rent' ), $filters['search'], __( 'Marca, modello, titolo...', 'gest-web-rent' ) ) );
		$html .= self::filter_section( __( 'Prezzo', 'gest-web-rent' ), self::number_field( 'max_price', __( 'Massimo al giorno', 'gest-web-rent' ), $filters['max_price'], '100' ) );
		$html .= self::filter_section( __( 'Categoria', 'gest-web-rent' ), self::select_field( 'category', __( 'Tipo veicolo', 'gest-web-rent' ), $filters['category'], GWR_CPT::filter_values( 'category' ) ) );
		$html .= self::filter_section( __( 'Marca', 'gest-web-rent' ), self::select_field( 'brand', __( 'Costruttore', 'gest-web-rent' ), $filters['brand'], GWR_CPT::filter_values( 'brand' ) ) );
		$html .= self::filter_section( __( 'Cambio', 'gest-web-rent' ), self::select_field( 'transmission', __( 'Trasmissione', 'gest-web-rent' ), $filters['transmission'], GWR_CPT::filter_values( 'transmission' ) ) );
		$html .= self::filter_section( __( 'Alimentazione', 'gest-web-rent' ), self::select_field( 'fuel', __( 'Carburante', 'gest-web-rent' ), $filters['fuel'], GWR_CPT::filter_values( 'fuel' ) ) );
		$html .= self::filter_section( __( 'Posti', 'gest-web-rent' ), self::number_field( 'seats', __( 'Posti minimi', 'gest-web-rent' ), $filters['seats'], '5' ) );
		$html .= '<div class="gwr-filter-actions"><button type="button" class="gwr-button-secondary" data-gwr-reset-filters>' . esc_html__( 'Azzera filtri', 'gest-web-rent' ) . '</button><button type="submit" class="gwr-button" data-gwr-apply-filters>' . esc_html__( 'Applica filtri', 'gest-web-rent' ) . '</button></div>';
		return $html . '</form></aside>';
	}

	private static function filter_section( $title, $content ) {
		$section_id = wp_unique_id( 'gwr-filter-section-' );
		$html = '<section class="gwr-sidebar-section">';
		$html .= '<button type="button" class="gwr-sidebar-section__toggle" data-gwr-sidebar-section-toggle aria-expanded="true" aria-controls="' . esc_attr( $section_id ) . '"><span>' . esc_html( $title ) . '</span><span aria-hidden="true"></span></button>';
		$html .= '<div id="' . esc_attr( $section_id ) . '" class="gwr-sidebar-section__content" data-gwr-sidebar-section-content>' . $content . '</div>';
		return $html . '</section>';
	}

	private static function category_shortcuts( $vehicles, $selected_category ) {
		$categories = array();
		foreach ( $vehicles as $vehicle ) {
			$category = trim( (string) $vehicle['category'] );
			if ( '' === $category ) { continue; }
			if ( ! isset( $categories[ $category ] ) ) { $categories[ $category ] = array( 'count' => 0, 'min_price' => 0 ); }
			$categories[ $category ]['count']++;
			$price = (float) $vehicle['daily_price'];
			if ( $price > 0 && ( 0 === $categories[ $category ]['min_price'] || $price < $categories[ $category ]['min_price'] ) ) { $categories[ $category ]['min_price'] = $price; }
		}
		if ( empty( $categories ) ) { return ''; }
		ksort( $categories, SORT_NATURAL | SORT_FLAG_CASE );
		$html = '<section class="gwr-category-shortcuts" data-gwr-categories aria-label="' . esc_attr__( 'Categorie veicoli disponibili', 'gest-web-rent' ) . '"><div class="gwr-category-shortcuts__track">';
		$html .= self::category_button( '', __( 'Tutte', 'gest-web-rent' ), count( $vehicles ), self::minimum_daily_price( $vehicles ), '' === $selected_category );
		foreach ( $categories as $category => $data ) { $html .= self::category_button( $category, $category, $data['count'], $data['min_price'], $category === $selected_category ); }
		return $html . '</div></section>';
	}

	private static function category_button( $value, $label, $count, $min_price, $selected ) {
		$html = '<button type="button" class="gwr-category-shortcut' . ( $selected ? ' is-active' : '' ) . '" data-gwr-category="' . esc_attr( $value ) . '" aria-pressed="' . ( $selected ? 'true' : 'false' ) . '">';
		$html .= '<strong>' . esc_html( $label ) . '</strong><span>' . esc_html( sprintf( _n( '%d veicolo', '%d veicoli', $count, 'gest-web-rent' ), $count ) ) . '</span>';
		if ( $min_price > 0 ) { $html .= '<small>' . esc_html( sprintf( __( 'Da %s / giorno', 'gest-web-rent' ), self::format_euro( $min_price ) ) ) . '</small>'; }
		return $html . '</button>';
	}

	private static function minimum_daily_price( $vehicles ) {
		$prices = array_filter( array_map( static function ( $vehicle ) { return (float) $vehicle['daily_price']; }, $vehicles ) );
		return $prices ? min( $prices ) : 0;
	}

	private static function results_toolbar( $count, $filters, $advanced_id ) {
		$count_label = sprintf( _n( '%d veicolo disponibile', '%d veicoli disponibili', $count, 'gest-web-rent' ), $count );
		$html = '<header class="gwr-results-toolbar" data-gwr-results-toolbar tabindex="-1"><div class="gwr-results-toolbar__summary"><strong data-gwr-count aria-live="polite">' . esc_html( $count_label ) . '</strong><span data-gwr-toolbar-period>' . esc_html( self::date_summary( $filters ) ) . '</span></div>';
		$html .= '<div class="gwr-results-toolbar__controls"><button type="button" class="gwr-toolbar-filter" data-gwr-filter-toggle aria-expanded="false" aria-controls="' . esc_attr( $advanced_id ) . '">' . esc_html__( 'Filtri', 'gest-web-rent' ) . ' <span data-gwr-filter-count hidden></span></button>';
		$html .= '<label class="gwr-sort-control"><span>' . esc_html__( 'Ordina', 'gest-web-rent' ) . '</span><select data-gwr-sort><option value="recommended">' . esc_html__( 'Consigliato', 'gest-web-rent' ) . '</option><option value="price_asc">' . esc_html__( 'Prezzo giornaliero piu basso', 'gest-web-rent' ) . '</option><option value="price_desc">' . esc_html__( 'Prezzo giornaliero piu alto', 'gest-web-rent' ) . '</option><option value="brand">' . esc_html__( 'Marca A-Z', 'gest-web-rent' ) . '</option><option value="category">' . esc_html__( 'Categoria', 'gest-web-rent' ) . '</option></select></label>';
		$html .= '<div class="gwr-view-switch" aria-label="' . esc_attr__( 'Vista catalogo', 'gest-web-rent' ) . '"><button type="button" data-gwr-view="list" aria-pressed="true">' . esc_html__( 'Lista', 'gest-web-rent' ) . '</button><button type="button" data-gwr-view="grid" aria-pressed="false">' . esc_html__( 'Griglia', 'gest-web-rent' ) . '</button></div></div></header>';
		return $html;
	}

	private static function cards_html( $vehicles, $filters ) {
		if ( empty( $vehicles ) ) {
			return '<div class="gwr-empty-state"><span class="gwr-empty-state__icon" aria-hidden="true"></span><h3>' . esc_html__( 'Nessun veicolo disponibile', 'gest-web-rent' ) . '</h3><p>' . esc_html__( 'Non abbiamo trovato veicoli compatibili con il periodo e i filtri selezionati. Prova a modificare le date o rimuovere alcuni filtri.', 'gest-web-rent' ) . '</p><div class="gwr-empty-state__actions"><button type="button" class="gwr-button-secondary" data-gwr-edit-search>' . esc_html__( 'Modifica date', 'gest-web-rent' ) . '</button><button type="button" class="gwr-button" data-gwr-reset-and-search>' . esc_html__( 'Azzera filtri', 'gest-web-rent' ) . '</button></div></div>';
		}
		$html = '';
		foreach ( $vehicles as $index => $vehicle ) { $html .= self::card_markup( $vehicle, $filters, $index ); }
		return $html;
	}

	private static function card_markup( $vehicle, $filters, $index = 0 ) {
		$payload = GWR_CPT::public_payload( $vehicle, false );
		$cover = $payload['images'] ? $payload['images'][0] : array();
		$primary_title = trim( $vehicle['brand'] . ' ' . $vehicle['model'] );
		$primary_title = $primary_title ?: $vehicle['title'];
		$daily_price = self::format_euro( $vehicle['daily_price'] );
		$payload_json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$payload_encoded = base64_encode( $payload_json );
		$availability_label = ! empty( $filters['start_date'] ) && ! empty( $filters['end_date'] ) ? __( 'Disponibile', 'gest-web-rent' ) : __( 'Verifica disponibilita', 'gest-web-rent' );
		$html = '<article class="gwr-vehicle-card" data-gwr-card data-gwr-vehicle-b64="' . esc_attr( $payload_encoded ) . '" data-gwr-original-index="' . esc_attr( $index ) . '" data-gwr-price="' . esc_attr( (float) $vehicle['daily_price'] ) . '" data-gwr-brand="' . esc_attr( strtolower( (string) $vehicle['brand'] ) ) . '" data-gwr-category-name="' . esc_attr( strtolower( (string) $vehicle['category'] ) ) . '" data-gwr-featured="' . ( ! empty( $vehicle['featured'] ) ? '1' : '0' ) . '">';
		$html .= '<div class="gwr-vehicle-card__media"><button type="button" class="gwr-vehicle-card__media-button" data-gwr-open-modal aria-label="' . esc_attr( sprintf( __( 'Vedi le foto e i dettagli di %s', 'gest-web-rent' ), $primary_title ) ) . '">';
		$html .= self::card_image( $cover, $primary_title );
		$html .= '</button><span class="gwr-vehicle-card__status">' . esc_html( $availability_label ) . '</span>';
		if ( ! empty( $vehicle['featured'] ) ) { $html .= '<span class="gwr-vehicle-card__recommended">' . esc_html__( 'Consigliato', 'gest-web-rent' ) . '</span>'; }
		$html .= '</div>';
		$html .= '<div class="gwr-vehicle-card__content">';
		if ( $vehicle['category'] ) { $html .= '<span class="gwr-vehicle-card__category">' . esc_html( $vehicle['category'] ) . '</span>'; }
		$html .= '<h3 class="gwr-vehicle-card__title"><button type="button" data-gwr-open-modal>' . esc_html( $primary_title ) . '</button></h3>';
		if ( $vehicle['version'] ) { $html .= '<p class="gwr-vehicle-card__version">' . esc_html( $vehicle['version'] ) . '</p>'; }
		$html .= self::card_specs( $vehicle );
		$html .= self::card_inclusions( $vehicle );
		if ( $vehicle['location'] ) { $html .= '<div class="gwr-vehicle-card__location"><strong>' . esc_html__( 'Ritiro presso sede', 'gest-web-rent' ) . '</strong><span>' . esc_html( $vehicle['location'] ) . '</span>' . ( ! empty( $vehicle['home_delivery'] ) ? '<small>' . esc_html__( 'Consegna a domicilio disponibile', 'gest-web-rent' ) . '</small>' : '' ) . '</div>'; }
		$html .= '</div>';
		$html .= '<div class="gwr-vehicle-card__pricing"><span class="gwr-vehicle-card__pricing-label">' . esc_html__( 'Tariffa giornaliera', 'gest-web-rent' ) . '</span><div class="gwr-vehicle-card__daily-price"><strong>' . esc_html( $daily_price ?: __( 'Su richiesta', 'gest-web-rent' ) ) . '</strong>' . ( $daily_price ? '<span>' . esc_html__( '/ giorno', 'gest-web-rent' ) . '</span>' : '' ) . '</div><span class="gwr-vehicle-card__duration" data-gwr-card-duration>' . esc_html__( 'Seleziona il periodo', 'gest-web-rent' ) . '</span><span class="gwr-vehicle-card__total-note">' . esc_html__( 'Totale su richiesta', 'gest-web-rent' ) . '</span><button type="button" class="gwr-button gwr-vehicle-card__cta" data-gwr-open-modal>' . esc_html__( 'Vedi dettagli', 'gest-web-rent' ) . '</button></div>';
		$html .= '</article>';
		return $html;
	}

	private static function card_image( $cover, $title ) {
		if ( empty( $cover['url'] ) ) { return '<span class="gwr-vehicle-card__placeholder"><span aria-hidden="true"></span>' . esc_html__( 'Immagine non disponibile', 'gest-web-rent' ) . '</span>'; }
		if ( ! empty( $cover['id'] ) ) {
			$image = wp_get_attachment_image( absint( $cover['id'] ), 'medium_large', false, array( 'alt' => $title, 'loading' => 'lazy', 'decoding' => 'async' ) );
			if ( $image ) { return $image; }
		}
		return '<img src="' . esc_url( $cover['url'] ) . '" alt="' . esc_attr( $title ) . '" width="640" height="480" loading="lazy" decoding="async" />';
	}

	private static function card_specs( $vehicle ) {
		$feature_keys = GWR_CPT::vehicle_features( $vehicle );
		$items = array();
		if ( absint( $vehicle['seats'] ) ) { $items[] = array( 'seats', sprintf( __( '%d posti', 'gest-web-rent' ), absint( $vehicle['seats'] ) ) ); }
		if ( absint( $vehicle['doors'] ) ) { $items[] = array( 'doors', sprintf( __( '%d porte', 'gest-web-rent' ), absint( $vehicle['doors'] ) ) ); }
		if ( $vehicle['transmission'] ) { $items[] = array( 'transmission', $vehicle['transmission'] ); }
		if ( $vehicle['fuel'] ) { $items[] = array( 'fuel', $vehicle['fuel'] ); }
		if ( in_array( 'air_conditioning', $feature_keys, true ) ) { $items[] = array( 'air', __( 'Climatizzatore', 'gest-web-rent' ) ); }
		if ( empty( $items ) ) { return ''; }
		$html = '<ul class="gwr-vehicle-card__specs" aria-label="' . esc_attr__( 'Caratteristiche principali', 'gest-web-rent' ) . '">';
		foreach ( array_slice( $items, 0, 6 ) as $item ) { $html .= '<li data-gwr-spec="' . esc_attr( $item[0] ) . '"><span aria-hidden="true">' . self::card_spec_icon( $item[0] ) . '</span>' . esc_html( $item[1] ) . '</li>'; }
		return $html . '</ul>';
	}

	private static function card_spec_icon( $type ) {
		$paths = array(
			'seats'        => '<circle cx="12" cy="8" r="3"/><path d="M6.5 20c.6-4 2.4-6 5.5-6s4.9 2 5.5 6"/>',
			'doors'        => '<path d="M6 3h9l3 5v13H6z"/><path d="M8 6h6l2 4H8zM14 14h1"/>',
			'transmission' => '<path d="M7 4v16M17 4v16M7 9h10M7 15h10"/><circle cx="7" cy="4" r="2"/><circle cx="17" cy="20" r="2"/>',
			'fuel'         => '<path d="M5 21V4h9v17M4 21h12M8 8h3M14 7h2l3 3v7a2 2 0 0 0 2 2"/>',
			'air'          => '<path d="M12 3v18M4.2 7.5l15.6 9M4.2 16.5l15.6-9M9 5l3 2 3-2M9 19l3-2 3 2"/>',
		);
		return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ( $paths[ $type ] ?? '' ) . '</g></svg>';
	}

	private static function card_inclusions( $vehicle ) {
		$items = array();
		if ( absint( $vehicle['included_km_daily'] ) ) { $items[] = sprintf( __( '%d km al giorno inclusi', 'gest-web-rent' ), absint( $vehicle['included_km_daily'] ) ); }
		if ( ! empty( $vehicle['insurance_included'] ) ) { $items[] = __( 'Assicurazione inclusa', 'gest-web-rent' ); }
		if ( ! empty( $vehicle['second_driver_included'] ) ) { $items[] = __( 'Secondo conducente incluso', 'gest-web-rent' ); }
		if ( empty( $items ) ) { return ''; }
		$html = '<div class="gwr-vehicle-card__included"><strong>' . esc_html__( 'Incluso nel prezzo', 'gest-web-rent' ) . '</strong><ul>';
		foreach ( array_slice( $items, 0, 4 ) as $item ) { $html .= '<li>' . esc_html( $item ) . '</li>'; }
		return $html . '</ul></div>';
	}

	private static function format_euro( $price ) {
		return (float) $price > 0 ? number_format_i18n( (float) $price, 2 ) . ' €' : '';
	}

	private static function modal_markup() {
		return '<div class="gwr-modal" data-gwr-modal aria-hidden="true" hidden><div class="gwr-modal__backdrop" data-gwr-close-modal></div><div class="gwr-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="gwr-modal-title" aria-describedby="gwr-modal-description" tabindex="-1"><button type="button" class="gwr-modal__close" data-gwr-close-modal aria-label="' . esc_attr__( 'Chiudi dettagli veicolo', 'gest-web-rent' ) . '">&times;</button><div class="gwr-modal__content" data-gwr-modal-content></div></div></div>';
	}

	private static function date_field( $name, $label, $value, $role ) {
		$field_id = wp_unique_id( 'gwr-' . sanitize_key( $name ) . '-' );
		$iso_id = $field_id . '-iso';
		$help_id = $field_id . '-help';
		$error_id = $field_id . '-error';
		$iso_value = self::date_to_iso( $value );
		$display_value = self::date_to_display( $value );
		if ( '' === $display_value && '' !== (string) $value ) {
			$display_value = sanitize_text_field( (string) $value );
		}

		$html = '<label for="' . esc_attr( $field_id ) . '"><span>' . esc_html( $label ) . '</span>';
		$html .= '<input id="' . esc_attr( $field_id ) . '" class="gwr-date-display" type="text" name="' . esc_attr( $name ) . '_display" value="' . esc_attr( $display_value ) . '" placeholder="GG-MM-AAAA" inputmode="numeric" autocomplete="off" aria-required="true" aria-describedby="' . esc_attr( $help_id . ' ' . $error_id ) . '" data-gwr-date-display data-gwr-date-role="' . esc_attr( $role ) . '" data-gwr-date-target="' . esc_attr( $iso_id ) . '" data-gwr-error-target="' . esc_attr( $error_id ) . '" />';
		$html .= '<small id="' . esc_attr( $help_id ) . '" class="gwr-field-help">' . esc_html__( 'Formato GG-MM-AAAA', 'gest-web-rent' ) . '</small><small id="' . esc_attr( $error_id ) . '" class="gwr-field-error" data-gwr-field-error hidden></small></label>';
		$html .= '<input id="' . esc_attr( $iso_id ) . '" type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $iso_value ) . '" data-gwr-date-iso />';
		return $html;
	}

	private static function time_field( $name, $label, $value, $role ) {
		$field_id = wp_unique_id( 'gwr-' . sanitize_key( $name ) . '-' );
		$error_id = $field_id . '-error';
		return '<label for="' . esc_attr( $field_id ) . '"><span>' . esc_html( $label ) . '</span><input id="' . esc_attr( $field_id ) . '" type="time" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ?: '09:00' ) . '" aria-required="true" aria-describedby="' . esc_attr( $error_id ) . '" data-gwr-time-role="' . esc_attr( $role ) . '" data-gwr-error-target="' . esc_attr( $error_id ) . '" /><small id="' . esc_attr( $error_id ) . '" class="gwr-field-error" data-gwr-field-error hidden></small></label>';
	}

	private static function location_field( $name, $label, $value, $options, $required, $data_attribute ) {
		$field_id = wp_unique_id( 'gwr-' . sanitize_key( $name ) . '-' );
		$error_id = $field_id . '-error';
		if ( 1 === count( $options ) && '' === (string) $value ) {
			$value = $options[0];
		}
		$is_required = $required && ! empty( $options );
		$html = '<label for="' . esc_attr( $field_id ) . '"><span>' . esc_html( $label ) . '</span><select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" aria-required="' . ( $is_required ? 'true' : 'false' ) . '" aria-describedby="' . esc_attr( $error_id ) . '" data-gwr-location-field data-gwr-required="' . ( $is_required ? '1' : '0' ) . '" data-gwr-error-target="' . esc_attr( $error_id ) . '" ' . esc_attr( $data_attribute ) . ( empty( $options ) ? ' disabled' : '' ) . '>';
		if ( empty( $options ) ) {
			$html .= '<option value="">' . esc_html__( 'Localita non configurata', 'gest-web-rent' ) . '</option>';
		} else {
			$html .= '<option value="">' . esc_html__( 'Seleziona localita', 'gest-web-rent' ) . '</option>';
			foreach ( $options as $option ) {
				$html .= '<option value="' . esc_attr( $option ) . '" ' . selected( $value, $option, false ) . '>' . esc_html( $option ) . '</option>';
			}
		}
		return $html . '</select><small id="' . esc_attr( $error_id ) . '" class="gwr-field-error" data-gwr-field-error hidden></small></label>';
	}

	private static function text_field( $name, $label, $value, $placeholder ) {
		$field_id = wp_unique_id( 'gwr-' . sanitize_key( $name ) . '-' );
		return '<label for="' . esc_attr( $field_id ) . '"><span>' . esc_html( $label ) . '</span><input id="' . esc_attr( $field_id ) . '" type="search" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" data-gwr-secondary-filter /></label>';
	}

	private static function number_field( $name, $label, $value, $placeholder ) {
		$field_id = wp_unique_id( 'gwr-' . sanitize_key( $name ) . '-' );
		$value = (float) $value > 0 ? $value : '';
		return '<label for="' . esc_attr( $field_id ) . '"><span>' . esc_html( $label ) . '</span><input id="' . esc_attr( $field_id ) . '" type="number" min="0" step="any" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" data-gwr-secondary-filter /></label>';
	}

	private static function select_field( $name, $label, $value, $options ) {
		$field_id = wp_unique_id( 'gwr-' . sanitize_key( $name ) . '-' );
		$html = '<label for="' . esc_attr( $field_id ) . '"><span>' . esc_html( $label ) . '</span><select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" data-gwr-secondary-filter><option value="">' . esc_html__( 'Tutti', 'gest-web-rent' ) . '</option>';
		foreach ( $options as $option ) {
			$html .= '<option value="' . esc_attr( $option ) . '" ' . selected( $value, $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		return $html . '</select></label>';
	}
	private static function style_vars( $max_width ) { $settings = GWR_Admin::get_settings(); $max_width = max( 960, min( 1800, absint( $max_width ) ) ); return '--gwr-primary:' . esc_attr( $settings['primary_color'] ) . ';--gwr-shell-max:' . esc_attr( $max_width ) . 'px;'; }
}
