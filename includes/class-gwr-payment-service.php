<?php
/**
 * Payment service and Stripe hosted Checkout integration.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Central payment state machine.
 */
class GWR_Payment_Service {
	const DB_VERSION = '1.7.0';
	const DB_OPTION  = 'gwr_payments_db_version';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 6 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'gwr_reconcile_payments', array( __CLASS__, 'reconcile_pending_payments' ) );
		add_action( 'admin_post_gwr_reconcile_payments', array( __CLASS__, 'handle_reconcile' ) );
		add_action( 'admin_post_gwr_refund_payment', array( __CLASS__, 'handle_refund' ) );
		add_action( 'admin_post_gwr_mark_bank_transfer', array( __CLASS__, 'handle_mark_bank_transfer' ) );

		if ( ! wp_next_scheduled( 'gwr_reconcile_payments' ) ) {
			wp_schedule_event( time() + ( 30 * MINUTE_IN_SECONDS ), 'hourly', 'gwr_reconcile_payments' );
		}
	}

	/**
	 * Payments table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_payments';
	}

	/**
	 * Payment events table.
	 *
	 * @return string
	 */
	public static function events_table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_payment_events';
	}

	/**
	 * Idempotent schema upgrade.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}
		self::create_tables();
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Create payment tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset  = $wpdb->get_charset_collate();
		$payments = self::table();
		$events   = self::events_table();

		$sql_payments = "CREATE TABLE {$payments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			provider varchar(40) NOT NULL,
			environment varchar(20) NOT NULL DEFAULT 'test',
			status varchar(40) NOT NULL DEFAULT 'created',
			payment_type varchar(40) NOT NULL DEFAULT 'deposit',
			currency char(3) NOT NULL DEFAULT 'EUR',
			amount int NOT NULL DEFAULT 0,
			amount_refunded int NOT NULL DEFAULT 0,
			provider_session_id varchar(190) NULL,
			provider_payment_intent_id varchar(190) NULL,
			provider_charge_id varchar(190) NULL,
			provider_refund_id varchar(190) NULL,
			idempotency_key varchar(190) NULL,
			attempt smallint unsigned NOT NULL DEFAULT 1,
			failure_code varchar(100) NULL,
			failure_message text NULL,
			metadata_json longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			paid_at datetime NULL,
			failed_at datetime NULL,
			refunded_at datetime NULL,
			expires_at datetime NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY provider_session_id (provider_session_id),
			KEY provider_payment_intent_id (provider_payment_intent_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset};";

		$sql_events = "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			provider_event_id varchar(190) NOT NULL,
			event_type varchar(100) NOT NULL,
			payment_id bigint(20) unsigned NULL,
			booking_id bigint(20) unsigned NULL,
			payload_hash varchar(128) NOT NULL,
			processed tinyint(1) NOT NULL DEFAULT 0,
			processing_error text NULL,
			received_at datetime NOT NULL,
			processed_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY provider_event_id (provider_event_id),
			KEY event_type (event_type),
			KEY payment_id (payment_id),
			KEY booking_id (booking_id),
			KEY processed (processed),
			KEY received_at (received_at)
		) {$charset};";

		dbDelta( $sql_payments );
		dbDelta( $sql_events );
	}

	/**
	 * Payment statuses.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'created'            => __( 'Creato', 'gest-web-rent' ),
			'pending'            => __( 'In attesa', 'gest-web-rent' ),
			'processing'         => __( 'In elaborazione', 'gest-web-rent' ),
			'paid'               => __( 'Pagato', 'gest-web-rent' ),
			'failed'             => __( 'Fallito', 'gest-web-rent' ),
			'cancelled'          => __( 'Annullato', 'gest-web-rent' ),
			'expired'            => __( 'Scaduto', 'gest-web-rent' ),
			'partially_refunded' => __( 'Parzialmente rimborsato', 'gest-web-rent' ),
			'refunded'           => __( 'Rimborsato', 'gest-web-rent' ),
			'disputed'           => __( 'Contestato', 'gest-web-rent' ),
		);
	}

	/**
	 * Register Stripe webhook endpoint.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'gest-web-rent/v1',
			'/stripe/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_stripe_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Start a payment for a booking when needed.
	 *
	 * @param array  $booking Booking.
	 * @param string $method Requested method.
	 * @return array|WP_Error
	 */
	public static function start_payment_for_booking( $booking, $method = '' ) {
		$booking = is_array( $booking ) ? $booking : GWR_Bookings::get( absint( $booking ) );
		if ( ! $booking ) {
			return new WP_Error( 'gwr_booking_missing', __( 'Prenotazione non trovata.', 'gest-web-rent' ) );
		}
		$settings = GWR_Pricing_Service::get_settings();
		$method   = sanitize_key( $method ?: ( $booking['payment_method'] ?? $settings['default_payment_method'] ) );
		$amount   = max( 0, GWR_Money::decimal_to_minor( $booking['pay_now_amount'] ?? 0 ) );

		if ( $amount <= 0 || in_array( $method, array( 'request', 'pickup' ), true ) ) {
			GWR_Bookings::sync_payment_status( $booking['id'], 'not_required', $method, $booking['status'], __( 'Nessun pagamento online richiesto.', 'gest-web-rent' ) );
			return array( 'required' => false, 'method' => $method );
		}

		if ( 'bank_transfer' === $method ) {
			$payment = self::create_payment_record( $booking, 'bank_transfer', 'manual', $amount, 'pending' );
			GWR_Bookings::sync_payment_status( $booking['id'], 'pending', $method, 'awaiting_bank_transfer', __( 'In attesa di bonifico.', 'gest-web-rent' ) );
			return array( 'required' => true, 'method' => 'bank_transfer', 'payment' => $payment, 'bank' => self::bank_instructions( $booking, $settings ) );
		}

		if ( 'stripe' !== $method ) {
			return new WP_Error( 'gwr_payment_method_invalid', __( 'Metodo di pagamento non disponibile.', 'gest-web-rent' ) );
		}
		if ( empty( $settings['stripe_enabled'] ) ) {
			return new WP_Error( 'gwr_stripe_disabled', __( 'Stripe non e configurato.', 'gest-web-rent' ) );
		}

		$payment = self::create_payment_record( $booking, 'stripe', $settings['stripe_mode'], $amount, 'created' );
		if ( is_wp_error( $payment ) ) {
			return $payment;
		}

		$session = GWR_Stripe_Service::create_checkout_session( $booking, $payment, $settings );
		if ( is_wp_error( $session ) ) {
			self::mark_payment_failed( $payment['id'], $session->get_error_code(), $session->get_error_message() );
			return $session;
		}

		self::update_payment(
			$payment['id'],
			array(
				'status'              => 'pending',
				'provider_session_id' => sanitize_text_field( $session['id'] ?? '' ),
				'expires_at'          => ! empty( $session['expires_at'] ) ? gmdate( 'Y-m-d H:i:s', absint( $session['expires_at'] ) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) : null,
				'metadata_json'       => wp_json_encode( array( 'checkout_session' => array( 'id' => $session['id'] ?? '', 'url_created' => ! empty( $session['url'] ) ) ) ),
			)
		);
		GWR_Bookings::sync_payment_status( $booking['id'], 'pending', 'stripe', 'awaiting_payment', __( 'Checkout Stripe creato.', 'gest-web-rent' ) );

		return array(
			'required'     => true,
			'method'       => 'stripe',
			'payment_id'   => absint( $payment['id'] ),
			'redirect_url' => esc_url_raw( $session['url'] ?? '' ),
			'status'       => 'pending',
		);
	}

	/**
	 * Retry or resume a customer payment without trusting frontend state.
	 *
	 * @param array|int $booking Booking or booking ID.
	 * @param string    $method Requested method.
	 * @return array|WP_Error
	 */
	public static function retry_payment_for_booking( $booking, $method = '' ) {
		$booking = is_array( $booking ) ? $booking : GWR_Bookings::get( absint( $booking ) );
		if ( ! $booking ) {
			return new WP_Error( 'gwr_booking_missing', __( 'Prenotazione non trovata.', 'gest-web-rent' ) );
		}
		if ( in_array( $booking['payment_status'] ?? '', array( 'paid', 'refunded', 'partially_refunded' ), true ) ) {
			return new WP_Error( 'gwr_payment_retry_not_needed', __( 'La prenotazione risulta gia saldata o rimborsata.', 'gest-web-rent' ) );
		}
		if ( ! in_array( $booking['status'], array( 'pending', 'awaiting_payment', 'payment_failed', 'awaiting_bank_transfer' ), true ) ) {
			return new WP_Error( 'gwr_payment_retry_not_allowed', __( 'Questa prenotazione non puo essere pagata online in questo stato.', 'gest-web-rent' ) );
		}

		$settings = GWR_Pricing_Service::get_settings();
		$method   = sanitize_key( $method ?: ( $booking['payment_method'] ?? $settings['default_payment_method'] ) );
		if ( 'stripe' === $method ) {
			$existing = self::latest_for_booking( $booking['id'], 'stripe' );
			if ( $existing && in_array( $existing['status'], array( 'created', 'pending', 'processing' ), true ) && ! empty( $existing['provider_session_id'] ) ) {
				$resumed = self::resume_checkout_session( $existing, $booking, $settings );
				if ( $resumed ) {
					return $resumed;
				}
			}
		}

		return self::start_payment_for_booking( $booking, $method );
	}

	/**
	 * Latest payment for a booking.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $provider Optional provider.
	 * @return array|null
	 */
	public static function latest_for_booking( $booking_id, $provider = '' ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table() . ' WHERE booking_id=%d';
		$values = array( absint( $booking_id ) );
		if ( $provider ) {
			$sql .= ' AND provider=%s';
			$values[] = sanitize_key( $provider );
		}
		$sql .= ' ORDER BY created_at DESC, id DESC LIMIT 1';
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $values ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Create a new payment attempt.
	 *
	 * @param array  $booking Booking.
	 * @param string $provider Provider.
	 * @param string $environment Environment.
	 * @param int    $amount Amount.
	 * @param string $status Status.
	 * @return array|WP_Error
	 */
	public static function create_payment_record( $booking, $provider, $environment, $amount, $status = 'created' ) {
		global $wpdb;
		$attempt = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE booking_id=%d AND provider=%s', absint( $booking['id'] ), sanitize_key( $provider ) ) );
		$key     = 'gwr-booking-' . absint( $booking['id'] ) . '-payment-' . $attempt;
		$data    = array(
			'booking_id'       => absint( $booking['id'] ),
			'provider'         => sanitize_key( $provider ),
			'environment'      => sanitize_key( $environment ),
			'status'           => sanitize_key( $status ),
			'payment_type'     => (int) $amount >= GWR_Money::decimal_to_minor( $booking['total_amount'] ?? 0 ) ? 'full' : 'deposit',
			'currency'         => GWR_Money::normalize_currency( $booking['currency'] ?? 'EUR' ),
			'amount'           => absint( $amount ),
			'amount_refunded'  => 0,
			'idempotency_key'  => $key,
			'attempt'          => $attempt,
			'metadata_json'    => wp_json_encode( self::safe_metadata( $booking ) ),
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
		);
		if ( false === $wpdb->insert( self::table(), $data ) ) {
			return new WP_Error( 'gwr_payment_create_failed', __( 'Impossibile creare il pagamento.', 'gest-web-rent' ) );
		}
		return self::get( (int) $wpdb->insert_id );
	}

	/**
	 * REST callback for Stripe webhooks.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_stripe_webhook( WP_REST_Request $request ) {
		$payload   = $request->get_body();
		$signature = $request->get_header( 'stripe-signature' );
		$settings  = GWR_Pricing_Service::get_settings();
		$secret    = GWR_Stripe_Service::webhook_secret( $settings );
		if ( ! $secret || ! GWR_Stripe_Service::verify_signature( $payload, $signature, $secret ) ) {
			self::remember_webhook_error( __( 'Firma webhook Stripe non valida.', 'gest-web-rent' ) );
			return new WP_REST_Response( array( 'error' => 'invalid_signature' ), 400 );
		}
		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['type'] ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_payload' ), 400 );
		}
		$result = self::process_provider_event( $event, $payload );
		return new WP_REST_Response( array( 'received' => true, 'processed' => ! is_wp_error( $result ) ), is_wp_error( $result ) ? 202 : 200 );
	}

	/**
	 * Process provider event idempotently.
	 *
	 * @param array  $event Event.
	 * @param string $payload Raw payload.
	 * @return bool|WP_Error
	 */
	public static function process_provider_event( $event, $payload = '' ) {
		global $wpdb;
		$event_id = sanitize_text_field( $event['id'] );
		$type     = sanitize_text_field( $event['type'] );
		$hash     = hash( 'sha256', (string) $payload );
		$inserted = $wpdb->insert(
			self::events_table(),
			array(
				'provider_event_id' => $event_id,
				'event_type'        => $type,
				'payload_hash'      => $hash,
				'processed'         => 0,
				'received_at'       => current_time( 'mysql' ),
			)
		);
		if ( false === $inserted ) {
			return true;
		}
		$event_row_id = (int) $wpdb->insert_id;
		$object       = $event['data']['object'] ?? array();
		$payment      = self::payment_from_provider_object( $object );
		$error        = '';
		try {
			if ( ! $payment ) {
				throw new Exception( 'Payment not found for provider event.' );
			}
			if ( 'checkout.session.completed' === $type ) {
				self::mark_payment_paid( $payment, $object );
			} elseif ( 'checkout.session.expired' === $type ) {
				self::mark_payment_expired( $payment, __( 'Sessione Stripe scaduta.', 'gest-web-rent' ) );
			} elseif ( 'payment_intent.succeeded' === $type ) {
				self::mark_payment_paid( $payment, $object );
			} elseif ( 'payment_intent.payment_failed' === $type ) {
				$last_error = $object['last_payment_error'] ?? array();
				self::mark_payment_failed( $payment['id'], sanitize_text_field( $last_error['code'] ?? 'payment_failed' ), sanitize_text_field( $last_error['message'] ?? __( 'Pagamento non completato.', 'gest-web-rent' ) ) );
			} elseif ( 'charge.refunded' === $type ) {
				self::mark_refund_from_charge( $payment, $object );
			} elseif ( 'charge.dispute.created' === $type ) {
				self::mark_payment_disputed( $payment, $object );
			}
			$wpdb->update( self::events_table(), array( 'payment_id' => $payment['id'], 'booking_id' => $payment['booking_id'], 'processed' => 1, 'processed_at' => current_time( 'mysql' ) ), array( 'id' => $event_row_id ) );
			self::remember_webhook_success();
		} catch ( Exception $exception ) {
			$error = $exception->getMessage();
			$wpdb->update( self::events_table(), array( 'payment_id' => $payment['id'] ?? null, 'booking_id' => $payment['booking_id'] ?? null, 'processing_error' => sanitize_text_field( $error ) ), array( 'id' => $event_row_id ) );
			self::remember_webhook_error( $error );
			return new WP_Error( 'gwr_webhook_processing_failed', $error );
		}
		return true;
	}

	/**
	 * Reconcile pending Stripe sessions.
	 *
	 * @return int
	 */
	public static function reconcile_pending_payments() {
		global $wpdb;
		$settings = GWR_Pricing_Service::get_settings();
		$rows = $wpdb->get_results( "SELECT * FROM " . self::table() . " WHERE provider='stripe' AND status IN ('created','pending','processing') ORDER BY created_at ASC LIMIT 20", ARRAY_A );
		$count = 0;
		foreach ( $rows as $payment ) {
			if ( empty( $payment['provider_session_id'] ) ) {
				continue;
			}
			$session = GWR_Stripe_Service::retrieve_checkout_session( $payment['provider_session_id'], $settings );
			if ( is_wp_error( $session ) ) {
				continue;
			}
			if ( 'complete' === ( $session['status'] ?? '' ) || 'paid' === ( $session['payment_status'] ?? '' ) ) {
				self::mark_payment_paid( $payment, $session );
				$count++;
			} elseif ( 'expired' === ( $session['status'] ?? '' ) ) {
				self::mark_payment_expired( $payment, __( 'Riconciliazione: sessione scaduta.', 'gest-web-rent' ) );
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Refund a payment.
	 *
	 * @param int    $payment_id Payment ID.
	 * @param int    $amount_minor Refund amount.
	 * @param string $reason Reason.
	 * @return array|WP_Error
	 */
	public static function refund_payment( $payment_id, $amount_minor, $reason = '' ) {
		$payment = self::get( $payment_id );
		if ( ! $payment || 'paid' !== $payment['status'] && 'partially_refunded' !== $payment['status'] ) {
			return new WP_Error( 'gwr_refund_invalid_payment', __( 'Pagamento non rimborsabile.', 'gest-web-rent' ) );
		}
		$refundable = max( 0, absint( $payment['amount'] ) - absint( $payment['amount_refunded'] ) );
		$amount     = min( $refundable, absint( $amount_minor ) );
		if ( $amount <= 0 ) {
			return new WP_Error( 'gwr_refund_amount_invalid', __( 'Importo rimborso non valido.', 'gest-web-rent' ) );
		}
		if ( 'stripe' !== $payment['provider'] ) {
			return self::mark_manual_refund( $payment, $amount, $reason );
		}
		$result = GWR_Stripe_Service::create_refund( $payment, $amount, $reason, GWR_Pricing_Service::get_settings() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		self::apply_refund_amount( $payment, $amount, sanitize_text_field( $result['id'] ?? '' ) );
		return self::get( $payment['id'] );
	}

	/** Admin handler: reconcile. */
	public static function handle_reconcile() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) );
		}
		check_admin_referer( 'gwr_reconcile_payments' );
		$count = self::reconcile_pending_payments();
		wp_safe_redirect( add_query_arg( array( 'page' => 'gwr-payments', 'gwr_message' => rawurlencode( sprintf( __( 'Pagamenti riconciliati: %d', 'gest-web-rent' ), $count ) ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Admin handler: refund. */
	public static function handle_refund() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) );
		}
		$payment_id = absint( $_POST['payment_id'] ?? 0 );
		check_admin_referer( 'gwr_refund_payment_' . $payment_id, 'gwr_payment_nonce' );
		$amount = GWR_Money::decimal_to_minor( $_POST['amount'] ?? 0 );
		$result = self::refund_payment( $payment_id, $amount, sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) ) );
		$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Rimborso registrato.', 'gest-web-rent' );
		wp_safe_redirect( add_query_arg( array( 'page' => 'gwr-payment-detail', 'payment_id' => $payment_id, 'gwr_message' => rawurlencode( $message ), 'gwr_error' => is_wp_error( $result ) ? 1 : 0 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Admin handler: mark bank transfer received. */
	public static function handle_mark_bank_transfer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) );
		}
		$payment_id = absint( $_POST['payment_id'] ?? 0 );
		check_admin_referer( 'gwr_mark_bank_transfer_' . $payment_id, 'gwr_payment_nonce' );
		$payment = self::get( $payment_id );
		if ( $payment && 'bank_transfer' === $payment['provider'] ) {
			self::mark_payment_paid( $payment, array( 'manual_reference' => sanitize_text_field( wp_unslash( $_POST['reference'] ?? '' ) ) ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'gwr-payment-detail', 'payment_id' => $payment_id, 'gwr_message' => rawurlencode( __( 'Bonifico segnato come ricevuto.', 'gest-web-rent' ) ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Query payments for admin.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array( 'status' => '', 'provider' => '', 'environment' => '', 'search' => '', 'page' => 1, 'per_page' => 20 ) );
		$where = array( '1=1' );
		$values = array();
		foreach ( array( 'status', 'provider', 'environment' ) as $field ) {
			if ( '' !== (string) $args[ $field ] ) {
				$where[] = "p.{$field}=%s";
				$values[] = sanitize_key( $args[ $field ] );
			}
		}
		if ( $args['search'] ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[] = '(b.booking_code LIKE %s OR b.customer_email LIKE %s OR p.provider_session_id LIKE %s OR p.provider_payment_intent_id LIKE %s)';
			array_push( $values, $like, $like, $like, $like );
		}
		$where_sql = implode( ' AND ', $where );
		$base = ' FROM ' . self::table() . ' p LEFT JOIN ' . GWR_Bookings::table() . ' b ON b.id=p.booking_id WHERE ' . $where_sql;
		$count_sql = 'SELECT COUNT(*)' . $base;
		if ( $values ) {
			$count_sql = $wpdb->prepare( $count_sql, $values );
		}
		$total = (int) $wpdb->get_var( $count_sql );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ) ) );
		$offset = ( max( 1, absint( $args['page'] ) ) - 1 ) * $per_page;
		$list_sql = 'SELECT p.*, b.booking_code, b.customer_email, b.total_amount, b.pay_now_amount' . $base . ' ORDER BY p.created_at DESC LIMIT %d OFFSET %d';
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $values, array( $per_page, $offset ) ) ), ARRAY_A );
		return array( 'items' => is_array( $rows ) ? $rows : array(), 'total' => $total, 'pages' => (int) ceil( $total / $per_page ) );
	}

	/** Get payment by ID. */
	public static function get( $payment_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT p.*, b.booking_code, b.customer_email FROM ' . self::table() . ' p LEFT JOIN ' . GWR_Bookings::table() . ' b ON b.id=p.booking_id WHERE p.id=%d', absint( $payment_id ) ), ARRAY_A );
	}

	/** Events for one payment. */
	public static function events( $payment_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::events_table() . ' WHERE payment_id=%d ORDER BY received_at DESC, id DESC', absint( $payment_id ) ), ARRAY_A );
	}

	private static function resume_checkout_session( $payment, $booking, $settings ) {
		$session = GWR_Stripe_Service::retrieve_checkout_session( $payment['provider_session_id'], $settings );
		if ( is_wp_error( $session ) ) {
			return null;
		}
		if ( 'complete' === ( $session['status'] ?? '' ) || 'paid' === ( $session['payment_status'] ?? '' ) ) {
			self::mark_payment_paid( $payment, $session );
			return array( 'required' => false, 'method' => 'stripe', 'payment_id' => absint( $payment['id'] ), 'status' => 'paid' );
		}
		if ( 'expired' === ( $session['status'] ?? '' ) ) {
			self::mark_payment_expired( $payment, __( 'Sessione Stripe scaduta.', 'gest-web-rent' ) );
			return new WP_Error( 'gwr_checkout_session_expired', __( 'La sessione di pagamento e scaduta. La disponibilita e stata rilasciata.', 'gest-web-rent' ) );
		}
		if ( ! empty( $session['url'] ) ) {
			GWR_Bookings::sync_payment_status( $booking['id'], 'pending', 'stripe', 'awaiting_payment', __( 'Checkout Stripe riaperto.', 'gest-web-rent' ) );
			return array(
				'required'     => true,
				'method'       => 'stripe',
				'payment_id'   => absint( $payment['id'] ),
				'redirect_url' => esc_url_raw( $session['url'] ),
				'status'       => 'pending',
			);
		}
		return null;
	}

	private static function payment_from_provider_object( $object ) {
		global $wpdb;
		$session_id = sanitize_text_field( $object['id'] ?? '' );
		$intent_id  = sanitize_text_field( $object['payment_intent'] ?? ( $object['id'] ?? '' ) );
		$charge_id  = sanitize_text_field( $object['charge'] ?? ( $object['id'] ?? '' ) );
		$metadata   = is_array( $object['metadata'] ?? null ) ? $object['metadata'] : array();
		$booking_id = absint( $metadata['booking_id'] ?? ( $object['client_reference_id'] ?? 0 ) );
		if ( $session_id ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE provider_session_id=%s', $session_id ), ARRAY_A );
			if ( $row ) {
				return $row;
			}
		}
		if ( $intent_id ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE provider_payment_intent_id=%s', $intent_id ), ARRAY_A );
			if ( $row ) {
				return $row;
			}
		}
		if ( $charge_id ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE provider_charge_id=%s', $charge_id ), ARRAY_A );
			if ( $row ) {
				return $row;
			}
		}
		if ( $booking_id ) {
			return self::latest_for_booking( $booking_id, 'stripe' );
		}
		return null;
	}

	private static function mark_payment_paid( $payment, $object ) {
		$payment = is_array( $payment ) ? $payment : self::get( $payment );
		if ( ! $payment || 'paid' === $payment['status'] ) {
			return;
		}
		self::update_payment(
			$payment['id'],
			array(
				'status'                     => 'paid',
				'provider_payment_intent_id' => sanitize_text_field( $object['payment_intent'] ?? ( $object['id'] ?? $payment['provider_payment_intent_id'] ) ),
				'provider_charge_id'         => sanitize_text_field( $object['charge'] ?? ( $payment['provider_charge_id'] ?? '' ) ),
				'paid_at'                    => current_time( 'mysql' ),
				'failure_code'               => '',
				'failure_message'            => '',
			)
		);
		GWR_Bookings::sync_payment_status( $payment['booking_id'], 'paid', $payment['provider'], 'confirmed', __( 'Pagamento confermato.', 'gest-web-rent' ) );
		GWR_Coupon_Service::mark_used_by_booking( $payment['booking_id'] );
	}

	private static function mark_payment_failed( $payment_id, $code, $message ) {
		$payment = self::get( $payment_id );
		self::update_payment( $payment_id, array( 'status' => 'failed', 'failure_code' => sanitize_key( $code ), 'failure_message' => sanitize_text_field( $message ), 'failed_at' => current_time( 'mysql' ) ) );
		if ( $payment ) {
			GWR_Bookings::sync_payment_status( $payment['booking_id'], 'failed', $payment['provider'], 'payment_failed', __( 'Pagamento non completato. Il cliente puo riprovare.', 'gest-web-rent' ) );
		}
	}

	private static function mark_payment_expired( $payment, $note ) {
		self::update_payment( $payment['id'], array( 'status' => 'expired' ) );
		GWR_Bookings::sync_payment_status( $payment['booking_id'], 'expired', $payment['provider'], 'expired', $note );
		GWR_Coupon_Service::release_by_booking( $payment['booking_id'] );
	}

	private static function mark_payment_disputed( $payment, $object ) {
		self::update_payment( $payment['id'], array( 'status' => 'disputed', 'metadata_json' => wp_json_encode( array( 'dispute' => array( 'id' => sanitize_text_field( $object['id'] ?? '' ), 'amount' => absint( $object['amount'] ?? 0 ) ) ) ) ) );
		GWR_Bookings::add_system_log( $payment['booking_id'], 'payment_disputed', __( 'Contestazione Stripe ricevuta.', 'gest-web-rent' ) );
	}

	private static function mark_refund_from_charge( $payment, $object ) {
		$refunded = absint( $object['amount_refunded'] ?? 0 );
		$status = $refunded >= absint( $payment['amount'] ) ? 'refunded' : 'partially_refunded';
		self::update_payment( $payment['id'], array( 'status' => $status, 'amount_refunded' => $refunded, 'provider_charge_id' => sanitize_text_field( $object['id'] ?? $payment['provider_charge_id'] ), 'refunded_at' => current_time( 'mysql' ) ) );
		GWR_Bookings::sync_payment_status( $payment['booking_id'], $status, $payment['provider'], 'refunded' === $status ? 'refunded' : null, __( 'Rimborso registrato da Stripe.', 'gest-web-rent' ) );
	}

	private static function mark_manual_refund( $payment, $amount, $reason ) {
		self::apply_refund_amount( $payment, $amount, 'manual-' . time() );
		return self::get( $payment['id'] );
	}

	private static function apply_refund_amount( $payment, $amount, $refund_id ) {
		$new_refunded = min( absint( $payment['amount'] ), absint( $payment['amount_refunded'] ) + absint( $amount ) );
		$status = $new_refunded >= absint( $payment['amount'] ) ? 'refunded' : 'partially_refunded';
		self::update_payment( $payment['id'], array( 'status' => $status, 'amount_refunded' => $new_refunded, 'provider_refund_id' => sanitize_text_field( $refund_id ), 'refunded_at' => current_time( 'mysql' ) ) );
		GWR_Bookings::sync_payment_status( $payment['booking_id'], $status, $payment['provider'], 'refunded' === $status ? 'refunded' : null, __( 'Rimborso registrato.', 'gest-web-rent' ) );
	}

	private static function update_payment( $payment_id, $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		$wpdb->update( self::table(), $data, array( 'id' => absint( $payment_id ) ) );
	}

	private static function safe_metadata( $booking ) {
		return array(
			'booking_id'      => absint( $booking['id'] ),
			'booking_code'    => sanitize_text_field( $booking['booking_code'] ),
			'environment'     => sanitize_key( GWR_Pricing_Service::get_settings()['stripe_mode'] ?? 'test' ),
			'pricing_version' => sanitize_text_field( $booking['snapshot']['price']['pricing_version'] ?? GWR_Pricing_Service::VERSION ),
		);
	}

	private static function bank_instructions( $booking, $settings ) {
		return array(
			'holder'       => sanitize_text_field( $settings['bank_account_holder'] ),
			'iban'         => sanitize_text_field( $settings['bank_iban'] ),
			'bic'          => sanitize_text_field( $settings['bank_bic'] ),
			'bank_name'    => sanitize_text_field( $settings['bank_name'] ),
			'reason'       => str_replace( '{booking_code}', $booking['booking_code'], sanitize_text_field( $settings['bank_reason_template'] ) ),
			'due_days'     => absint( $settings['bank_due_days'] ),
			'instructions' => sanitize_textarea_field( $settings['bank_instructions'] ),
		);
	}

	private static function remember_webhook_success() {
		$settings = GWR_Pricing_Service::get_settings();
		$settings['webhook_last_event_at'] = current_time( 'mysql' );
		$settings['webhook_last_error'] = '';
		update_option( GWR_Pricing_Service::OPTION_NAME, $settings, false );
	}

	private static function remember_webhook_error( $message ) {
		$settings = GWR_Pricing_Service::get_settings();
		$settings['webhook_last_error'] = sanitize_text_field( $message );
		update_option( GWR_Pricing_Service::OPTION_NAME, $settings, false );
	}
}

/**
 * Stripe API integration using WordPress HTTP API.
 */
class GWR_Stripe_Service {
	const API_BASE = 'https://api.stripe.com/v1';

	/**
	 * Create Checkout Session.
	 *
	 * @param array $booking Booking.
	 * @param array $payment Payment.
	 * @param array $settings Settings.
	 * @return array|WP_Error
	 */
	public static function create_checkout_session( $booking, $payment, $settings ) {
		$secret = self::secret_key( $settings );
		if ( ! $secret ) {
			return new WP_Error( 'gwr_stripe_key_missing', __( 'Secret key Stripe mancante.', 'gest-web-rent' ) );
		}
		$success_url = self::success_url( $booking, $settings );
		$cancel_url  = self::cancel_url( $booking, $settings );
		$description = str_replace( '{booking_code}', $booking['booking_code'], $settings['stripe_description'] );
		$body = array(
			'mode'                  => 'payment',
			'client_reference_id'   => (string) $booking['id'],
			'success_url'           => $success_url,
			'cancel_url'            => $cancel_url,
			'expires_at'            => time() + ( max( 30, absint( $settings['stripe_session_expiry_minutes'] ?? 60 ) ) * MINUTE_IN_SECONDS ),
			'line_items[0][quantity]' => 1,
			'line_items[0][price_data][currency]' => strtolower( GWR_Money::normalize_currency( $payment['currency'] ) ),
			'line_items[0][price_data][unit_amount]' => absint( $payment['amount'] ),
			'line_items[0][price_data][product_data][name]' => $description,
			'metadata[booking_id]'  => (string) absint( $booking['id'] ),
			'metadata[booking_code]' => sanitize_text_field( $booking['booking_code'] ),
			'metadata[environment]' => sanitize_key( $settings['stripe_mode'] ),
			'metadata[pricing_version]' => sanitize_text_field( $booking['snapshot']['price']['pricing_version'] ?? GWR_Pricing_Service::VERSION ),
			'payment_intent_data[metadata][booking_id]' => (string) absint( $booking['id'] ),
			'payment_intent_data[metadata][booking_code]' => sanitize_text_field( $booking['booking_code'] ),
			'payment_intent_data[metadata][environment]' => sanitize_key( $settings['stripe_mode'] ),
			'payment_intent_data[metadata][pricing_version]' => sanitize_text_field( $booking['snapshot']['price']['pricing_version'] ?? GWR_Pricing_Service::VERSION ),
		);
		if ( ! empty( $settings['stripe_receipt_email'] ) && is_email( $booking['customer_email'] ) ) {
			$body['customer_email'] = $booking['customer_email'];
		}
		if ( ! empty( $settings['stripe_cards_enabled'] ) ) {
			$body['payment_method_types[0]'] = 'card';
		}
		return self::request( 'POST', '/checkout/sessions', $secret, $body, $payment['idempotency_key'] );
	}

	/**
	 * Retrieve Checkout Session.
	 *
	 * @param string $session_id Session ID.
	 * @param array  $settings Settings.
	 * @return array|WP_Error
	 */
	public static function retrieve_checkout_session( $session_id, $settings ) {
		$secret = self::secret_key( $settings );
		if ( ! $secret ) {
			return new WP_Error( 'gwr_stripe_key_missing', __( 'Secret key Stripe mancante.', 'gest-web-rent' ) );
		}
		return self::request( 'GET', '/checkout/sessions/' . rawurlencode( $session_id ), $secret );
	}

	/**
	 * Create refund.
	 *
	 * @param array  $payment Payment.
	 * @param int    $amount Amount.
	 * @param string $reason Reason.
	 * @param array  $settings Settings.
	 * @return array|WP_Error
	 */
	public static function create_refund( $payment, $amount, $reason, $settings ) {
		$secret = self::secret_key( $settings );
		if ( ! $secret ) {
			return new WP_Error( 'gwr_stripe_key_missing', __( 'Secret key Stripe mancante.', 'gest-web-rent' ) );
		}
		$body = array(
			'amount' => absint( $amount ),
			'metadata[booking_id]' => absint( $payment['booking_id'] ),
			'metadata[reason]' => sanitize_text_field( $reason ),
		);
		if ( ! empty( $payment['provider_payment_intent_id'] ) ) {
			$body['payment_intent'] = $payment['provider_payment_intent_id'];
		} elseif ( ! empty( $payment['provider_charge_id'] ) ) {
			$body['charge'] = $payment['provider_charge_id'];
		} else {
			return new WP_Error( 'gwr_refund_provider_reference_missing', __( 'Riferimento Stripe mancante.', 'gest-web-rent' ) );
		}
		$key = 'gwr-refund-payment-' . absint( $payment['id'] ) . '-' . absint( $amount ) . '-' . substr( md5( $reason ), 0, 10 );
		return self::request( 'POST', '/refunds', $secret, $body, $key );
	}

	/**
	 * Test Stripe key.
	 *
	 * @param array $settings Settings.
	 * @return array|WP_Error
	 */
	public static function test_connection( $settings ) {
		$secret = self::secret_key( $settings );
		if ( ! $secret ) {
			return new WP_Error( 'gwr_stripe_key_missing', __( 'Secret key Stripe mancante.', 'gest-web-rent' ) );
		}
		return self::request( 'GET', '/account', $secret );
	}

	/**
	 * Verify Stripe webhook signature.
	 *
	 * @param string $payload Payload.
	 * @param string $header Header.
	 * @param string $secret Secret.
	 * @return bool
	 */
	public static function verify_signature( $payload, $header, $secret ) {
		if ( ! $payload || ! $header || ! $secret ) {
			return false;
		}
		$timestamp = 0;
		$signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			$bits = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $bits ) ) {
				continue;
			}
			if ( 't' === $bits[0] ) {
				$timestamp = absint( $bits[1] );
			}
			if ( 'v1' === $bits[0] ) {
				$signatures[] = $bits[1];
			}
		}
		if ( ! $timestamp || abs( time() - $timestamp ) > 300 ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}
		return false;
	}

	/** Secret key for current environment. */
	public static function secret_key( $settings ) {
		return 'live' === ( $settings['stripe_mode'] ?? 'test' ) ? sanitize_text_field( $settings['stripe_live_secret_key'] ?? '' ) : sanitize_text_field( $settings['stripe_test_secret_key'] ?? '' );
	}

	/** Webhook secret for current environment. */
	public static function webhook_secret( $settings ) {
		return 'live' === ( $settings['stripe_mode'] ?? 'test' ) ? sanitize_text_field( $settings['stripe_live_webhook_secret'] ?? '' ) : sanitize_text_field( $settings['stripe_test_webhook_secret'] ?? '' );
	}

	private static function request( $method, $path, $secret, $body = array(), $idempotency_key = '' ) {
		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $secret,
			),
		);
		if ( $idempotency_key ) {
			$args['headers']['Idempotency-Key'] = sanitize_text_field( $idempotency_key );
		}
		if ( 'GET' !== strtoupper( $method ) ) {
			$args['body'] = $body;
		}
		$response = wp_remote_request( self::API_BASE . $path, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error( 'gwr_stripe_api_error', sanitize_text_field( $data['error']['message'] ?? __( 'Errore Stripe.', 'gest-web-rent' ) ), array( 'status' => $status ) );
		}
		return is_array( $data ) ? $data : array();
	}

	private static function success_url( $booking, $settings ) {
		$url = esc_url_raw( $settings['stripe_success_url'] ?? '' );
		if ( ! $url ) {
			$url = add_query_arg( array( 'gwr_payment' => 'success', 'booking_code' => $booking['booking_code'], 'gwr_token' => $booking['public_token'] ?? '', 'session_id' => '{CHECKOUT_SESSION_ID}' ), home_url( '/' ) );
			$url = str_replace( '%7BCHECKOUT_SESSION_ID%7D', '{CHECKOUT_SESSION_ID}', $url );
		}
		return str_replace( array( '{booking_code}', '{booking_id}', '{booking_token}' ), array( rawurlencode( $booking['booking_code'] ), absint( $booking['id'] ), rawurlencode( $booking['public_token'] ?? '' ) ), $url );
	}

	private static function cancel_url( $booking, $settings ) {
		$url = esc_url_raw( $settings['stripe_cancel_url'] ?? '' );
		if ( ! $url ) {
			$url = add_query_arg( array( 'gwr_payment' => 'cancel', 'booking_code' => $booking['booking_code'], 'gwr_token' => $booking['public_token'] ?? '' ), home_url( '/' ) );
		}
		return str_replace( array( '{booking_code}', '{booking_id}', '{booking_token}' ), array( rawurlencode( $booking['booking_code'] ), absint( $booking['id'] ), rawurlencode( $booking['public_token'] ?? '' ) ), $url );
	}
}
