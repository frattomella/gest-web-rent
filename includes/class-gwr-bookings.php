<?php
/**
 * Booking storage, pricing, availability and notifications.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/** Central booking domain service. */
class GWR_Bookings {
	const DB_VERSION = '1.9.0';
	const DB_OPTION  = 'gwr_bookings_db_version';

	/** Register runtime hooks. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 6 );
	}

	/** Booking table. */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_bookings';
	}

	/** Booking items table. */
	public static function items_table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_booking_items';
	}

	/** Booking log table. */
	public static function logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_booking_logs';
	}

	/** Idempotent schema migration for existing installations. */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}
		self::create_tables();
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/** Create booking tables. */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$bookings = self::table();
		$items = self::items_table();
		$logs = self::logs_table();

		$sql_bookings = "CREATE TABLE {$bookings} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_code varchar(40) NOT NULL,
			vehicle_id bigint(20) unsigned NOT NULL,
			availability_id bigint(20) unsigned NULL,
			status varchar(30) NOT NULL DEFAULT 'pending',
			pickup_location varchar(190) NOT NULL,
			return_location varchar(190) NOT NULL,
			pickup_datetime datetime NOT NULL,
			return_datetime datetime NOT NULL,
			rental_days smallint unsigned NOT NULL,
			currency char(3) NOT NULL DEFAULT 'EUR',
			base_amount decimal(12,2) NOT NULL DEFAULT 0,
			extras_amount decimal(12,2) NOT NULL DEFAULT 0,
			coverages_amount decimal(12,2) NOT NULL DEFAULT 0,
			supplements_amount decimal(12,2) NOT NULL DEFAULT 0,
			discounts_amount decimal(12,2) NOT NULL DEFAULT 0,
			taxes_amount decimal(12,2) NOT NULL DEFAULT 0,
			total_amount decimal(12,2) NOT NULL DEFAULT 0,
			deposit_amount decimal(12,2) NOT NULL DEFAULT 0,
			pay_now_amount decimal(12,2) NOT NULL DEFAULT 0,
			pay_later_amount decimal(12,2) NOT NULL DEFAULT 0,
			payment_method varchar(40) NOT NULL DEFAULT 'request',
			payment_status varchar(40) NOT NULL DEFAULT 'not_required',
			pricing_snapshot_json longtext NULL,
			public_token_hash varchar(128) NULL,
			public_token_expires_at datetime NULL,
			public_token_created_at datetime NULL,
			public_token_revoked_at datetime NULL,
			customer_type varchar(20) NOT NULL DEFAULT 'private',
			customer_email varchar(190) NOT NULL,
			customer_phone varchar(60) NOT NULL,
			customer_data_json longtext NOT NULL,
			driver_data_json longtext NOT NULL,
			selection_json longtext NULL,
			terms_snapshot_json longtext NOT NULL,
			privacy_version varchar(80) NOT NULL,
			privacy_accepted_at datetime NOT NULL,
			terms_accepted_at datetime NOT NULL,
			marketing_consent tinyint(1) NOT NULL DEFAULT 0,
			third_party_consent tinyint(1) NOT NULL DEFAULT 0,
			customer_notes text NULL,
			admin_notes text NULL,
			delivery_data_json longtext NULL,
			return_data_json longtext NULL,
			rejection_reason text NULL,
			cancellation_reason text NULL,
			expires_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			confirmed_at datetime NULL,
			cancelled_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY booking_code (booking_code),
			KEY vehicle_id (vehicle_id),
			KEY status (status),
			KEY payment_status (payment_status),
			KEY pickup_datetime (pickup_datetime),
			KEY return_datetime (return_datetime),
			KEY customer_email (customer_email),
			KEY public_token_hash (public_token_hash),
			KEY created_at (created_at)
		) {$charset};";

		$sql_items = "CREATE TABLE {$items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			item_type varchar(30) NOT NULL,
			item_id varchar(80) NULL,
			item_name varchar(190) NOT NULL,
			quantity smallint unsigned NOT NULL DEFAULT 1,
			unit_price decimal(12,2) NOT NULL DEFAULT 0,
			pricing_mode varchar(30) NOT NULL,
			days smallint unsigned NOT NULL DEFAULT 1,
			total_price decimal(12,2) NOT NULL DEFAULT 0,
			snapshot_json longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY item_type (item_type),
			KEY item_id (item_id)
		) {$charset};";

		$sql_logs = "CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(80) NOT NULL,
			old_status varchar(30) NULL,
			new_status varchar(30) NULL,
			note text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY action (action),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql_bookings );
		dbDelta( $sql_items );
		dbDelta( $sql_logs );
	}

	/** Public status labels. */
	public static function statuses() {
		return array(
			'pending'      => __( 'In attesa', 'gest-web-rent' ),
			'awaiting_payment' => __( 'In attesa di pagamento', 'gest-web-rent' ),
			'awaiting_bank_transfer' => __( 'In attesa di bonifico', 'gest-web-rent' ),
			'payment_failed' => __( 'Pagamento fallito', 'gest-web-rent' ),
			'confirmed'    => __( 'Confermata', 'gest-web-rent' ),
			'rejected'     => __( 'Rifiutata', 'gest-web-rent' ),
			'cancelled'    => __( 'Annullata', 'gest-web-rent' ),
			'expired'      => __( 'Scaduta', 'gest-web-rent' ),
			'refunded'     => __( 'Rimborsata', 'gest-web-rent' ),
			'delivered'    => __( 'Veicolo consegnato', 'gest-web-rent' ),
			'in_progress'  => __( 'In corso', 'gest-web-rent' ),
			'returned'     => __( 'Riconsegnata', 'gest-web-rent' ),
			'completed'    => __( 'Completata', 'gest-web-rent' ),
			'no_show'      => __( 'No-show', 'gest-web-rent' ),
		);
	}

	/** Allowed state changes. */
	public static function transitions() {
		return array(
			'pending'     => array( 'confirmed', 'rejected', 'cancelled', 'expired', 'awaiting_payment', 'awaiting_bank_transfer' ),
			'awaiting_payment' => array( 'confirmed', 'payment_failed', 'cancelled', 'expired' ),
			'awaiting_bank_transfer' => array( 'confirmed', 'cancelled', 'expired' ),
			'payment_failed' => array( 'awaiting_payment', 'cancelled', 'expired' ),
			'confirmed'   => array( 'cancelled', 'delivered', 'no_show' ),
			'refunded'    => array(),
			'delivered'   => array( 'in_progress', 'returned' ),
			'in_progress' => array( 'returned' ),
			'returned'    => array( 'completed' ),
		);
	}

	/** Statuses that reserve the vehicle. */
	public static function blocking_statuses() {
		return array( 'pending', 'awaiting_payment', 'awaiting_bank_transfer', 'payment_failed', 'confirmed', 'delivered', 'in_progress' );
	}

	/** Validate and create a booking atomically. */
	public static function create( $raw ) {
		global $wpdb;
		$input = self::sanitize_request( $raw );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		$vehicle = GWR_CPT::get_vehicle( $input['vehicle_id'] );
		if ( ! $vehicle || 'active' !== $vehicle['status'] ) {
			return new WP_Error( 'gwr_vehicle_invalid', __( 'Il veicolo selezionato non e disponibile.', 'gest-web-rent' ), array( 'field' => 'vehicle_id' ) );
		}
		$period = self::normalize_period( $input );
		if ( is_wp_error( $period ) ) {
			return $period;
		}
		$terms_payload = GWR_Rental_Terms::public_payload( $input['vehicle_id'], $vehicle );
		$requirements = self::validate_driver( $input['driver'], $period['pickup'], $terms_payload['rental_terms']['driver_requirements'] ?? array() );
		if ( is_wp_error( $requirements ) ) {
			return $requirements;
		}
		$documents = self::validate_documents( $input['document_confirmations'], $terms_payload['rental_terms']['required_documents'] ?? array() );
		if ( is_wp_error( $documents ) ) {
			return $documents;
		}
		$price = self::calculate_price( $vehicle, $period, $input['selection'], $input['driver'], $terms_payload, array( 'coupon_code' => $input['coupon_code'], 'customer_email' => $input['customer']['email'], 'payment_method' => $input['payment_method'] ) );
		if ( is_wp_error( $price ) ) {
			return $price;
		}

		$lock = self::acquire_vehicle_lock( $input['vehicle_id'] );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		$booking_id = 0;
		try {
			$wpdb->query( 'START TRANSACTION' );
			if ( ! self::is_period_available( $input['vehicle_id'], $period['pickup_mysql'], $period['return_mysql'] ) ) {
				throw new Exception( __( 'Il veicolo e appena diventato non disponibile per il periodo scelto.', 'gest-web-rent' ) );
			}
			$settings = GWR_Admin::get_settings();
			$now = current_time( 'mysql' );
			$expiry_hours = max( 1, min( 168, absint( $settings['pending_expiry_hours'] ?? 24 ) ) );
			$provisional = 'TMP-' . wp_generate_uuid4();
			$public_token = self::generate_public_token();
			$token_days = class_exists( 'GWR_Notification_Service' ) ? max( 1, min( 730, absint( GWR_Notification_Service::settings()['portal_token_days'] ?? 365 ) ) ) : 365;
			$initial_status = self::initial_status_for_payment( $input['payment_method'], $price );
			$initial_payment_status = ( ! empty( $price['pay_now_minor'] ) && in_array( $input['payment_method'], array( 'stripe', 'bank_transfer' ), true ) ) ? 'pending' : 'not_required';
			$data = array(
				'booking_code' => $provisional,
				'vehicle_id' => $input['vehicle_id'],
				'status' => $initial_status,
				'pickup_location' => $input['pickup_location'],
				'return_location' => $input['return_location'],
				'pickup_datetime' => $period['pickup_mysql'],
				'return_datetime' => $period['return_mysql'],
				'rental_days' => $period['days'],
				'currency' => $price['currency'],
				'base_amount' => self::minor_to_decimal( $price['base_minor'] ),
				'extras_amount' => self::minor_to_decimal( $price['extras_minor'] ),
				'coverages_amount' => self::minor_to_decimal( $price['coverages_minor'] ),
				'supplements_amount' => self::minor_to_decimal( $price['supplements_minor'] ),
				'discounts_amount' => self::minor_to_decimal( $price['discounts_minor'] ),
				'taxes_amount' => self::minor_to_decimal( $price['taxes_minor'] ),
				'total_amount' => self::minor_to_decimal( $price['total_minor'] ),
				'deposit_amount' => self::minor_to_decimal( $price['deposit_minor'] ),
				'pay_now_amount' => self::minor_to_decimal( $price['pay_now_minor'] ?? 0 ),
				'pay_later_amount' => self::minor_to_decimal( $price['pay_later_minor'] ?? 0 ),
				'payment_method' => $input['payment_method'],
				'payment_status' => $initial_payment_status,
				'pricing_snapshot_json' => wp_json_encode( $price ),
				'public_token_hash' => self::hash_public_token( $public_token ),
				'public_token_expires_at' => ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+' . $token_days . ' days' )->format( 'Y-m-d H:i:s' ),
				'public_token_created_at' => $now,
				'public_token_revoked_at' => null,
				'customer_type' => $input['customer']['customer_type'],
				'customer_email' => $input['customer']['email'],
				'customer_phone' => $input['customer']['phone'],
				'customer_data_json' => wp_json_encode( $input['customer'] ),
				'driver_data_json' => wp_json_encode( $input['driver'] ),
				'selection_json' => wp_json_encode( $input['selection'] ),
				'terms_snapshot_json' => wp_json_encode( self::snapshot( $vehicle, $terms_payload, $price ) ),
				'privacy_version' => sanitize_text_field( $settings['privacy_version'] ?? GWR_VERSION ),
				'privacy_accepted_at' => $now,
				'terms_accepted_at' => $now,
				'marketing_consent' => $input['consents']['marketing'] ? 1 : 0,
				'third_party_consent' => $input['consents']['third_party'] ? 1 : 0,
				'customer_notes' => $input['customer']['notes'],
				'expires_at' => ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+' . $expiry_hours . ' hours' )->format( 'Y-m-d H:i:s' ),
				'created_at' => $now,
				'updated_at' => $now,
			);
			if ( false === $wpdb->insert( self::table(), $data ) ) {
				throw new Exception( __( 'Impossibile salvare la prenotazione.', 'gest-web-rent' ) );
			}
			$booking_id = (int) $wpdb->insert_id;
			$code = self::generate_code( $booking_id, $settings['booking_prefix'] ?? 'GWR' );
			if ( false === $wpdb->update( self::table(), array( 'booking_code' => $code ), array( 'id' => $booking_id ), array( '%s' ), array( '%d' ) ) ) {
				throw new Exception( __( 'Impossibile generare il codice prenotazione.', 'gest-web-rent' ) );
			}
			self::insert_items( $booking_id, $price['items'] );
			$availability_id = self::create_availability_block( $booking_id, $code, $input['vehicle_id'], $period );
			if ( ! $availability_id ) {
				throw new Exception( __( 'Impossibile bloccare il periodo selezionato.', 'gest-web-rent' ) );
			}
			$wpdb->update( self::table(), array( 'availability_id' => $availability_id ), array( 'id' => $booking_id ), array( '%d' ), array( '%d' ) );
			if ( ! empty( $price['coupon_id'] ) ) {
				GWR_Coupon_Service::reserve_usage( $price['coupon_id'], $booking_id, $input['customer']['email'], absint( $price['coupon']['amount_minor'] ?? 0 ) );
			}
			self::add_log( $booking_id, 'created', '', $initial_status, __( 'Prenotazione creata dal catalogo.', 'gest-web-rent' ) );
			$wpdb->query( 'COMMIT' );
		} catch ( Exception $exception ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_vehicle_lock( $input['vehicle_id'] );
			return new WP_Error( 'gwr_booking_failed', $exception->getMessage() );
		}
		self::release_vehicle_lock( $input['vehicle_id'] );
		$booking = self::get( $booking_id );
		$booking['public_token'] = $public_token;
		self::send_notifications( $booking );

		return $booking;
	}

	/** Convert a reviewed lightweight request into a real pending booking. */
	public static function create_from_inquiry( $inquiry ) {
		global $wpdb;
		$inquiry = is_array( $inquiry ) ? $inquiry : array();
		$vehicle = GWR_CPT::get_vehicle( absint( $inquiry['vehicle_id'] ?? 0 ) );
		if ( ! $vehicle || 'active' !== ( $vehicle['status'] ?? '' ) ) {
			return new WP_Error( 'gwr_vehicle_invalid', __( 'Il veicolo selezionato non e disponibile.', 'gest-web-rent' ) );
		}
		$settings = GWR_Admin::get_settings();
		$location = sanitize_text_field( $vehicle['location'] ?? '' );
		if ( ! $location ) {
			$location = sanitize_text_field( $settings['dealer_name'] ?? '' );
		}
		if ( ! $location ) {
			$location = __( 'Da definire', 'gest-web-rent' );
		}
		$period = self::normalize_period(
			array(
				'start_date'      => sanitize_text_field( $inquiry['start_date'] ?? '' ),
				'end_date'        => sanitize_text_field( $inquiry['end_date'] ?? '' ),
				'pickup_time'     => sanitize_text_field( ! empty( $inquiry['pickup_time'] ) ? $inquiry['pickup_time'] : '09:00' ),
				'return_time'     => sanitize_text_field( ! empty( $inquiry['return_time'] ) ? $inquiry['return_time'] : '18:00' ),
				'pickup_location' => $location,
				'return_location' => $location,
			)
		);
		if ( is_wp_error( $period ) ) {
			return $period;
		}
		$selection = json_decode( (string) ( $inquiry['selection_json'] ?? '' ), true );
		$selection = is_array( $selection ) ? array_replace( array( 'extras' => array(), 'coverages' => array() ), $selection ) : array( 'extras' => array(), 'coverages' => array() );
		$terms     = GWR_Rental_Terms::public_payload( absint( $vehicle['id'] ), $vehicle );
		$price     = self::calculate_price( $vehicle, $period, $selection, array(), $terms, array( 'customer_email' => sanitize_email( $inquiry['email'] ?? '' ), 'payment_method' => 'request' ) );
		if ( is_wp_error( $price ) ) {
			return $price;
		}

		$vehicle_id     = absint( $vehicle['id'] );
		$availability_id = absint( $inquiry['availability_id'] ?? 0 );
		$lock           = self::acquire_vehicle_lock( $vehicle_id );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		$booking_id   = 0;
		$public_token = self::generate_public_token();
		try {
			$wpdb->query( 'START TRANSACTION' );
			$manual_sql  = 'SELECT COUNT(*) FROM ' . GWR_CPT::availability_table() . ' WHERE vehicle_id=%d AND start_date <= %s AND end_date >= %s';
			$manual_args = array( $vehicle_id, substr( $period['return_mysql'], 0, 10 ), substr( $period['pickup_mysql'], 0, 10 ) );
			if ( $availability_id ) {
				$manual_sql   .= ' AND id <> %d';
				$manual_args[] = $availability_id;
			}
			$statuses     = self::blocking_statuses();
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$booking_sql  = 'SELECT COUNT(*) FROM ' . self::table() . " WHERE vehicle_id=%d AND status IN ({$placeholders}) AND pickup_datetime < %s AND return_datetime > %s";
			$booking_args = array_merge( array( $vehicle_id ), $statuses, array( $period['return_mysql'], $period['pickup_mysql'] ) );
			if ( (int) $wpdb->get_var( $wpdb->prepare( $manual_sql, $manual_args ) ) || (int) $wpdb->get_var( $wpdb->prepare( $booking_sql, $booking_args ) ) ) {
				throw new Exception( __( 'Il veicolo non e piu disponibile nel periodo richiesto.', 'gest-web-rent' ) );
			}

			$now        = current_time( 'mysql' );
			$expiry     = max( 1, min( 168, absint( $settings['pending_expiry_hours'] ?? 24 ) ) );
			$token_days = class_exists( 'GWR_Notification_Service' ) ? max( 1, min( 730, absint( GWR_Notification_Service::settings()['portal_token_days'] ?? 365 ) ) ) : 365;
			$customer   = array(
				'customer_type' => 'company' === ( $inquiry['customer_type'] ?? '' ) ? 'company' : 'private',
				'first_name'    => sanitize_text_field( $inquiry['first_name'] ?? '' ),
				'last_name'     => sanitize_text_field( $inquiry['last_name'] ?? '' ),
				'email'         => sanitize_email( $inquiry['email'] ?? '' ),
				'phone'         => sanitize_text_field( $inquiry['phone'] ?? '' ),
				'notes'         => sanitize_textarea_field( $inquiry['message'] ?? '' ),
			);
			$data = array(
				'booking_code'            => 'TMP-' . wp_generate_uuid4(),
				'vehicle_id'              => $vehicle_id,
				'availability_id'         => $availability_id ?: null,
				'status'                  => 'pending',
				'pickup_location'         => $location,
				'return_location'         => $location,
				'pickup_datetime'         => $period['pickup_mysql'],
				'return_datetime'         => $period['return_mysql'],
				'rental_days'             => $period['days'],
				'currency'                => $price['currency'],
				'base_amount'             => self::minor_to_decimal( $price['base_minor'] ),
				'extras_amount'           => self::minor_to_decimal( $price['extras_minor'] ),
				'coverages_amount'        => self::minor_to_decimal( $price['coverages_minor'] ),
				'supplements_amount'      => self::minor_to_decimal( $price['supplements_minor'] ),
				'discounts_amount'        => self::minor_to_decimal( $price['discounts_minor'] ),
				'taxes_amount'            => self::minor_to_decimal( $price['taxes_minor'] ),
				'total_amount'            => self::minor_to_decimal( $price['total_minor'] ),
				'deposit_amount'          => self::minor_to_decimal( $price['deposit_minor'] ),
				'pay_now_amount'          => 0,
				'pay_later_amount'        => self::minor_to_decimal( $price['total_minor'] ),
				'payment_method'          => 'request',
				'payment_status'          => 'not_required',
				'pricing_snapshot_json'   => wp_json_encode( $price ),
				'public_token_hash'       => self::hash_public_token( $public_token ),
				'public_token_expires_at' => ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+' . $token_days . ' days' )->format( 'Y-m-d H:i:s' ),
				'public_token_created_at' => $now,
				'customer_type'           => $customer['customer_type'],
				'customer_email'          => $customer['email'],
				'customer_phone'          => $customer['phone'],
				'customer_data_json'      => wp_json_encode( $customer ),
				'driver_data_json'        => wp_json_encode( array() ),
				'selection_json'          => wp_json_encode( $selection ),
				'terms_snapshot_json'     => wp_json_encode( self::snapshot( $vehicle, $terms, $price ) ),
				'privacy_version'         => sanitize_text_field( $settings['privacy_version'] ?? GWR_VERSION ),
				'privacy_accepted_at'     => sanitize_text_field( $inquiry['privacy_accepted_at'] ?? $now ),
				'terms_accepted_at'       => sanitize_text_field( $inquiry['terms_accepted_at'] ?? $now ),
				'customer_notes'          => $customer['notes'],
				'expires_at'              => ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+' . $expiry . ' hours' )->format( 'Y-m-d H:i:s' ),
				'created_at'              => $now,
				'updated_at'              => $now,
			);
			if ( false === $wpdb->insert( self::table(), $data ) ) {
				throw new Exception( __( 'Impossibile creare la prenotazione dalla richiesta.', 'gest-web-rent' ) );
			}
			$booking_id = (int) $wpdb->insert_id;
			$code       = self::generate_code( $booking_id, $settings['booking_prefix'] ?? 'GWR' );
			if ( false === $wpdb->update( self::table(), array( 'booking_code' => $code ), array( 'id' => $booking_id ), array( '%s' ), array( '%d' ) ) ) {
				throw new Exception( __( 'Impossibile generare il codice prenotazione.', 'gest-web-rent' ) );
			}
			self::insert_items( $booking_id, $price['items'] );
			if ( $availability_id ) {
				$saved_availability = GWR_CPT::save_availability( $vehicle_id, array( 'start_date' => substr( $period['pickup_mysql'], 0, 10 ), 'end_date' => substr( $period['return_mysql'], 0, 10 ), 'status' => 'reserved', 'internal_note' => __( 'Blocco collegato a prenotazione.', 'gest-web-rent' ), 'external_reference' => 'booking:' . $code ), $availability_id );
				if ( is_wp_error( $saved_availability ) ) {
					throw new Exception( $saved_availability->get_error_message() );
				}
			} else {
				$availability_id = self::create_availability_block( $booking_id, $code, $vehicle_id, $period );
				if ( $availability_id && false === $wpdb->update( self::table(), array( 'availability_id' => $availability_id ), array( 'id' => $booking_id ), array( '%d' ), array( '%d' ) ) ) {
					throw new Exception( __( 'Impossibile collegare il blocco disponibilita.', 'gest-web-rent' ) );
				}
			}
			if ( ! $availability_id ) {
				throw new Exception( __( 'Impossibile bloccare il periodo selezionato.', 'gest-web-rent' ) );
			}
			self::add_log( $booking_id, 'converted_from_inquiry', '', 'pending', __( 'Prenotazione creata da richiesta cliente.', 'gest-web-rent' ) );
			$wpdb->query( 'COMMIT' );
		} catch ( Exception $exception ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_vehicle_lock( $vehicle_id );
			return new WP_Error( 'gwr_inquiry_conversion_failed', $exception->getMessage() );
		}
		self::release_vehicle_lock( $vehicle_id );
		$booking                 = self::get( $booking_id );
		$booking['public_token'] = $public_token;
		self::send_notifications( $booking );
		return $booking;
	}

	/** Sanitize booking request and required consents. */
	public static function sanitize_request( $raw ) {
		$raw = is_array( $raw ) ? wp_unslash( $raw ) : array();
		$customer = self::sanitize_customer( $raw['customer'] ?? array() );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}
		$driver_same = ! empty( $raw['driver_same'] );
		$driver = $driver_same ? self::driver_from_customer( $customer, $raw['driver'] ?? array() ) : self::sanitize_driver( $raw['driver'] ?? array() );
		if ( is_wp_error( $driver ) ) {
			return $driver;
		}
		$consents = array(
			'privacy' => ! empty( $raw['consents']['privacy'] ),
			'terms' => ! empty( $raw['consents']['terms'] ),
			'cancellation' => ! empty( $raw['consents']['cancellation'] ),
			'marketing' => ! empty( $raw['consents']['marketing'] ),
			'third_party' => ! empty( $raw['consents']['third_party'] ),
		);
		if ( ! $consents['privacy'] || ! $consents['terms'] ) {
			return new WP_Error( 'gwr_consents_required', __( 'Accetta privacy e condizioni generali per continuare.', 'gest-web-rent' ), array( 'field' => 'consents' ) );
		}

		$selection = array( 'extras' => array(), 'coverages' => array() );
		foreach ( is_array( $raw['selection']['extras'] ?? null ) ? $raw['selection']['extras'] : array() as $extra ) {
			$id = sanitize_key( $extra['id'] ?? '' );
			if ( $id ) {
				$selection['extras'][] = array( 'id' => $id, 'quantity' => max( 1, absint( $extra['quantity'] ?? 1 ) ) );
			}
		}
		foreach ( is_array( $raw['selection']['coverages'] ?? null ) ? $raw['selection']['coverages'] : array() as $coverage ) {
			$id = sanitize_key( is_array( $coverage ) ? ( $coverage['id'] ?? '' ) : $coverage );
			if ( $id ) {
				$selection['coverages'][] = $id;
			}
		}

		return array(
			'vehicle_id' => absint( $raw['vehicle_id'] ?? 0 ),
			'pickup_location' => sanitize_text_field( $raw['pickup_location'] ?? '' ),
			'return_location' => sanitize_text_field( $raw['return_location'] ?? '' ),
			'start_date' => sanitize_text_field( $raw['start_date'] ?? '' ),
			'end_date' => sanitize_text_field( $raw['end_date'] ?? '' ),
			'pickup_time' => sanitize_text_field( $raw['pickup_time'] ?? '' ),
			'return_time' => sanitize_text_field( $raw['return_time'] ?? '' ),
			'customer' => $customer,
			'driver' => $driver,
			'driver_same' => $driver_same,
			'selection' => $selection,
			'coupon_code' => sanitize_text_field( $raw['coupon_code'] ?? '' ),
			'payment_method' => self::sanitize_payment_method( $raw['payment_method'] ?? '' ),
			'document_confirmations' => array_values( array_filter( array_map( 'sanitize_key', is_array( $raw['document_confirmations'] ?? null ) ? $raw['document_confirmations'] : array() ) ) ),
			'consents' => $consents,
		);
	}

	/** Calculate all trusted prices in integer minor units. */
	public static function calculate_price( $vehicle, $period, $selection, $driver, $terms_payload, $context = array() ) {
		return GWR_Pricing_Service::calculate( $vehicle, $period, $selection, $driver, $terms_payload, $context );
	}

	/** Verify availability against manual blocks and blocking bookings. */
	public static function is_period_available( $vehicle_id, $pickup_mysql, $return_mysql, $exclude_booking_id = 0 ) {
		global $wpdb;
		$pickup_date = substr( $pickup_mysql, 0, 10 );
		$return_date = substr( $return_mysql, 0, 10 );
		$manual = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . GWR_CPT::availability_table() . " WHERE vehicle_id = %d AND start_date <= %s AND end_date >= %s AND (external_reference IS NULL OR external_reference NOT LIKE 'booking:%%')", $vehicle_id, $return_date, $pickup_date ) );
		if ( $manual ) {
			return false;
		}
		$statuses = self::blocking_statuses();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$sql = 'SELECT COUNT(*) FROM ' . self::table() . " WHERE vehicle_id = %d AND status IN ({$placeholders}) AND pickup_datetime < %s AND return_datetime > %s";
		$args = array_merge( array( $vehicle_id ), $statuses, array( $return_mysql, $pickup_mysql ) );
		if ( $exclude_booking_id ) {
			$sql .= ' AND id <> %d';
			$args[] = absint( $exclude_booking_id );
		}
		return 0 === (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
	}

	/** Change status while enforcing transitions and availability synchronization. */
	public static function change_status( $booking_id, $new_status, $note = '', $operation = array() ) {
		global $wpdb;
		$booking = self::get( $booking_id );
		$new_status = sanitize_key( $new_status );
		if ( ! $booking || ! in_array( $new_status, self::transitions()[ $booking['status'] ] ?? array(), true ) ) {
			return new WP_Error( 'gwr_transition_invalid', __( 'Transizione di stato non consentita.', 'gest-web-rent' ) );
		}
		if ( in_array( $new_status, array( 'cancelled', 'rejected' ), true ) && '' === trim( (string) $note ) ) {
			return new WP_Error( 'gwr_transition_reason', __( 'Inserisci una motivazione per annullamento o rifiuto.', 'gest-web-rent' ) );
		}
		if ( in_array( $new_status, array( 'delivered', 'returned' ), true ) && empty( $operation['effective_datetime'] ) ) {
			return new WP_Error( 'gwr_operation_datetime', __( 'Inserisci data e ora effettiva per consegna o riconsegna.', 'gest-web-rent' ) );
		}
		$locked_vehicle_id = absint( $booking['vehicle_id'] );
		$lock = self::acquire_vehicle_lock( $locked_vehicle_id );
		if ( is_wp_error( $lock ) ) { return $lock; }
		$booking = self::get( $booking_id );
		if ( ! $booking || ! in_array( $new_status, self::transitions()[ $booking['status'] ] ?? array(), true ) ) {
			self::release_vehicle_lock( $locked_vehicle_id );
			return new WP_Error( 'gwr_transition_stale', __( 'Lo stato e cambiato nel frattempo. Ricarica la pagina.', 'gest-web-rent' ) );
		}
		$update = array( 'status' => $new_status, 'updated_at' => current_time( 'mysql' ) );
		if ( 'confirmed' === $new_status ) {
			$update['confirmed_at'] = current_time( 'mysql' );
		}
		if ( 'cancelled' === $new_status ) {
			$update['cancelled_at'] = current_time( 'mysql' );
			$update['cancellation_reason'] = sanitize_textarea_field( $note );
		}
		if ( 'rejected' === $new_status ) {
			$update['rejection_reason'] = sanitize_textarea_field( $note );
		}
		if ( 'delivered' === $new_status ) {
			$update['delivery_data_json'] = wp_json_encode( self::sanitize_operation( $operation ) );
		}
		if ( 'returned' === $new_status ) {
			$update['return_data_json'] = wp_json_encode( self::sanitize_operation( $operation ) );
		}
		$wpdb->update( self::table(), $update, array( 'id' => absint( $booking_id ) ) );
		if ( in_array( $new_status, self::blocking_statuses(), true ) ) {
			self::ensure_availability_block( array_merge( $booking, $update ) );
		} else {
			self::remove_availability_block( $booking );
		}
		if ( 'confirmed' === $new_status && class_exists( 'GWR_Coupon_Service' ) ) {
			GWR_Coupon_Service::mark_used_by_booking( $booking_id );
		}
		if ( in_array( $new_status, array( 'cancelled', 'rejected', 'expired' ), true ) && class_exists( 'GWR_Coupon_Service' ) ) {
			GWR_Coupon_Service::release_by_booking( $booking_id );
		}
		self::add_log( $booking_id, 'status_changed', $booking['status'], $new_status, $note );
		self::release_vehicle_lock( $booking['vehicle_id'] );
		$updated = self::get( $booking_id );
		self::send_status_notification( $updated );
		return $updated;
	}

	/** Controlled admin update for vehicle, period, contacts and notes. */
	public static function update_admin( $booking_id, $raw ) {
		global $wpdb;
		$booking = self::get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'gwr_booking_missing', __( 'Prenotazione non trovata.', 'gest-web-rent' ) );
		}
		$raw = is_array( $raw ) ? wp_unslash( $raw ) : array();
		$vehicle_id = absint( $raw['vehicle_id'] ?? $booking['vehicle_id'] );
		$vehicle = GWR_CPT::get_vehicle( $vehicle_id );
		if ( ! $vehicle ) {
			return new WP_Error( 'gwr_vehicle_invalid', __( 'Veicolo non valido.', 'gest-web-rent' ) );
		}
		$input = array(
			'start_date' => sanitize_text_field( $raw['start_date'] ?? substr( $booking['pickup_datetime'], 0, 10 ) ),
			'end_date' => sanitize_text_field( $raw['end_date'] ?? substr( $booking['return_datetime'], 0, 10 ) ),
			'pickup_time' => sanitize_text_field( $raw['pickup_time'] ?? substr( $booking['pickup_datetime'], 11, 5 ) ),
			'return_time' => sanitize_text_field( $raw['return_time'] ?? substr( $booking['return_datetime'], 11, 5 ) ),
			'pickup_location' => sanitize_text_field( $raw['pickup_location'] ?? $booking['pickup_location'] ),
			'return_location' => sanitize_text_field( $raw['return_location'] ?? $booking['return_location'] ),
		);
		$period = self::normalize_period( $input );
		if ( is_wp_error( $period ) ) { return $period; }
		$terms = GWR_Rental_Terms::public_payload( $vehicle_id, $vehicle );
		$customer = $booking['customer'];
		foreach ( array( 'first_name', 'last_name', 'email', 'phone' ) as $key ) {
			if ( isset( $raw[ 'customer_' . $key ] ) ) { $customer[ $key ] = 'email' === $key ? sanitize_email( $raw[ 'customer_' . $key ] ) : ( 'phone' === $key ? preg_replace( '/[^0-9+ ]/', '', sanitize_text_field( $raw[ 'customer_' . $key ] ) ) : sanitize_text_field( $raw[ 'customer_' . $key ] ) ); }
		}
		$driver = $booking['driver'];
		foreach ( array( 'first_name', 'last_name', 'birth_date', 'license_type', 'license_number', 'license_country', 'license_issue_date', 'license_expiry_date' ) as $key ) {
			if ( isset( $raw[ 'driver_' . $key ] ) ) { $driver[ $key ] = sanitize_text_field( $raw[ 'driver_' . $key ] ); }
		}
		$driver_check = self::validate_driver( $driver, $period['pickup'], $terms['rental_terms']['driver_requirements'] ?? array() );
		if ( is_wp_error( $driver_check ) ) { return $driver_check; }
		$selection = $booking['selection'];
		if ( ! empty( $raw['selection_present'] ) ) {
			$selection = array( 'extras' => array(), 'coverages' => array() );
			foreach ( is_array( $raw['selection']['extras'] ?? null ) ? $raw['selection']['extras'] : array() as $id => $row ) {
				if ( ! empty( $row['selected'] ) ) { $selection['extras'][] = array( 'id' => sanitize_key( $id ), 'quantity' => max( 1, absint( $row['quantity'] ?? 1 ) ) ); }
			}
			foreach ( is_array( $raw['selection']['coverages'] ?? null ) ? $raw['selection']['coverages'] : array() as $id ) { $id = sanitize_key( $id ); if ( $id ) { $selection['coverages'][] = $id; } }
		}
		$coupon_code = $booking['pricing_snapshot']['coupon_code'] ?? ( $booking['pricing_snapshot']['coupon']['code'] ?? '' );
		$price = self::calculate_price( $vehicle, $period, $selection, $driver, $terms, array( 'coupon_code' => $coupon_code, 'customer_email' => $customer['email'], 'payment_method' => $booking['payment_method'] ?? 'request' ) );
		if ( is_wp_error( $price ) ) { return $price; }
		if ( 'paid' === ( $booking['payment_status'] ?? '' ) && (int) round( (float) $booking['total_amount'] * 100 ) !== (int) $price['total_minor'] ) {
			return new WP_Error( 'gwr_paid_booking_price_locked', __( 'Il pagamento e gia riuscito: usa un rimborso o un addebito aggiuntivo invece di modificare il totale storico.', 'gest-web-rent' ) );
		}

		$lock_ids = array_values( array_unique( array( absint( $booking['vehicle_id'] ), $vehicle_id ) ) );
		sort( $lock_ids );
		foreach ( $lock_ids as $lock_id ) {
			$lock = self::acquire_vehicle_lock( $lock_id );
			if ( is_wp_error( $lock ) ) {
				foreach ( $lock_ids as $release_id ) { if ( $release_id === $lock_id ) { break; } self::release_vehicle_lock( $release_id ); }
				return $lock;
			}
		}
		try {
			$wpdb->query( 'START TRANSACTION' );
			if ( in_array( $booking['status'], self::blocking_statuses(), true ) && ! self::is_period_available( $vehicle_id, $period['pickup_mysql'], $period['return_mysql'], $booking_id ) ) {
				throw new Exception( __( 'Il nuovo periodo e in conflitto con una disponibilita esistente.', 'gest-web-rent' ) );
			}
			if ( ! $customer['first_name'] || ! $customer['last_name'] || ! is_email( $customer['email'] ) || strlen( preg_replace( '/\D/', '', $customer['phone'] ) ) < 7 ) {
				throw new Exception( __( 'Email o telefono cliente non validi.', 'gest-web-rent' ) );
			}
			$update = array(
				'vehicle_id' => $vehicle_id, 'pickup_location' => $input['pickup_location'], 'return_location' => $input['return_location'],
				'pickup_datetime' => $period['pickup_mysql'], 'return_datetime' => $period['return_mysql'], 'rental_days' => $period['days'], 'currency' => $price['currency'],
				'base_amount' => self::minor_to_decimal( $price['base_minor'] ), 'extras_amount' => self::minor_to_decimal( $price['extras_minor'] ), 'coverages_amount' => self::minor_to_decimal( $price['coverages_minor'] ), 'supplements_amount' => self::minor_to_decimal( $price['supplements_minor'] ), 'discounts_amount' => self::minor_to_decimal( $price['discounts_minor'] ), 'taxes_amount' => self::minor_to_decimal( $price['taxes_minor'] ), 'total_amount' => self::minor_to_decimal( $price['total_minor'] ), 'deposit_amount' => self::minor_to_decimal( $price['deposit_minor'] ),
				'pay_now_amount' => self::minor_to_decimal( $price['pay_now_minor'] ?? 0 ), 'pay_later_amount' => self::minor_to_decimal( $price['pay_later_minor'] ?? 0 ), 'pricing_snapshot_json' => wp_json_encode( $price ), 'terms_snapshot_json' => wp_json_encode( self::snapshot( $vehicle, $terms, $price ) ),
				'customer_email' => $customer['email'], 'customer_phone' => $customer['phone'], 'customer_data_json' => wp_json_encode( $customer ), 'driver_data_json' => wp_json_encode( $driver ), 'selection_json' => wp_json_encode( $selection ),
				'admin_notes' => sanitize_textarea_field( $raw['admin_notes'] ?? $booking['admin_notes'] ), 'updated_at' => current_time( 'mysql' ),
			);
			if ( false === $wpdb->update( self::table(), $update, array( 'id' => absint( $booking_id ) ) ) ) { throw new Exception( __( 'Impossibile aggiornare la prenotazione.', 'gest-web-rent' ) ); }
			$wpdb->delete( self::items_table(), array( 'booking_id' => absint( $booking_id ) ), array( '%d' ) );
			self::insert_items( $booking_id, $price['items'] );
			if ( ! empty( $booking['availability_id'] ) ) { $wpdb->delete( GWR_CPT::availability_table(), array( 'id' => absint( $booking['availability_id'] ) ), array( '%d' ) ); }
			$availability_id = null;
			if ( in_array( $booking['status'], self::blocking_statuses(), true ) ) {
				$availability_id = self::create_availability_block( $booking_id, $booking['booking_code'], $vehicle_id, $period );
				if ( ! $availability_id ) { throw new Exception( __( 'Impossibile aggiornare il blocco disponibilita.', 'gest-web-rent' ) ); }
			}
			$wpdb->update( self::table(), array( 'availability_id' => $availability_id ), array( 'id' => absint( $booking_id ) ) );
			self::add_log( $booking_id, 'booking_updated', $booking['status'], $booking['status'], __( 'Veicolo, periodo, contatti o note aggiornati.', 'gest-web-rent' ) );
			if ( (int) round( (float) $booking['total_amount'] * 100 ) !== (int) $price['total_minor'] ) {
				self::add_log( $booking_id, 'price_changed', $booking['status'], $booking['status'], sprintf( __( 'Totale modificato da %1$s a %2$s.', 'gest-web-rent' ), self::format_money( $booking['total_amount'], $booking['currency'] ), self::format_money( self::minor_to_decimal( $price['total_minor'] ), $price['currency'] ) ) );
			}
			$wpdb->query( 'COMMIT' );
		} catch ( Exception $exception ) {
			$wpdb->query( 'ROLLBACK' );
			foreach ( $lock_ids as $lock_id ) { self::release_vehicle_lock( $lock_id ); }
			return new WP_Error( 'gwr_booking_update_failed', $exception->getMessage() );
		}
		foreach ( $lock_ids as $lock_id ) { self::release_vehicle_lock( $lock_id ); }
		return self::get( $booking_id );
	}

	/** Get one booking with decoded snapshots. */
	public static function get( $booking_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT b.*, v.title AS vehicle_title, v.brand, v.model FROM ' . self::table() . ' b LEFT JOIN ' . GWR_CPT::vehicles_table() . ' v ON v.id=b.vehicle_id WHERE b.id=%d', absint( $booking_id ) ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		foreach ( array( 'customer_data_json' => 'customer', 'driver_data_json' => 'driver', 'selection_json' => 'selection', 'terms_snapshot_json' => 'snapshot', 'pricing_snapshot_json' => 'pricing_snapshot', 'delivery_data_json' => 'delivery', 'return_data_json' => 'return' ) as $column => $key ) {
			$value = json_decode( (string) ( $row[ $column ] ?? '' ), true );
			$row[ $key ] = is_array( $value ) ? $value : array();
		}
		return $row;
	}

	/** Paginated admin query. */
	public static function query( $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array( 'search' => '', 'status' => '', 'vehicle_id' => 0, 'pickup_from' => '', 'pickup_to' => '', 'return_from' => '', 'created_from' => '', 'min_amount' => '', 'page' => 1, 'per_page' => 20, 'active' => false, 'expired' => false ) );
		$where = array( '1=1' );
		$values = array();
		if ( $args['search'] ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(b.booking_code LIKE %s OR b.customer_email LIKE %s OR b.customer_phone LIKE %s OR b.customer_data_json LIKE %s)';
			array_push( $values, $like, $like, $like, $like );
		}
		if ( isset( self::statuses()[ $args['status'] ] ) ) { $where[] = 'b.status=%s'; $values[] = $args['status']; }
		if ( absint( $args['vehicle_id'] ) ) { $where[] = 'b.vehicle_id=%d'; $values[] = absint( $args['vehicle_id'] ); }
		if ( $args['pickup_from'] ) { $where[] = 'b.pickup_datetime >= %s'; $values[] = sanitize_text_field( $args['pickup_from'] ) . ' 00:00:00'; }
		if ( $args['pickup_to'] ) { $where[] = 'b.pickup_datetime <= %s'; $values[] = sanitize_text_field( $args['pickup_to'] ) . ' 23:59:59'; }
		if ( $args['return_from'] ) { $where[] = 'b.return_datetime >= %s'; $values[] = sanitize_text_field( $args['return_from'] ) . ' 00:00:00'; }
		if ( $args['created_from'] ) { $where[] = 'b.created_at >= %s'; $values[] = sanitize_text_field( $args['created_from'] ) . ' 00:00:00'; }
		if ( '' !== (string) $args['min_amount'] ) { $where[] = 'b.total_amount >= %f'; $values[] = max( 0, (float) str_replace( ',', '.', $args['min_amount'] ) ); }
		if ( $args['active'] && ! $args['expired'] ) { $where[] = "b.status IN ('pending','awaiting_payment','awaiting_bank_transfer','payment_failed','confirmed','delivered','in_progress')"; }
		if ( $args['expired'] ) { $where[] = "b.status='expired'"; }
		$where_sql = implode( ' AND ', $where );
		$base = ' FROM ' . self::table() . ' b LEFT JOIN ' . GWR_CPT::vehicles_table() . ' v ON v.id=b.vehicle_id WHERE ' . $where_sql;
		$count_sql = 'SELECT COUNT(*)' . $base;
		if ( $values ) { $count_sql = $wpdb->prepare( $count_sql, $values ); }
		$total = (int) $wpdb->get_var( $count_sql );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ) ) );
		$offset = ( max( 1, absint( $args['page'] ) ) - 1 ) * $per_page;
		$list_sql = 'SELECT b.*, v.title AS vehicle_title' . $base . ' ORDER BY b.created_at DESC LIMIT %d OFFSET %d';
		$list_values = array_merge( $values, array( $per_page, $offset ) );
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_values ), ARRAY_A );
		return array( 'items' => is_array( $rows ) ? $rows : array(), 'total' => $total, 'pages' => (int) ceil( $total / $per_page ) );
	}

	/** Booking items. */
	public static function items( $booking_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::items_table() . ' WHERE booking_id=%d ORDER BY id ASC', absint( $booking_id ) ), ARRAY_A );
	}

	/** Booking history. */
	public static function logs( $booking_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT l.*, u.display_name FROM ' . self::logs_table() . ' l LEFT JOIN ' . $wpdb->users . ' u ON u.ID=l.user_id WHERE booking_id=%d ORDER BY l.created_at DESC, l.id DESC', absint( $booking_id ) ), ARRAY_A );
	}

	/**
	 * Append a system log entry.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $action Action.
	 * @param string $note Note.
	 * @return void
	 */
	public static function add_system_log( $booking_id, $action, $note = '' ) {
		$booking = self::get( $booking_id );
		self::add_log( $booking_id, $action, $booking['status'] ?? '', $booking['status'] ?? '', $note );
	}

	/**
	 * Synchronize booking fields from the payment service.
	 *
	 * @param int         $booking_id Booking ID.
	 * @param string      $payment_status Payment status.
	 * @param string      $payment_method Payment method.
	 * @param string|null $booking_status Optional booking status.
	 * @param string      $note Log note.
	 * @return bool
	 */
	public static function sync_payment_status( $booking_id, $payment_status, $payment_method, $booking_status = null, $note = '' ) {
		global $wpdb;
		$booking = self::get( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		$new_booking_status = null === $booking_status ? $booking['status'] : sanitize_key( $booking_status );
		$update = array(
			'payment_status' => sanitize_key( $payment_status ),
			'payment_method' => sanitize_key( $payment_method ),
			'status'         => $new_booking_status,
			'updated_at'     => current_time( 'mysql' ),
		);
		if ( 'confirmed' === $new_booking_status && empty( $booking['confirmed_at'] ) ) {
			$update['confirmed_at'] = current_time( 'mysql' );
		}
		if ( 'cancelled' === $new_booking_status || 'expired' === $new_booking_status || 'refunded' === $new_booking_status ) {
			$update['cancelled_at'] = 'refunded' === $new_booking_status ? null : current_time( 'mysql' );
		}
		$wpdb->update( self::table(), $update, array( 'id' => absint( $booking_id ) ) );

		$updated = array_merge( $booking, $update );
		if ( in_array( $new_booking_status, self::blocking_statuses(), true ) ) {
			self::ensure_availability_block( $updated );
		} else {
			self::remove_availability_block( $booking );
		}

		self::add_log( $booking_id, 'payment_status_changed', $booking['status'], $new_booking_status, $note );
		return true;
	}

	/**
	 * Verify a public lookup token without exposing incremental IDs.
	 *
	 * @param array|int $booking Booking row or ID.
	 * @param string    $token Public token.
	 * @return bool
	 */
	public static function verify_public_token( $booking, $token ) {
		$booking = is_array( $booking ) ? $booking : self::get( absint( $booking ) );
		if ( ! $booking || empty( $booking['public_token_hash'] ) || ! empty( $booking['public_token_revoked_at'] ) || empty( $token ) ) {
			return false;
		}
		if ( ! empty( $booking['public_token_expires_at'] ) && $booking['public_token_expires_at'] < current_time( 'mysql' ) ) {
			return false;
		}
		return hash_equals( $booking['public_token_hash'], self::hash_public_token( $token ) );
	}

	/** Rotate a customer token, storing only its HMAC. */
	public static function rotate_public_token( $booking_id ) {
		global $wpdb;
		$booking = self::get( absint( $booking_id ) );
		if ( ! $booking ) {
			return new WP_Error( 'gwr_booking_missing', __( 'Prenotazione non trovata.', 'gest-web-rent' ) );
		}
		$token = self::generate_public_token();
		$days = class_exists( 'GWR_Notification_Service' ) ? max( 1, min( 730, absint( GWR_Notification_Service::settings()['portal_token_days'] ?? 365 ) ) ) : 365;
		$updated = $wpdb->update( self::table(), array(
			'public_token_hash' => self::hash_public_token( $token ),
			'public_token_created_at' => current_time( 'mysql' ),
			'public_token_expires_at' => ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+' . $days . ' days' )->format( 'Y-m-d H:i:s' ),
			'public_token_revoked_at' => null,
			'updated_at' => current_time( 'mysql' ),
		), array( 'id' => absint( $booking_id ) ) );
		if ( false === $updated ) {
			return new WP_Error( 'gwr_token_rotation_failed', __( 'Impossibile generare il link sicuro.', 'gest-web-rent' ) );
		}
		self::add_system_log( $booking_id, 'public_token_rotated', __( 'Link cliente rigenerato.', 'gest-web-rent' ) );
		return $token;
	}

	/** Revoke portal access without deleting booking data. */
	public static function revoke_public_token( $booking_id ) {
		global $wpdb;
		$result = $wpdb->update( self::table(), array( 'public_token_revoked_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $booking_id ) ) );
		if ( false !== $result ) { self::add_system_log( $booking_id, 'public_token_revoked', __( 'Accesso cliente revocato.', 'gest-web-rent' ) ); }
		return false !== $result;
	}

	/** Expire pending bookings and release blocks. */
	public static function expire_pending() {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::table() . " WHERE status IN ('pending','awaiting_payment','awaiting_bank_transfer','payment_failed') AND expires_at IS NOT NULL AND expires_at <= %s", current_time( 'mysql' ) ) );
		foreach ( $ids as $id ) {
			self::change_status( absint( $id ), 'expired', __( 'Scadenza automatica della richiesta in attesa.', 'gest-web-rent' ) );
		}
	}

	/** Basic request throttling without persisting the IP. */
	public static function check_antispam( $raw ) {
		if ( ! empty( $raw['website'] ) ) {
			return new WP_Error( 'gwr_spam', __( 'Invio non valido.', 'gest-web-rent' ) );
		}
		$started = absint( $raw['form_started_at'] ?? 0 );
		if ( ! $started || time() - $started < 3 ) {
			return new WP_Error( 'gwr_too_fast', __( 'Attendi qualche secondo e riprova.', 'gest-web-rent' ) );
		}
		$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
		$key = 'gwr_booking_rate_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 );
		$count = absint( get_transient( $key ) );
		if ( $count >= 5 ) {
			return new WP_Error( 'gwr_rate_limit', __( 'Troppe richieste. Riprova piu tardi.', 'gest-web-rent' ) );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/** Queue customer and administrator email after commit. */
	public static function send_notifications( $booking ) {
		if ( ! $booking || ! class_exists( 'GWR_Notification_Service' ) ) { return; }
		$args = array( 'reference' => 'created:' . $booking['id'], 'booking_token' => $booking['public_token'] ?? '' );
		GWR_Notification_Service::send_event( $booking, 'booking_created', $args );
		$admin_email = sanitize_email( GWR_Admin::get_settings()['contact_email'] ?? get_option( 'admin_email' ) );
		if ( $admin_email ) { $args['recipient'] = $admin_email; GWR_Notification_Service::send_event( $booking, 'booking_created', $args ); }
	}

	/** Send a concise customer status update after an admin transition. */
	public static function send_status_notification( $booking ) {
		if ( ! $booking || ! class_exists( 'GWR_Notification_Service' ) ) { return; }
		$events = array( 'pending' => 'booking_pending_confirmation', 'confirmed' => 'booking_confirmed', 'rejected' => 'booking_rejected', 'cancelled' => 'booking_cancelled', 'expired' => 'booking_expired', 'awaiting_payment' => 'payment_requested', 'payment_failed' => 'payment_failed', 'refunded' => 'payment_refunded' );
		$event = $events[ $booking['status'] ] ?? '';
		if ( $event ) { GWR_Notification_Service::send_event( $booking, $event, array( 'reference' => 'status:' . $booking['status'] . ':' . ( $booking['updated_at'] ?? '' ) ) ); }
	}

	/** Public display helpers. */
	public static function format_money( $amount, $currency = 'EUR' ) {
		return number_format_i18n( (float) $amount, 2 ) . ' ' . strtoupper( sanitize_key( $currency ) );
	}

	public static function format_datetime( $value ) {
		$timestamp = strtotime( (string) $value );
		return $timestamp ? wp_date( 'd-m-Y H:i', $timestamp ) : '';
	}

	/** Internal customer validation. */
	private static function sanitize_customer( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$type = 'company' === sanitize_key( $raw['customer_type'] ?? '' ) ? 'company' : 'private';
		$data = array(
			'customer_type' => $type, 'first_name' => sanitize_text_field( $raw['first_name'] ?? '' ), 'last_name' => sanitize_text_field( $raw['last_name'] ?? '' ),
			'email' => sanitize_email( $raw['email'] ?? '' ), 'phone' => preg_replace( '/[^0-9+ ]/', '', sanitize_text_field( $raw['phone'] ?? '' ) ), 'tax_code' => strtoupper( sanitize_text_field( $raw['tax_code'] ?? '' ) ),
			'birth_date' => sanitize_text_field( $raw['birth_date'] ?? '' ), 'birth_place' => sanitize_text_field( $raw['birth_place'] ?? '' ), 'nationality' => sanitize_text_field( $raw['nationality'] ?? '' ),
			'address' => sanitize_text_field( $raw['address'] ?? '' ), 'postal_code' => sanitize_text_field( $raw['postal_code'] ?? '' ), 'city' => sanitize_text_field( $raw['city'] ?? '' ), 'province' => sanitize_text_field( $raw['province'] ?? '' ), 'country' => sanitize_text_field( $raw['country'] ?? '' ),
			'company_name' => sanitize_text_field( $raw['company_name'] ?? '' ), 'vat_number' => sanitize_text_field( $raw['vat_number'] ?? '' ), 'company_tax_code' => sanitize_text_field( $raw['company_tax_code'] ?? '' ), 'pec' => sanitize_email( $raw['pec'] ?? '' ), 'recipient_code' => sanitize_text_field( $raw['recipient_code'] ?? '' ), 'registered_office' => sanitize_text_field( $raw['registered_office'] ?? '' ), 'contact_person' => sanitize_text_field( $raw['contact_person'] ?? '' ), 'notes' => sanitize_textarea_field( $raw['notes'] ?? '' ),
		);
		if ( ! $data['first_name'] || ! $data['last_name'] || ! is_email( $data['email'] ) || strlen( preg_replace( '/\D/', '', $data['phone'] ) ) < 7 ) {
			return new WP_Error( 'gwr_customer_invalid', __( 'Inserisci nome, cognome, email e telefono validi.', 'gest-web-rent' ), array( 'field' => 'customer' ) );
		}
		if ( 'company' === $type && ( ! $data['company_name'] || ! $data['vat_number'] ) ) {
			return new WP_Error( 'gwr_company_invalid', __( 'Per le aziende sono obbligatorie ragione sociale e partita IVA.', 'gest-web-rent' ), array( 'field' => 'company' ) );
		}
		if ( $data['tax_code'] && ! preg_match( '/^[A-Z0-9]{11,16}$/', $data['tax_code'] ) ) {
			return new WP_Error( 'gwr_tax_code_invalid', __( 'Il codice fiscale inserito non e valido.', 'gest-web-rent' ), array( 'field' => 'customer_tax_code' ) );
		}
		if ( 'company' === $type && ! preg_match( '/^[A-Z0-9]{8,16}$/i', $data['vat_number'] ) ) {
			return new WP_Error( 'gwr_vat_invalid', __( 'La partita IVA inserita non e valida.', 'gest-web-rent' ), array( 'field' => 'vat_number' ) );
		}
		return $data;
	}

	private static function sanitize_driver( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$data = array(
			'first_name' => sanitize_text_field( $raw['first_name'] ?? '' ), 'last_name' => sanitize_text_field( $raw['last_name'] ?? '' ), 'birth_date' => sanitize_text_field( $raw['birth_date'] ?? '' ), 'birth_place' => sanitize_text_field( $raw['birth_place'] ?? '' ), 'nationality' => sanitize_text_field( $raw['nationality'] ?? '' ), 'tax_code' => strtoupper( sanitize_text_field( $raw['tax_code'] ?? '' ) ), 'email' => sanitize_email( $raw['email'] ?? '' ), 'phone' => preg_replace( '/[^0-9+ ]/', '', sanitize_text_field( $raw['phone'] ?? '' ) ), 'address' => sanitize_text_field( $raw['address'] ?? '' ), 'license_type' => sanitize_text_field( $raw['license_type'] ?? '' ), 'license_number' => sanitize_text_field( $raw['license_number'] ?? '' ), 'license_country' => sanitize_text_field( $raw['license_country'] ?? '' ), 'license_issue_date' => sanitize_text_field( $raw['license_issue_date'] ?? '' ), 'license_expiry_date' => sanitize_text_field( $raw['license_expiry_date'] ?? '' ), 'international_license' => ! empty( $raw['international_license'] ), 'notes' => sanitize_textarea_field( $raw['notes'] ?? '' ),
		);
		if ( ! $data['first_name'] || ! $data['last_name'] || ! self::valid_iso_date( $data['birth_date'] ) || ! $data['license_type'] || ! $data['license_number'] || ! self::valid_iso_date( $data['license_issue_date'] ) || ! self::valid_iso_date( $data['license_expiry_date'] ) ) {
			return new WP_Error( 'gwr_driver_invalid', __( 'Completa i dati obbligatori del conducente e della patente.', 'gest-web-rent' ), array( 'field' => 'driver' ) );
		}
		return $data;
	}

	private static function driver_from_customer( $customer, $raw_driver ) {
		$raw_driver = is_array( $raw_driver ) ? $raw_driver : array();
		foreach ( array( 'first_name', 'last_name', 'birth_date', 'birth_place', 'nationality', 'tax_code', 'email', 'phone', 'address' ) as $key ) {
			$raw_driver[ $key ] = $customer[ $key ] ?? '';
		}
		return self::sanitize_driver( $raw_driver );
	}

	private static function validate_driver( $driver, $pickup, $requirements ) {
		$age = self::years_between( $driver['birth_date'], $pickup );
		if ( ! $age || ( ! empty( $requirements['min_age'] ) && $age < absint( $requirements['min_age'] ) ) || ( ! empty( $requirements['max_age'] ) && $age > absint( $requirements['max_age'] ) ) ) {
			return new WP_Error( 'gwr_driver_age', __( 'Il conducente non rispetta i requisiti di eta configurati.', 'gest-web-rent' ), array( 'field' => 'driver_birth_date' ) );
		}
		$license_expiry = self::date_object( $driver['license_expiry_date'] );
		$license_issue = self::date_object( $driver['license_issue_date'] );
		if ( ! $license_expiry || $license_expiry < $pickup ) {
			return new WP_Error( 'gwr_license_expired', __( 'La patente deve essere valida per la data di ritiro.', 'gest-web-rent' ), array( 'field' => 'license_expiry_date' ) );
		}
		if ( ! $license_issue || $license_issue > $pickup ) {
			return new WP_Error( 'gwr_license_issue_invalid', __( 'La data di rilascio della patente non e valida.', 'gest-web-rent' ), array( 'field' => 'license_issue_date' ) );
		}
		$license_years = self::years_between( $driver['license_issue_date'], $pickup );
		if ( ! empty( $requirements['min_license_years'] ) && $license_years < absint( $requirements['min_license_years'] ) ) {
			return new WP_Error( 'gwr_license_years', __( 'La patente non e posseduta da un numero sufficiente di anni.', 'gest-web-rent' ), array( 'field' => 'license_issue_date' ) );
		}
		if ( ! empty( $requirements['international_license'] ) && empty( $driver['international_license'] ) ) {
			return new WP_Error( 'gwr_international_license', __( 'Per questo noleggio e richiesta la patente internazionale.', 'gest-web-rent' ), array( 'field' => 'international_license' ) );
		}
		if ( ! empty( $requirements['eu_license'] ) ) {
			$country = strtoupper( sanitize_text_field( $driver['license_country'] ?? '' ) );
			$eu = array( 'AT','BE','BG','HR','CY','CZ','DE','DK','EE','ES','FI','FR','GR','EL','HU','IE','IT','LT','LU','LV','MT','NL','PL','PT','RO','SE','SI','SK','AUSTRIA','BELGIO','BULGARIA','CROAZIA','CIPRO','CECHIA','GERMANIA','DANIMARCA','ESTONIA','SPAGNA','FINLANDIA','FRANCIA','GRECIA','UNGHERIA','IRLANDA','ITALIA','LITUANIA','LUSSEMBURGO','LETTONIA','MALTA','PAESI BASSI','POLONIA','PORTOGALLO','ROMANIA','SVEZIA','SLOVENIA','SLOVACCHIA' );
			if ( ! in_array( $country, $eu, true ) ) { return new WP_Error( 'gwr_eu_license', __( 'Per questo noleggio e richiesta una patente rilasciata in Unione Europea.', 'gest-web-rent' ), array( 'field' => 'license_country' ) ); }
		}
		return true;
	}

	private static function validate_documents( $confirmed, $documents ) {
		foreach ( $documents as $document ) {
			if ( ! empty( $document['required'] ) && ! in_array( sanitize_key( $document['id'] ?? '' ), $confirmed, true ) ) {
				return new WP_Error( 'gwr_documents_missing', __( 'Conferma di possedere tutti i documenti obbligatori.', 'gest-web-rent' ), array( 'field' => 'documents' ) );
			}
		}
		return true;
	}

	private static function normalize_period( $input ) {
		$pickup = self::datetime_object( $input['start_date'], $input['pickup_time'] );
		$return = self::datetime_object( $input['end_date'], $input['return_time'] );
		if ( ! $pickup || ! $return || $return <= $pickup ) {
			return new WP_Error( 'gwr_period_invalid', __( 'Inserisci un periodo di noleggio valido.', 'gest-web-rent' ), array( 'field' => 'period' ) );
		}
		if ( $pickup < new DateTimeImmutable( 'now', wp_timezone() ) ) {
			return new WP_Error( 'gwr_period_past', __( 'La data di ritiro non puo essere nel passato.', 'gest-web-rent' ), array( 'field' => 'period' ) );
		}
		if ( ! $input['pickup_location'] || ! $input['return_location'] ) {
			return new WP_Error( 'gwr_locations_invalid', __( 'Inserisci luogo di ritiro e riconsegna.', 'gest-web-rent' ), array( 'field' => 'location' ) );
		}
		$duration = GWR_Pricing_Service::calculate_billable_duration( $pickup, $return );
		return array( 'pickup' => $pickup, 'return' => $return, 'pickup_mysql' => $pickup->format( 'Y-m-d H:i:s' ), 'return_mysql' => $return->format( 'Y-m-d H:i:s' ), 'days' => max( 1, absint( $duration['billable_days'] ) ), 'duration' => $duration );
	}

	private static function acquire_vehicle_lock( $vehicle_id ) {
		global $wpdb;
		$name = 'gwr_booking_vehicle_' . absint( $vehicle_id );
		$locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $name ) );
		return 1 === $locked ? true : new WP_Error( 'gwr_booking_busy', __( 'Il veicolo e in fase di prenotazione da un altro utente. Riprova.', 'gest-web-rent' ) );
	}

	private static function release_vehicle_lock( $vehicle_id ) {
		global $wpdb;
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'gwr_booking_vehicle_' . absint( $vehicle_id ) ) );
	}

	private static function create_availability_block( $booking_id, $code, $vehicle_id, $period ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$ok = $wpdb->insert( GWR_CPT::availability_table(), array( 'vehicle_id' => $vehicle_id, 'start_date' => substr( $period['pickup_mysql'], 0, 10 ), 'end_date' => substr( $period['return_mysql'], 0, 10 ), 'status' => 'reserved', 'internal_note' => sprintf( 'Prenotazione #%d', $booking_id ), 'external_reference' => 'booking:' . $code, 'created_at' => $now, 'updated_at' => $now ) );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	private static function ensure_availability_block( $booking ) {
		if ( ! empty( $booking['availability_id'] ) ) { return; }
		$period = array( 'pickup_mysql' => $booking['pickup_datetime'], 'return_mysql' => $booking['return_datetime'] );
		$id = self::create_availability_block( $booking['id'], $booking['booking_code'], $booking['vehicle_id'], $period );
		if ( $id ) {
			global $wpdb;
			$wpdb->update( self::table(), array( 'availability_id' => $id ), array( 'id' => $booking['id'] ), array( '%d' ), array( '%d' ) );
		}
	}

	private static function remove_availability_block( $booking ) {
		global $wpdb;
		if ( ! empty( $booking['availability_id'] ) ) {
			$wpdb->delete( GWR_CPT::availability_table(), array( 'id' => absint( $booking['availability_id'] ) ), array( '%d' ) );
			$wpdb->update( self::table(), array( 'availability_id' => null ), array( 'id' => absint( $booking['id'] ) ) );
		}
	}

	private static function insert_items( $booking_id, $items ) {
		global $wpdb;
		foreach ( $items as $item ) {
			$item['booking_id'] = absint( $booking_id );
			$item['created_at'] = current_time( 'mysql' );
			if ( false === $wpdb->insert( self::items_table(), $item ) ) {
				throw new Exception( __( 'Impossibile salvare extra e coperture.', 'gest-web-rent' ) );
			}
		}
	}

	private static function add_log( $booking_id, $action, $old_status = '', $new_status = '', $note = '' ) {
		global $wpdb;
		$wpdb->insert( self::logs_table(), array( 'booking_id' => absint( $booking_id ), 'user_id' => get_current_user_id(), 'action' => sanitize_key( $action ), 'old_status' => sanitize_key( $old_status ), 'new_status' => sanitize_key( $new_status ), 'note' => sanitize_textarea_field( $note ), 'created_at' => current_time( 'mysql' ) ) );
	}

	private static function snapshot( $vehicle, $terms, $price ) {
		return array( 'vehicle' => array( 'id' => absint( $vehicle['id'] ), 'title' => sanitize_text_field( $vehicle['title'] ), 'brand' => sanitize_text_field( $vehicle['brand'] ), 'model' => sanitize_text_field( $vehicle['model'] ), 'version' => sanitize_text_field( $vehicle['version'] ), 'daily_price' => (float) $vehicle['daily_price'] ), 'rental_terms' => $terms['rental_terms'], 'extras' => $terms['extras'], 'price' => $price, 'captured_at' => current_time( 'mysql' ) );
	}

	private static function price_item( $type, $id, $name, $quantity, $unit_minor, $mode, $days, $total_minor, $snapshot ) {
		return array( 'item_type' => sanitize_key( $type ), 'item_id' => sanitize_key( $id ), 'item_name' => sanitize_text_field( $name ), 'quantity' => absint( $quantity ), 'unit_price' => self::minor_to_decimal( $unit_minor ), 'pricing_mode' => sanitize_key( $mode ), 'days' => absint( $days ), 'total_price' => self::minor_to_decimal( $total_minor ), 'snapshot_json' => wp_json_encode( $snapshot ) );
	}

	private static function price_for_mode( $unit, $mode, $quantity, $days, $base, $percent ) {
		if ( 'free' === $mode ) { return 0; }
		if ( 'per_day' === $mode ) { return $unit * $quantity * $days; }
		if ( 'percentage' === $mode ) { return (int) round( $base * $percent / 100 ) * $quantity; }
		return $unit * $quantity;
	}

	private static function minor_to_decimal( $minor ) { return number_format( (int) $minor / 100, 2, '.', '' ); }

	private static function generate_code( $id, $prefix ) {
		$prefix = strtoupper( preg_replace( '/[^A-Z0-9]/', '', sanitize_text_field( $prefix ) ) );
		return ( $prefix ?: 'GWR' ) . '-' . wp_date( 'Y' ) . '-' . str_pad( (string) absint( $id ), 6, '0', STR_PAD_LEFT );
	}

	private static function datetime_object( $date, $time ) {
		$object = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, wp_timezone() );
		$errors = DateTimeImmutable::getLastErrors();
		return $object && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) ? $object : null;
	}

	private static function date_object( $date ) {
		$object = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		$errors = DateTimeImmutable::getLastErrors();
		return $object && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) ? $object : null;
	}

	private static function valid_iso_date( $date ) { return (bool) self::date_object( $date ); }

	private static function years_between( $date, $target ) {
		$from = self::date_object( $date );
		return $from && $target && $from <= $target ? (int) $from->diff( $target )->y : -1;
	}

	private static function sanitize_payment_method( $method ) {
		$settings = class_exists( 'GWR_Pricing_Service' ) ? GWR_Pricing_Service::get_settings() : array();
		$allowed = array_filter( array_map( 'sanitize_key', explode( ',', (string) ( $settings['active_payment_methods'] ?? 'request,pickup' ) ) ) );
		if ( ! empty( $settings['stripe_enabled'] ) && ! in_array( 'stripe', $allowed, true ) ) {
			$allowed[] = 'stripe';
		}
		if ( ! empty( $settings['bank_transfer_enabled'] ) && ! in_array( 'bank_transfer', $allowed, true ) ) {
			$allowed[] = 'bank_transfer';
		}
		$allowed = $allowed ?: array( 'request', 'pickup' );
		$method = sanitize_key( $method ?: ( $settings['default_payment_method'] ?? 'request' ) );
		return in_array( $method, $allowed, true ) ? $method : $allowed[0];
	}

	private static function initial_status_for_payment( $method, $price ) {
		if ( empty( $price['pay_now_minor'] ) ) {
			return 'pending';
		}
		if ( 'stripe' === $method ) {
			return 'awaiting_payment';
		}
		if ( 'bank_transfer' === $method ) {
			return 'awaiting_bank_transfer';
		}
		return 'pending';
	}

	private static function hash_public_token( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	private static function generate_public_token() {
		try { return bin2hex( random_bytes( 32 ) ); }
		catch ( Exception $exception ) { return wp_generate_password( 64, false, false ); }
	}

	private static function sanitize_operation( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array( 'effective_datetime' => sanitize_text_field( $input['effective_datetime'] ?? '' ), 'odometer' => absint( $input['odometer'] ?? 0 ), 'fuel_level' => sanitize_text_field( $input['fuel_level'] ?? '' ), 'deposit_verified' => ! empty( $input['deposit_verified'] ), 'damage' => sanitize_textarea_field( $input['damage'] ?? '' ), 'delay_minutes' => absint( $input['delay_minutes'] ?? 0 ), 'notes' => sanitize_textarea_field( $input['notes'] ?? '' ) );
	}
}
