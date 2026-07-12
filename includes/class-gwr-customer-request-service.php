<?php
/**
 * Customer change and cancellation requests.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

class GWR_Customer_Request_Service {
	const DB_VERSION = '1.9.0';
	const DB_OPTION = 'gwr_customer_requests_db_version';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 6 );
		add_action( 'admin_post_gwr_customer_request', array( __CLASS__, 'handle_customer_request' ) );
		add_action( 'admin_post_nopriv_gwr_customer_request', array( __CLASS__, 'handle_customer_request' ) );
		add_action( 'admin_post_gwr_customer_request_action', array( __CLASS__, 'handle_admin_action' ) );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_customer_requests';
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE " . self::table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			request_kind varchar(30) NOT NULL DEFAULT 'change',
			request_type varchar(50) NOT NULL,
			description text NOT NULL,
			proposed_json longtext NULL,
			estimated_difference decimal(12,2) NULL,
			status varchar(30) NOT NULL DEFAULT 'submitted',
			customer_attachment_id bigint(20) unsigned NULL,
			admin_user_id bigint(20) unsigned NULL,
			admin_response text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			resolved_at datetime NULL,
			PRIMARY KEY  (id),
			KEY booking_status (booking_id,status),
			KEY kind_status (request_kind,status),
			KEY created_at (created_at)
		) $charset;" );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_OPTION ) !== self::DB_VERSION ) {
			self::create_tables();
		}
	}

	public static function types() {
		return array(
			'dates' => __( 'Date', 'gest-web-rent' ), 'times' => __( 'Orari', 'gest-web-rent' ), 'location' => __( 'Sede', 'gest-web-rent' ),
			'vehicle' => __( 'Veicolo', 'gest-web-rent' ), 'driver' => __( 'Conducente', 'gest-web-rent' ), 'customer' => __( 'Dati anagrafici', 'gest-web-rent' ),
			'extras' => __( 'Extra', 'gest-web-rent' ), 'coverages' => __( 'Coperture', 'gest-web-rent' ), 'other' => __( 'Altro', 'gest-web-rent' ),
		);
	}

	public static function statuses() {
		return array( 'submitted' => __( 'Inviata', 'gest-web-rent' ), 'reviewing' => __( 'In valutazione', 'gest-web-rent' ), 'approved' => __( 'Approvata', 'gest-web-rent' ), 'rejected' => __( 'Rifiutata', 'gest-web-rent' ), 'needs_info' => __( 'Informazioni richieste', 'gest-web-rent' ), 'cancelled' => __( 'Annullata', 'gest-web-rent' ) );
	}

	public static function for_booking( $booking_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE booking_id=%d ORDER BY created_at DESC,id DESC', absint( $booking_id ) ), ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['proposed'] = json_decode( (string) $row['proposed_json'], true );
			$row['proposed'] = is_array( $row['proposed'] ) ? $row['proposed'] : array();
		}
		return $rows;
	}

	public static function get( $request_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id=%d', absint( $request_id ) ), ARRAY_A );
		if ( $row ) { $row['proposed'] = json_decode( (string) $row['proposed_json'], true ) ?: array(); }
		return $row ?: null;
	}

	public static function create( $booking, $raw ) {
		global $wpdb;
		$kind = 'cancellation' === sanitize_key( $raw['request_kind'] ?? '' ) ? 'cancellation' : 'change';
		$type = 'cancellation' === $kind ? 'cancellation' : sanitize_key( $raw['request_type'] ?? '' );
		if ( 'change' === $kind && ! isset( self::types()[ $type ] ) ) {
			return new WP_Error( 'gwr_request_type_invalid', __( 'Tipo richiesta non valido.', 'gest-web-rent' ) );
		}
		if ( 'cancellation' === $kind && ! in_array( $booking['status'], array( 'pending', 'awaiting_payment', 'awaiting_bank_transfer', 'payment_failed', 'confirmed' ), true ) ) {
			return new WP_Error( 'gwr_cancellation_not_allowed', __( 'La prenotazione non consente una richiesta di annullamento.', 'gest-web-rent' ) );
		}
		$description = sanitize_textarea_field( $raw['description'] ?? '' );
		if ( strlen( trim( $description ) ) < 10 ) {
			return new WP_Error( 'gwr_request_description_short', __( 'Descrivi la richiesta con almeno 10 caratteri.', 'gest-web-rent' ) );
		}
		if ( 'cancellation' === $kind && empty( $raw['confirmed'] ) ) {
			return new WP_Error( 'gwr_cancellation_confirmation_required', __( 'Conferma esplicitamente la richiesta di annullamento.', 'gest-web-rent' ) );
		}
		$proposed = self::sanitize_proposed( $type, $raw['proposed'] ?? array() );
		$estimate = self::estimate_difference( $booking, $type, $proposed );
		$now = current_time( 'mysql' );
		$wpdb->insert( self::table(), array(
			'booking_id' => absint( $booking['id'] ), 'request_kind' => $kind, 'request_type' => $type,
			'description' => $description, 'proposed_json' => wp_json_encode( $proposed ), 'estimated_difference' => $estimate, 'status' => 'submitted',
			'created_at' => $now, 'updated_at' => $now,
		) );
		$id = (int) $wpdb->insert_id;
		GWR_Bookings::add_system_log( $booking['id'], 'customer_request_submitted', sprintf( __( 'Richiesta cliente #%1$d: %2$s.', 'gest-web-rent' ), $id, $kind ) );
		$event = 'cancellation' === $kind ? 'cancellation_request_received' : 'change_request_received';
		GWR_Notification_Service::send_event( $booking, $event, array( 'reference' => 'request:' . $id ) );
		$admin_email = sanitize_email( GWR_Admin::get_settings()['contact_email'] ?? '' );
		if ( $admin_email ) {
			GWR_Notification_Service::send_event( $booking, $event, array( 'reference' => 'request-admin:' . $id, 'recipient' => $admin_email ) );
		}
		return $id;
	}

	private static function sanitize_proposed( $type, $raw ) {
		$raw = is_array( $raw ) ? wp_unslash( $raw ) : array();
		$allowed = array(
			'dates' => array( 'start_date', 'end_date' ), 'times' => array( 'pickup_time', 'return_time' ), 'location' => array( 'pickup_location', 'return_location' ),
			'vehicle' => array( 'vehicle_id' ), 'driver' => array( 'driver_first_name', 'driver_last_name', 'driver_birth_date', 'driver_license_type', 'driver_license_country', 'driver_license_issue_date', 'driver_license_expiry_date' ),
			'customer' => array( 'customer_first_name', 'customer_last_name', 'customer_email', 'customer_phone' ), 'extras' => array( 'selection_notes' ), 'coverages' => array( 'selection_notes' ), 'other' => array( 'notes' ),
		);
		$out = array();
		foreach ( $allowed[ $type ] ?? array() as $key ) {
			if ( isset( $raw[ $key ] ) && '' !== (string) $raw[ $key ] ) {
				$out[ $key ] = 'vehicle_id' === $key ? absint( $raw[ $key ] ) : ( 'customer_email' === $key ? sanitize_email( $raw[ $key ] ) : sanitize_text_field( $raw[ $key ] ) );
			}
		}
		return $out;
	}

	private static function estimate_difference( $booking, $type, $proposed ) {
		if ( ! in_array( $type, array( 'dates', 'times', 'location', 'vehicle' ), true ) || empty( $proposed ) ) { return null; }
		$vehicle_id = absint( $proposed['vehicle_id'] ?? $booking['vehicle_id'] );
		$vehicle = GWR_CPT::get_vehicle( $vehicle_id );
		if ( ! $vehicle ) { return null; }
		$start = sanitize_text_field( $proposed['start_date'] ?? substr( $booking['pickup_datetime'], 0, 10 ) );
		$end = sanitize_text_field( $proposed['end_date'] ?? substr( $booking['return_datetime'], 0, 10 ) );
		$pickup_time = sanitize_text_field( $proposed['pickup_time'] ?? substr( $booking['pickup_datetime'], 11, 5 ) );
		$return_time = sanitize_text_field( $proposed['return_time'] ?? substr( $booking['return_datetime'], 11, 5 ) );
		try { $pickup = new DateTimeImmutable( $start . ' ' . $pickup_time, wp_timezone() ); $return = new DateTimeImmutable( $end . ' ' . $return_time, wp_timezone() ); }
		catch ( Exception $exception ) { return null; }
		if ( $return <= $pickup ) { return null; }
		$days = max( 1, (int) ceil( ( $return->getTimestamp() - $pickup->getTimestamp() ) / DAY_IN_SECONDS ) );
		$period = array( 'pickup' => $pickup, 'return' => $return, 'pickup_mysql' => $pickup->format( 'Y-m-d H:i:s' ), 'return_mysql' => $return->format( 'Y-m-d H:i:s' ), 'days' => $days );
		$terms = GWR_Rental_Terms::public_payload( $vehicle_id, $vehicle );
		$price = GWR_Bookings::calculate_price( $vehicle, $period, $booking['selection'], $booking['driver'], $terms, array( 'customer_email' => $booking['customer_email'], 'payment_method' => $booking['payment_method'] ) );
		return is_wp_error( $price ) ? null : round( ( (int) $price['total_minor'] / 100 ) - (float) $booking['total_amount'], 2 );
	}

	public static function handle_customer_request() {
		$code = sanitize_text_field( wp_unslash( $_POST['booking_code'] ?? '' ) );
		$token = sanitize_text_field( wp_unslash( $_POST['gwr_token'] ?? '' ) );
		$booking = self::booking_by_code( $code );
		if ( ! $booking || ! GWR_Bookings::verify_public_token( $booking, $token ) ) {
			wp_die( esc_html__( 'Accesso prenotazione non valido.', 'gest-web-rent' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'gwr_customer_request_' . $booking['id'], 'gwr_request_nonce' );
		$rate = self::rate_limit( 'request', 8, HOUR_IN_SECONDS );
		$result = is_wp_error( $rate ) ? $rate : self::create( $booking, $_POST );
		self::redirect_portal( $booking, $token, $result, __( 'Richiesta inviata. La prenotazione non e stata modificata automaticamente.', 'gest-web-rent' ) );
	}

	public static function handle_admin_action() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) ); }
		$request_id = absint( $_POST['request_id'] ?? 0 );
		check_admin_referer( 'gwr_customer_request_action_' . $request_id );
		$request = self::get( $request_id );
		if ( ! $request ) { wp_die( esc_html__( 'Richiesta non trovata.', 'gest-web-rent' ) ); }
		$action = sanitize_key( $_POST['request_action'] ?? '' );
		$response = sanitize_textarea_field( wp_unslash( $_POST['admin_response'] ?? '' ) );
		$result = self::transition( $request, $action, $response );
		$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Richiesta aggiornata.', 'gest-web-rent' );
		wp_safe_redirect( add_query_arg( array( 'page' => 'gwr-booking-detail', 'booking_id' => $request['booking_id'], 'gwr_message' => rawurlencode( $message ), 'gwr_error' => is_wp_error( $result ) ? 1 : 0 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function transition( $request, $action, $response ) {
		global $wpdb;
		$allowed = array( 'reviewing', 'approved', 'rejected', 'needs_info', 'cancelled' );
		if ( ! in_array( $action, $allowed, true ) ) { return new WP_Error( 'gwr_request_transition_invalid', __( 'Azione non valida.', 'gest-web-rent' ) ); }
		$booking = GWR_Bookings::get( $request['booking_id'] );
		if ( ! $booking ) { return new WP_Error( 'gwr_booking_missing', __( 'Prenotazione non trovata.', 'gest-web-rent' ) ); }
		if ( 'approved' === $action ) {
			if ( 'cancellation' === $request['request_kind'] ) {
				$result = GWR_Bookings::change_status( $booking['id'], 'cancelled', $response ?: __( 'Annullamento approvato su richiesta cliente.', 'gest-web-rent' ) );
				if ( is_wp_error( $result ) ) { return $result; }
				if ( class_exists( 'GWR_Document_Service' ) ) { GWR_Document_Service::generate( $booking['id'], 'cancellation_confirmation' ); }
			} elseif ( in_array( $request['request_type'], array( 'dates', 'times', 'location', 'vehicle', 'driver', 'customer' ), true ) ) {
				$result = GWR_Bookings::update_admin( $booking['id'], $request['proposed'] );
				if ( is_wp_error( $result ) ) { return $result; }
				if ( class_exists( 'GWR_Payment_Service' ) ) { GWR_Payment_Service::invalidate_open_attempts( $booking['id'] ); }
			} else {
				return new WP_Error( 'gwr_request_manual_application_required', __( 'Extra, coperture e richieste libere vanno applicati dai controlli prenotazione prima dell approvazione.', 'gest-web-rent' ) );
			}
		}
		$resolved = in_array( $action, array( 'approved', 'rejected', 'cancelled' ), true ) ? current_time( 'mysql' ) : null;
		$wpdb->update( self::table(), array( 'status' => $action, 'admin_user_id' => get_current_user_id(), 'admin_response' => $response, 'updated_at' => current_time( 'mysql' ), 'resolved_at' => $resolved ), array( 'id' => $request['id'] ) );
		GWR_Bookings::add_system_log( $booking['id'], 'customer_request_' . $action, sprintf( __( 'Richiesta cliente #%d aggiornata.', 'gest-web-rent' ), $request['id'] ) );
		if ( in_array( $action, array( 'approved', 'rejected' ), true ) ) {
			GWR_Notification_Service::send_event( GWR_Bookings::get( $booking['id'] ), 'approved' === $action ? 'change_request_approved' : 'change_request_rejected', array( 'reference' => 'request:' . $request['id'] . ':' . $action ) );
		}
		return true;
	}

	public static function admin_panel( $booking ) {
		$rows = self::for_booking( $booking['id'] );
		echo '<section class="gwr-data-grid-card gwr-customer-requests"><div class="gwr-data-grid-card__head"><div><h2>' . esc_html__( 'Richieste cliente', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Le richieste non modificano la prenotazione finche non vengono approvate.', 'gest-web-rent' ) . '</p></div></div>';
		if ( ! $rows ) { echo '<p>' . esc_html__( 'Nessuna richiesta cliente.', 'gest-web-rent' ) . '</p></section>'; return; }
		foreach ( $rows as $row ) {
			echo '<article class="gwr-customer-request"><header><strong>#' . esc_html( $row['id'] ) . ' - ' . esc_html( 'cancellation' === $row['request_kind'] ? __( 'Annullamento', 'gest-web-rent' ) : ( self::types()[ $row['request_type'] ] ?? $row['request_type'] ) ) . '</strong><span class="gwr-inline-status is-' . esc_attr( $row['status'] ) . '">' . esc_html( self::statuses()[ $row['status'] ] ?? $row['status'] ) . '</span></header><p>' . nl2br( esc_html( $row['description'] ) ) . '</p>';
			if ( null !== $row['estimated_difference'] ) { echo '<p><strong>' . esc_html__( 'Differenza stimata non definitiva:', 'gest-web-rent' ) . '</strong> ' . esc_html( GWR_Bookings::format_money( $row['estimated_difference'], $booking['currency'] ) ) . '</p>'; }
			if ( $row['proposed'] ) { echo '<dl>'; foreach ( $row['proposed'] as $key => $value ) { echo '<dt>' . esc_html( $key ) . '</dt><dd>' . esc_html( is_scalar( $value ) ? $value : wp_json_encode( $value ) ) . '</dd>'; } echo '</dl>'; }
			if ( $row['admin_response'] ) { echo '<p><strong>' . esc_html__( 'Risposta:', 'gest-web-rent' ) . '</strong> ' . esc_html( $row['admin_response'] ) . '</p>'; }
			if ( ! in_array( $row['status'], array( 'approved', 'rejected', 'cancelled' ), true ) ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gwr_customer_request_action" /><input type="hidden" name="request_id" value="' . esc_attr( $row['id'] ) . '" />'; wp_nonce_field( 'gwr_customer_request_action_' . $row['id'] );
				echo '<label><span>' . esc_html__( 'Risposta amministratore', 'gest-web-rent' ) . '</span><textarea name="admin_response" rows="3"></textarea></label><div class="gwr-row-actions"><button class="button" name="request_action" value="reviewing">' . esc_html__( 'Prendi in carico', 'gest-web-rent' ) . '</button><button class="button button-primary" name="request_action" value="approved">' . esc_html__( 'Approva', 'gest-web-rent' ) . '</button><button class="button" name="request_action" value="needs_info">' . esc_html__( 'Richiedi informazioni', 'gest-web-rent' ) . '</button><button class="button" name="request_action" value="rejected">' . esc_html__( 'Rifiuta', 'gest-web-rent' ) . '</button></div></form>';
			}
			echo '</article>';
		}
		echo '</section>';
	}

	private static function booking_by_code( $code ) {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GWR_Bookings::table() . ' WHERE booking_code=%s', $code ) );
		return $id ? GWR_Bookings::get( $id ) : null;
	}

	private static function rate_limit( $action, $max, $ttl ) {
		$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
		$key = 'gwr_customer_' . sanitize_key( $action ) . '_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 );
		$count = absint( get_transient( $key ) );
		if ( $count >= $max ) { return new WP_Error( 'gwr_rate_limit', __( 'Troppe richieste. Riprova piu tardi.', 'gest-web-rent' ) ); }
		set_transient( $key, $count + 1, $ttl );
		return true;
	}

	private static function redirect_portal( $booking, $token, $result, $success ) {
		$url = add_query_arg( array( 'booking_code' => $booking['booking_code'], 'gwr_token' => $token, 'gwr_portal_message' => rawurlencode( is_wp_error( $result ) ? $result->get_error_message() : $success ), 'gwr_portal_error' => is_wp_error( $result ) ? 1 : 0 ), wp_get_referer() ?: home_url( '/prenotazione/' ) );
		wp_safe_redirect( $url ); exit;
	}
}
