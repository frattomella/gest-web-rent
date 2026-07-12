<?php
/**
 * Booking document generation and secure delivery.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Central service for booking documents.
 */
class GWR_Document_Service {
	const DB_VERSION = '1.8.0';
	const DB_OPTION = 'gwr_documents_db_version';
	const OPTION_NAME = 'gwr_document_settings';

	/**
	 * Runtime hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 6 );
		add_action( 'admin_post_gwr_generate_document', array( __CLASS__, 'handle_generate' ) );
		add_action( 'admin_post_gwr_document_preview', array( __CLASS__, 'handle_preview' ) );
		add_action( 'admin_post_nopriv_gwr_document_preview', array( __CLASS__, 'handle_preview' ) );
		add_action( 'admin_post_gwr_document_download', array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_nopriv_gwr_document_download', array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_gwr_document_invalidate', array( __CLASS__, 'handle_invalidate' ) );
		add_action( 'admin_post_gwr_document_archive', array( __CLASS__, 'handle_archive' ) );
		add_action( 'admin_post_gwr_document_send', array( __CLASS__, 'handle_send' ) );
		add_action( 'admin_post_gwr_document_sign', array( __CLASS__, 'handle_sign' ) );
	}

	/**
	 * Main table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_booking_documents';
	}

	/**
	 * Signatures table name.
	 *
	 * @return string
	 */
	public static function signatures_table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_document_signatures';
	}

	/**
	 * Email logs table name.
	 *
	 * @return string
	 */
	public static function email_logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_document_email_logs';
	}

	/**
	 * Create or update document tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE " . self::table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			document_type varchar(64) NOT NULL,
			document_number varchar(80) NOT NULL,
			version int unsigned NOT NULL DEFAULT 1,
			status varchar(32) NOT NULL DEFAULT 'generated',
			file_path text NULL,
			file_hash varchar(128) NULL,
			content_hash varchar(128) NULL,
			snapshot_json longtext NULL,
			generated_by bigint(20) unsigned NULL,
			generated_at datetime NULL,
			sent_at datetime NULL,
			signed_at datetime NULL,
			invalidated_at datetime NULL,
			archived_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY document_number (document_number),
			KEY booking_id (booking_id),
			KEY document_type (document_type),
			KEY status (status),
			KEY generated_at (generated_at)
		) $charset;" );

		dbDelta( "CREATE TABLE " . self::signatures_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			document_id bigint(20) unsigned NOT NULL,
			booking_id bigint(20) unsigned NOT NULL,
			version int unsigned NOT NULL DEFAULT 1,
			signer_name varchar(190) NOT NULL,
			signer_role varchar(80) NOT NULL,
			method varchar(40) NOT NULL DEFAULT 'drawn',
			status varchar(32) NOT NULL DEFAULT 'captured',
			document_hash varchar(128) NULL,
			signature_hash varchar(128) NULL,
			signature_data longtext NULL,
			user_agent text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY document_id (document_id),
			KEY booking_id (booking_id),
			KEY status (status)
		) $charset;" );

		dbDelta( "CREATE TABLE " . self::email_logs_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			document_id bigint(20) unsigned NOT NULL,
			booking_id bigint(20) unsigned NOT NULL,
			recipient varchar(190) NOT NULL,
			communication_type varchar(64) NOT NULL DEFAULT 'document_email',
			status varchar(32) NOT NULL,
			error_message text NULL,
			user_id bigint(20) unsigned NULL,
			document_version int unsigned NOT NULL DEFAULT 1,
			sent_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY document_id (document_id),
			KEY booking_id (booking_id),
			KEY status (status)
		) $charset;" );

		update_option( self::DB_OPTION, self::DB_VERSION, false );
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::default_settings(), '', false );
		}
		GWR_PDF_Service::storage_dir();
	}

	/**
	 * Upgrade storage lazily for existing installations.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}
		self::create_tables();
	}

	/**
	 * Supported document types.
	 *
	 * @return array
	 */
	public static function document_types() {
		return array(
			'booking_summary' => array( 'label' => __( 'Riepilogo prenotazione', 'gest-web-rent' ), 'prefix' => 'RIE', 'public' => true ),
			'booking_confirmation' => array( 'label' => __( 'Conferma prenotazione', 'gest-web-rent' ), 'prefix' => 'CNF', 'public' => true ),
			'booking_voucher' => array( 'label' => __( 'Voucher cliente', 'gest-web-rent' ), 'prefix' => 'VOU', 'public' => true ),
			'rental_contract' => array( 'label' => __( 'Contratto di noleggio', 'gest-web-rent' ), 'prefix' => 'CON', 'public' => true, 'signable' => true ),
			'pickup_report' => array( 'label' => __( 'Verbale consegna', 'gest-web-rent' ), 'prefix' => 'DEL', 'public' => false, 'signable' => true ),
			'return_report' => array( 'label' => __( 'Verbale riconsegna', 'gest-web-rent' ), 'prefix' => 'RET', 'public' => false, 'signable' => true ),
			'payment_summary' => array( 'label' => __( 'Riepilogo pagamenti', 'gest-web-rent' ), 'prefix' => 'PAY', 'public' => true ),
			'cancellation_confirmation' => array( 'label' => __( 'Conferma annullamento', 'gest-web-rent' ), 'prefix' => 'CAN', 'public' => true ),
		);
	}

	/**
	 * Document statuses.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'generated' => __( 'Generato', 'gest-web-rent' ),
			'sent' => __( 'Inviato', 'gest-web-rent' ),
			'signed' => __( 'Firmato', 'gest-web-rent' ),
			'invalidated' => __( 'Invalidato', 'gest-web-rent' ),
			'archived' => __( 'Archiviato', 'gest-web-rent' ),
		);
	}

	/**
	 * Default document settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		$admin = class_exists( 'GWR_Admin' ) ? GWR_Admin::get_settings() : array();
		return array(
			'company_legal_name' => $admin['dealer_name'] ?? get_bloginfo( 'name' ),
			'company_trade_name' => $admin['dealer_name'] ?? get_bloginfo( 'name' ),
			'vat_number' => '',
			'tax_code' => '',
			'rea_number' => '',
			'address' => '',
			'postal_code' => '',
			'city' => '',
			'province' => '',
			'country' => 'Italia',
			'phone' => $admin['whatsapp_number'] ?? '',
			'email' => $admin['contact_email'] ?? get_option( 'admin_email' ),
			'pec' => '',
			'website' => home_url( '/' ),
			'logo_id' => 0,
			'voucher_intro' => __( 'Documento riepilogativo per il ritiro del veicolo.', 'gest-web-rent' ),
			'confirmation_intro' => __( 'La prenotazione e stata presa in carico secondo lo stato indicato nel documento.', 'gest-web-rent' ),
			'contract_intro' => __( 'Bozza operativa del contratto collegato alla prenotazione.', 'gest-web-rent' ),
			'pickup_text' => __( 'Compilare i dati effettivi di consegna e verificare il veicolo con il cliente.', 'gest-web-rent' ),
			'return_text' => __( 'Compilare i dati effettivi di riconsegna e gli eventuali rilievi.', 'gest-web-rent' ),
			'payment_note' => __( 'Riepilogo non fiscale dei pagamenti associati alla prenotazione.', 'gest-web-rent' ),
			'cancellation_text' => __( 'Conferma operativa dell annullamento della prenotazione.', 'gest-web-rent' ),
			'legal_text' => '',
			'documents_footer' => get_bloginfo( 'name' ),
			'contract_clauses' => array(),
			'attachments_enabled' => 1,
			'max_upload_mb' => 8,
			'retention_days' => 3650,
		);
	}

	/**
	 * Read settings with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		return array_replace_recursive( self::default_settings(), $settings );
	}

	/**
	 * Save sanitized settings.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public static function save_settings( $input ) {
		$settings = self::sanitize_settings( $input );
		update_option( self::OPTION_NAME, $settings, false );
		return $settings;
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? wp_unslash( $input ) : array();
		$text_fields = array( 'company_legal_name', 'company_trade_name', 'vat_number', 'tax_code', 'rea_number', 'address', 'postal_code', 'city', 'province', 'country', 'phone', 'pec', 'documents_footer' );
		$out = array();
		foreach ( $text_fields as $field ) {
			$out[ $field ] = sanitize_text_field( $input[ $field ] ?? '' );
		}
		$out['email'] = sanitize_email( $input['email'] ?? '' );
		$out['website'] = esc_url_raw( $input['website'] ?? '' );
		$out['logo_id'] = absint( $input['logo_id'] ?? 0 );
		foreach ( array( 'voucher_intro', 'confirmation_intro', 'contract_intro', 'pickup_text', 'return_text', 'payment_note', 'cancellation_text', 'legal_text' ) as $field ) {
			$out[ $field ] = sanitize_textarea_field( $input[ $field ] ?? '' );
		}
		$clauses = array();
		if ( ! empty( $input['contract_clauses'] ) ) {
			$raw = is_array( $input['contract_clauses'] ) ? $input['contract_clauses'] : explode( "\n", (string) $input['contract_clauses'] );
			foreach ( $raw as $clause ) {
				$clause = trim( sanitize_textarea_field( $clause ) );
				if ( '' !== $clause ) {
					$clauses[] = $clause;
				}
			}
		}
		$out['contract_clauses'] = $clauses;
		$out['attachments_enabled'] = ! empty( $input['attachments_enabled'] ) ? 1 : 0;
		$out['max_upload_mb'] = max( 1, min( 64, absint( $input['max_upload_mb'] ?? 8 ) ) );
		$out['retention_days'] = max( 30, min( 3650, absint( $input['retention_days'] ?? 3650 ) ) );
		return array_replace_recursive( self::default_settings(), $out );
	}

	/**
	 * Generate a document from booking snapshot.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $type Document type.
	 * @param array  $args Arguments.
	 * @return array|WP_Error
	 */
	public static function generate( $booking_id, $type, $args = array() ) {
		global $wpdb;
		$type = sanitize_key( $type );
		$types = self::document_types();
		if ( empty( $types[ $type ] ) ) {
			return new WP_Error( 'gwr_document_type_invalid', __( 'Tipo documento non valido.', 'gest-web-rent' ) );
		}
		$booking = GWR_Bookings::get( absint( $booking_id ) );
		if ( ! $booking ) {
			return new WP_Error( 'gwr_booking_not_found', __( 'Prenotazione non trovata.', 'gest-web-rent' ) );
		}

		$data = self::normalize_document_data( $booking, $type );
		$validation = self::validate_document_data( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$settings = self::get_settings();
		$latest = self::latest_for_booking( $booking_id, $type, true );
		$snapshot_payload = array(
			'type' => $type,
			'data' => $data,
			'settings_version' => self::settings_hash( $settings ),
			'template_version' => GWR_Document_Template_Service::TEMPLATE_VERSION,
		);
		$content_hash = hash( 'sha256', wp_json_encode( $snapshot_payload ) );
		$force = ! empty( $args['force'] );
		if ( $latest && ! $force && $content_hash === ( $latest['content_hash'] ?? '' ) && in_array( $latest['status'], array( 'generated', 'sent', 'signed' ), true ) ) {
			return $latest;
		}

		$version = $latest ? absint( $latest['version'] ) + 1 : 1;
		$number = self::document_number( $booking, $type, $version );
		$now = current_time( 'mysql' );
		$meta = array(
			'document_number' => $number,
			'version' => $version,
			'generated_at' => GWR_Bookings::format_datetime( $now ),
			'content_hash' => $content_hash,
		);
		$html = GWR_Document_Template_Service::render( $type, $data, $settings, $meta );

		if ( $latest && in_array( $latest['status'], array( 'generated', 'sent' ), true ) ) {
			$wpdb->update(
				self::table(),
				array( 'status' => 'invalidated', 'invalidated_at' => $now, 'updated_at' => $now ),
				array( 'id' => absint( $latest['id'] ) )
			);
		}

		$inserted = $wpdb->insert(
			self::table(),
			array(
				'booking_id' => absint( $booking_id ),
				'document_type' => $type,
				'document_number' => $number,
				'version' => $version,
				'status' => 'generated',
				'content_hash' => $content_hash,
				'snapshot_json' => wp_json_encode( $snapshot_payload ),
				'generated_by' => get_current_user_id(),
				'generated_at' => $now,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		if ( false === $inserted ) {
			return new WP_Error( 'gwr_document_insert_failed', __( 'Impossibile registrare il documento.', 'gest-web-rent' ) );
		}

		$document_id = (int) $wpdb->insert_id;
		$cache = GWR_PDF_Service::cache_html( $document_id, $number, $html );
		if ( is_wp_error( $cache ) ) {
			return $cache;
		}
		$wpdb->update(
			self::table(),
			array( 'file_path' => $cache['path'], 'file_hash' => $cache['hash'], 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $document_id )
		);

		GWR_Bookings::add_system_log( $booking_id, 'document_generated', sprintf( __( 'Documento %1$s versione %2$d generato.', 'gest-web-rent' ), $types[ $type ]['label'], $version ) );
		return self::get( $document_id );
	}

	/**
	 * Get a document by ID.
	 *
	 * @param int $document_id Document ID.
	 * @return array|null
	 */
	public static function get( $document_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id=%d', absint( $document_id ) ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Latest document for booking/type.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $type Document type.
	 * @param bool   $include_inactive Include invalidated/archived.
	 * @return array|null
	 */
	public static function latest_for_booking( $booking_id, $type, $include_inactive = false ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table() . ' WHERE booking_id=%d AND document_type=%s';
		$values = array( absint( $booking_id ), sanitize_key( $type ) );
		if ( ! $include_inactive ) {
			$sql .= " AND status NOT IN ('invalidated','archived')";
		}
		$sql .= ' ORDER BY version DESC, id DESC LIMIT 1';
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $values ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * All documents for one booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	public static function documents_for_booking( $booking_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE booking_id=%d ORDER BY document_type ASC, version DESC, id DESC', absint( $booking_id ) ), ARRAY_A );
		return array_map( array( __CLASS__, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Query documents for admin archive.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array( 'search' => '', 'type' => '', 'status' => '', 'page' => 1, 'per_page' => 20 ) );
		$where = array( '1=1' );
		$values = array();
		if ( $args['type'] ) {
			$where[] = 'd.document_type=%s';
			$values[] = sanitize_key( $args['type'] );
		}
		if ( $args['status'] ) {
			$where[] = 'd.status=%s';
			$values[] = sanitize_key( $args['status'] );
		}
		if ( $args['search'] ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[] = '(d.document_number LIKE %s OR b.booking_code LIKE %s OR b.customer_email LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}
		$where_sql = implode( ' AND ', $where );
		$total_sql = 'SELECT COUNT(*) FROM ' . self::table() . ' d LEFT JOIN ' . GWR_Bookings::table() . ' b ON b.id=d.booking_id WHERE ' . $where_sql;
		$total = (int) $wpdb->get_var( $values ? $wpdb->prepare( $total_sql, $values ) : $total_sql );
		$page = max( 1, absint( $args['page'] ) );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ) ) );
		$offset = ( $page - 1 ) * $per_page;
		$list_sql = 'SELECT d.*, b.booking_code, b.customer_email, b.vehicle_title FROM ' . self::table() . ' d LEFT JOIN ' . GWR_Bookings::table() . ' b ON b.id=d.booking_id WHERE ' . $where_sql . ' ORDER BY d.created_at DESC, d.id DESC LIMIT %d OFFSET %d';
		$list_values = array_merge( $values, array( $per_page, $offset ) );
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_values ), ARRAY_A );
		return array( 'items' => is_array( $rows ) ? array_map( array( __CLASS__, 'hydrate' ), $rows ) : array(), 'total' => $total, 'pages' => (int) ceil( $total / $per_page ) );
	}

	/**
	 * Preview/download URL.
	 *
	 * @param array  $document Document row.
	 * @param string $mode preview|download.
	 * @param array  $args Extra public args.
	 * @return string
	 */
	public static function document_url( $document, $mode = 'preview', $args = array() ) {
		$action = 'download' === $mode ? 'gwr_document_download' : 'gwr_document_preview';
		$params = array_merge( array( 'action' => $action, 'document_id' => absint( $document['id'] ) ), $args );
		if ( current_user_can( 'manage_options' ) ) {
			return wp_nonce_url( admin_url( 'admin-post.php?' . http_build_query( $params ) ), $action . '_' . absint( $document['id'] ) );
		}
		return admin_url( 'admin-post.php?' . http_build_query( $params ) );
	}

	/**
	 * Signed time-limited URL.
	 *
	 * @param array  $document Document row.
	 * @param string $mode preview|download.
	 * @param int    $ttl Time to live.
	 * @return string
	 */
	public static function signed_document_url( $document, $mode = 'download', $ttl = DAY_IN_SECONDS ) {
		$expires = time() + absint( $ttl );
		$params = array(
			'gwr_expires' => $expires,
			'gwr_signature' => self::signature_for_url( $document, $expires ),
		);
		return self::document_url( $document, $mode, $params );
	}

	/**
	 * Intro text per type.
	 *
	 * @param string $type Document type.
	 * @param array  $settings Settings.
	 * @param array  $data Snapshot data.
	 * @return string
	 */
	public static function document_intro( $type, $settings, $data ) {
		$map = array(
			'booking_summary' => 'confirmation_intro',
			'booking_confirmation' => 'confirmation_intro',
			'booking_voucher' => 'voucher_intro',
			'rental_contract' => 'contract_intro',
			'pickup_report' => 'pickup_text',
			'return_report' => 'return_text',
			'payment_summary' => 'payment_note',
			'cancellation_confirmation' => 'cancellation_text',
		);
		$key = $map[ $type ] ?? 'confirmation_intro';
		return trim( (string) ( $settings[ $key ] ?? '' ) );
	}

	/**
	 * Normalize booking data for immutable snapshots.
	 *
	 * @param array  $booking Booking row.
	 * @param string $type Document type.
	 * @return array
	 */
	public static function normalize_document_data( $booking, $type ) {
		$items = GWR_Bookings::items( $booking['id'] );
		$vehicle = GWR_CPT::get_vehicle( $booking['vehicle_id'] );
		$payment = class_exists( 'GWR_Payment_Service' ) ? GWR_Payment_Service::latest_for_booking( $booking['id'] ) : null;
		$customer = $booking['customer'] ?? array();
		$driver = $booking['driver'] ?? array();
		$pricing_snapshot = $booking['pricing_snapshot'] ?? ( $booking['snapshot']['price'] ?? array() );

		return array(
			'booking' => array(
				'id' => absint( $booking['id'] ),
				'code' => sanitize_text_field( $booking['booking_code'] ),
				'status' => sanitize_key( $booking['status'] ),
				'status_label' => GWR_Bookings::statuses()[ $booking['status'] ] ?? $booking['status'],
				'payment_status' => sanitize_key( $booking['payment_status'] ?? '' ),
				'pickup_datetime' => GWR_Bookings::format_datetime( $booking['pickup_datetime'] ),
				'return_datetime' => GWR_Bookings::format_datetime( $booking['return_datetime'] ),
				'pickup_location' => sanitize_text_field( $booking['pickup_location'] ),
				'return_location' => sanitize_text_field( $booking['return_location'] ),
				'created_at' => GWR_Bookings::format_datetime( $booking['created_at'] ),
				'confirmed_at' => GWR_Bookings::format_datetime( $booking['confirmed_at'] ?? '' ),
				'cancelled_at' => GWR_Bookings::format_datetime( $booking['cancelled_at'] ?? '' ),
				'customer_notes' => sanitize_textarea_field( $booking['customer_notes'] ?? '' ),
				'admin_notes' => sanitize_textarea_field( $booking['admin_notes'] ?? '' ),
				'privacy_version' => sanitize_text_field( $booking['privacy_version'] ?? '' ),
			),
			'customer' => array(
				'name' => self::full_name( $customer ),
				'email' => sanitize_email( $booking['customer_email'] ?? ( $customer['email'] ?? '' ) ),
				'phone' => sanitize_text_field( $booking['customer_phone'] ?? ( $customer['phone'] ?? '' ) ),
				'tax_code' => sanitize_text_field( $customer['tax_code'] ?? '' ),
				'company' => sanitize_text_field( $customer['company_name'] ?? '' ),
				'vat_number' => sanitize_text_field( $customer['vat_number'] ?? '' ),
				'address' => self::address_line( $customer ),
			),
			'driver' => array(
				'name' => self::full_name( $driver ),
				'birth_date' => sanitize_text_field( $driver['birth_date'] ?? '' ),
				'license_type' => sanitize_text_field( $driver['license_type'] ?? '' ),
				'license_number' => sanitize_text_field( $driver['license_number'] ?? '' ),
				'license_country' => sanitize_text_field( $driver['license_country'] ?? '' ),
				'license_expiry_date' => sanitize_text_field( $driver['license_expiry_date'] ?? '' ),
			),
			'vehicle' => array(
				'id' => absint( $booking['vehicle_id'] ),
				'title' => sanitize_text_field( $booking['vehicle_title'] ),
				'plate' => sanitize_text_field( $vehicle['plate'] ?? ( $vehicle['license_plate'] ?? '' ) ),
				'category' => sanitize_text_field( $vehicle['category'] ?? '' ),
				'fuel' => sanitize_text_field( $vehicle['fuel'] ?? '' ),
				'transmission' => sanitize_text_field( $vehicle['transmission'] ?? '' ),
				'included_km' => self::included_km_label( $vehicle, $booking ),
			),
			'items' => self::normalize_items( $items, $booking['currency'] ),
			'pricing' => array(
				'base' => GWR_Bookings::format_money( $booking['base_amount'] ?? 0, $booking['currency'] ),
				'extras' => GWR_Bookings::format_money( $booking['extras_amount'] ?? 0, $booking['currency'] ),
				'coverages' => GWR_Bookings::format_money( $booking['coverages_amount'] ?? 0, $booking['currency'] ),
				'supplements' => GWR_Bookings::format_money( $booking['supplements_amount'] ?? 0, $booking['currency'] ),
				'discounts' => GWR_Bookings::format_money( $booking['discounts_amount'] ?? 0, $booking['currency'] ),
				'taxes' => GWR_Bookings::format_money( $booking['taxes_amount'] ?? 0, $booking['currency'] ),
				'total' => GWR_Bookings::format_money( $booking['total_amount'] ?? 0, $booking['currency'] ),
				'pay_now' => GWR_Bookings::format_money( $booking['pay_now_amount'] ?? 0, $booking['currency'] ),
				'pay_later' => GWR_Bookings::format_money( $booking['pay_later_amount'] ?? 0, $booking['currency'] ),
				'deposit' => GWR_Bookings::format_money( $booking['deposit_amount'] ?? 0, $booking['currency'] ),
				'snapshot' => $pricing_snapshot,
			),
			'payment' => self::normalize_payment( $payment, $booking ),
			'delivery' => self::normalize_operation( $booking['delivery'] ?? array() ),
			'return' => self::normalize_operation( $booking['return'] ?? array() ),
			'raw_snapshot' => $booking['snapshot'] ?? array(),
			'document_type' => $type,
		);
	}

	/**
	 * Admin action: generate document.
	 *
	 * @return void
	 */
	public static function handle_generate() {
		self::require_admin();
		$booking_id = absint( $_REQUEST['booking_id'] ?? 0 );
		$type = sanitize_key( wp_unslash( $_REQUEST['document_type'] ?? '' ) );
		check_admin_referer( 'gwr_generate_document_' . $booking_id . '_' . $type );
		$result = self::generate( $booking_id, $type, array( 'force' => ! empty( $_REQUEST['force'] ) ) );
		self::redirect_to_booking( $booking_id, $result, __( 'Documento generato.', 'gest-web-rent' ) );
	}

	/**
	 * Admin/public action: preview.
	 *
	 * @return void
	 */
	public static function handle_preview() {
		$document = self::requested_document();
		if ( ! $document || ! self::authorize_access( $document ) ) {
			wp_die( esc_html__( 'Documento non autorizzato.', 'gest-web-rent' ), esc_html__( 'Accesso negato', 'gest-web-rent' ), array( 'response' => 403 ) );
		}
		self::stream( $document, false );
	}

	/**
	 * Admin/public action: download.
	 *
	 * @return void
	 */
	public static function handle_download() {
		$document = self::requested_document();
		if ( ! $document || ! self::authorize_access( $document ) ) {
			wp_die( esc_html__( 'Documento non autorizzato.', 'gest-web-rent' ), esc_html__( 'Accesso negato', 'gest-web-rent' ), array( 'response' => 403 ) );
		}
		self::stream( $document, true );
	}

	/**
	 * Admin action: invalidate.
	 *
	 * @return void
	 */
	public static function handle_invalidate() {
		self::require_admin();
		$document_id = absint( $_REQUEST['document_id'] ?? 0 );
		check_admin_referer( 'gwr_document_action_' . $document_id );
		$result = self::change_document_status( $document_id, 'invalidated' );
		$document = self::get( $document_id );
		self::redirect_to_booking( $document['booking_id'] ?? 0, $result, __( 'Documento invalidato.', 'gest-web-rent' ) );
	}

	/**
	 * Admin action: archive.
	 *
	 * @return void
	 */
	public static function handle_archive() {
		self::require_admin();
		$document_id = absint( $_REQUEST['document_id'] ?? 0 );
		check_admin_referer( 'gwr_document_action_' . $document_id );
		$result = self::change_document_status( $document_id, 'archived' );
		$document = self::get( $document_id );
		self::redirect_to_booking( $document['booking_id'] ?? 0, $result, __( 'Documento archiviato.', 'gest-web-rent' ) );
	}

	/**
	 * Admin action: send document email.
	 *
	 * @return void
	 */
	public static function handle_send() {
		self::require_admin();
		$document_id = absint( $_REQUEST['document_id'] ?? 0 );
		check_admin_referer( 'gwr_document_action_' . $document_id );
		$result = self::send_document( $document_id );
		$document = self::get( $document_id );
		self::redirect_to_booking( $document['booking_id'] ?? 0, $result, __( 'Documento inviato.', 'gest-web-rent' ) );
	}

	/**
	 * Admin action: capture signature.
	 *
	 * @return void
	 */
	public static function handle_sign() {
		self::require_admin();
		$document_id = absint( $_POST['document_id'] ?? 0 );
		check_admin_referer( 'gwr_document_action_' . $document_id );
		$result = self::sign_document( $document_id, $_POST );
		$document = self::get( $document_id );
		self::redirect_to_booking( $document['booking_id'] ?? 0, $result, __( 'Firma salvata.', 'gest-web-rent' ) );
	}

	/**
	 * Send document link and HTML attachment.
	 *
	 * @param int $document_id Document ID.
	 * @return bool|WP_Error
	 */
	public static function send_document( $document_id ) {
		global $wpdb;
		$document = self::get( $document_id );
		if ( ! $document ) {
			return new WP_Error( 'gwr_document_not_found', __( 'Documento non trovato.', 'gest-web-rent' ) );
		}
		$booking = GWR_Bookings::get( $document['booking_id'] );
		if ( ! $booking || ! is_email( $booking['customer_email'] ) ) {
			return new WP_Error( 'gwr_document_email_missing', __( 'Email cliente non valida.', 'gest-web-rent' ) );
		}
		if ( in_array( $document['status'], array( 'invalidated', 'archived' ), true ) ) {
			return new WP_Error( 'gwr_document_inactive', __( 'Il documento non puo essere inviato perche non e attivo.', 'gest-web-rent' ) );
		}
		$types = self::document_types();
		$label = $types[ $document['document_type'] ]['label'] ?? __( 'Documento', 'gest-web-rent' );
		$link = self::signed_document_url( $document, 'download', 7 * DAY_IN_SECONDS );
		$subject = sprintf( __( '%1$s - prenotazione %2$s', 'gest-web-rent' ), $label, $booking['booking_code'] );
		$body = '<p>' . esc_html( sprintf( __( 'In allegato e tramite link trovi il documento %1$s della prenotazione %2$s.', 'gest-web-rent' ), $label, $booking['booking_code'] ) ) . '</p><p><a href="' . esc_url( $link ) . '">' . esc_html__( 'Apri documento', 'gest-web-rent' ) . '</a></p><p>' . esc_html__( 'Il link e temporaneo e non espone percorsi file.', 'gest-web-rent' ) . '</p>';
		$attachments = array();
		if ( ! empty( $document['file_path'] ) && GWR_PDF_Service::is_path_allowed( $document['file_path'] ) && file_exists( $document['file_path'] ) ) {
			$attachments[] = $document['file_path'];
		}
		$sent = wp_mail( $booking['customer_email'], $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ), $attachments );
		$wpdb->insert(
			self::email_logs_table(),
			array(
				'document_id' => absint( $document_id ),
				'booking_id' => absint( $document['booking_id'] ),
				'recipient' => sanitize_email( $booking['customer_email'] ),
				'communication_type' => 'document_email',
				'status' => $sent ? 'sent' : 'failed',
				'error_message' => $sent ? '' : __( 'wp_mail ha restituito false.', 'gest-web-rent' ),
				'user_id' => get_current_user_id(),
				'document_version' => absint( $document['version'] ),
				'sent_at' => current_time( 'mysql' ),
			)
		);
		if ( $sent ) {
			$update = array( 'sent_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) );
			if ( 'signed' !== $document['status'] ) {
				$update['status'] = 'sent';
			}
			$wpdb->update( self::table(), $update, array( 'id' => absint( $document_id ) ) );
			GWR_Bookings::add_system_log( $document['booking_id'], 'document_sent', sprintf( __( 'Documento %s inviato al cliente.', 'gest-web-rent' ), $document['document_number'] ) );
			return true;
		}
		return new WP_Error( 'gwr_document_email_failed', __( 'Invio email non riuscito.', 'gest-web-rent' ) );
	}

	/**
	 * Capture a simple drawn signature.
	 *
	 * @param int   $document_id Document ID.
	 * @param array $raw Raw request.
	 * @return bool|WP_Error
	 */
	public static function sign_document( $document_id, $raw ) {
		global $wpdb;
		$document = self::get( $document_id );
		if ( ! $document ) {
			return new WP_Error( 'gwr_document_not_found', __( 'Documento non trovato.', 'gest-web-rent' ) );
		}
		$type = self::document_types()[ $document['document_type'] ] ?? array();
		if ( empty( $type['signable'] ) ) {
			return new WP_Error( 'gwr_document_not_signable', __( 'Questo documento non prevede firma.', 'gest-web-rent' ) );
		}
		if ( 'signed' === $document['status'] ) {
			return new WP_Error( 'gwr_document_already_signed', __( 'Documento gia firmato. Genera una nuova versione per modifiche successive.', 'gest-web-rent' ) );
		}
		$signature_data = trim( (string) wp_unslash( $raw['signature_data'] ?? '' ) );
		if ( '' === $signature_data || 0 !== strpos( $signature_data, 'data:image/png;base64,' ) ) {
			return new WP_Error( 'gwr_signature_missing', __( 'Firma grafica mancante.', 'gest-web-rent' ) );
		}
		$name = sanitize_text_field( wp_unslash( $raw['signer_name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error( 'gwr_signature_name_missing', __( 'Nome firmatario mancante.', 'gest-web-rent' ) );
		}
		$now = current_time( 'mysql' );
		$wpdb->insert(
			self::signatures_table(),
			array(
				'document_id' => absint( $document_id ),
				'booking_id' => absint( $document['booking_id'] ),
				'version' => absint( $document['version'] ),
				'signer_name' => $name,
				'signer_role' => sanitize_text_field( wp_unslash( $raw['signer_role'] ?? 'customer' ) ),
				'method' => 'drawn',
				'status' => 'captured',
				'document_hash' => $document['file_hash'] ?: $document['content_hash'],
				'signature_hash' => hash( 'sha256', $signature_data ),
				'signature_data' => $signature_data,
				'user_agent' => sanitize_textarea_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
				'created_at' => $now,
			)
		);
		$wpdb->update( self::table(), array( 'status' => 'signed', 'signed_at' => $now, 'updated_at' => $now ), array( 'id' => absint( $document_id ) ) );
		GWR_Bookings::add_system_log( $document['booking_id'], 'document_signed', sprintf( __( 'Documento %s firmato da %s.', 'gest-web-rent' ), $document['document_number'], $name ) );
		return true;
	}

	/**
	 * Change status for inactive lifecycle operations.
	 *
	 * @param int    $document_id Document ID.
	 * @param string $status New status.
	 * @return bool|WP_Error
	 */
	public static function change_document_status( $document_id, $status ) {
		global $wpdb;
		$document = self::get( $document_id );
		if ( ! $document ) {
			return new WP_Error( 'gwr_document_not_found', __( 'Documento non trovato.', 'gest-web-rent' ) );
		}
		if ( 'invalidated' === $status && 'signed' === $document['status'] ) {
			return new WP_Error( 'gwr_signed_document_immutable', __( 'Un documento firmato non viene invalidato: genera una nuova versione e archivia questa se necessario.', 'gest-web-rent' ) );
		}
		$field = 'archived' === $status ? 'archived_at' : 'invalidated_at';
		$wpdb->update( self::table(), array( 'status' => sanitize_key( $status ), $field => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $document_id ) ) );
		GWR_Bookings::add_system_log( $document['booking_id'], 'document_' . sanitize_key( $status ), sprintf( __( 'Documento %s aggiornato a %s.', 'gest-web-rent' ), $document['document_number'], $status ) );
		return true;
	}

	/**
	 * Validate normalized data.
	 *
	 * @param array $data Data.
	 * @return true|WP_Error
	 */
	private static function validate_document_data( $data ) {
		$required = array(
			$data['booking']['code'] ?? '',
			$data['customer']['name'] ?? '',
			$data['customer']['email'] ?? '',
			$data['vehicle']['title'] ?? '',
			$data['booking']['pickup_datetime'] ?? '',
			$data['booking']['return_datetime'] ?? '',
			$data['pricing']['total'] ?? '',
		);
		foreach ( $required as $value ) {
			if ( '' === trim( (string) $value ) ) {
				return new WP_Error( 'gwr_document_missing_data', __( 'Dati prenotazione insufficienti per generare il documento.', 'gest-web-rent' ) );
			}
		}
		return true;
	}

	/**
	 * Stream document using cached HTML or snapshot fallback.
	 *
	 * @param array $document Document.
	 * @param bool  $download Download flag.
	 * @return void
	 */
	private static function stream( $document, $download ) {
		$html = '';
		if ( empty( $document['file_path'] ) || ! file_exists( $document['file_path'] ) ) {
			$snapshot = $document['snapshot'] ?? array();
			$data = $snapshot['data'] ?? array();
			if ( $data ) {
				$html = GWR_Document_Template_Service::render(
					$document['document_type'],
					$data,
					self::get_settings(),
					array(
						'document_number' => $document['document_number'],
						'version' => $document['version'],
						'generated_at' => GWR_Bookings::format_datetime( $document['generated_at'] ),
						'content_hash' => $document['content_hash'],
					)
				);
			}
		}
		GWR_PDF_Service::stream_html( $document, $html, $download );
	}

	/**
	 * Authorize admin, signed URL or booking public token.
	 *
	 * @param array $document Document.
	 * @return bool
	 */
	private static function authorize_access( $document ) {
		if ( current_user_can( 'manage_options' ) ) {
			if ( ! empty( $_GET['_wpnonce'] ) ) {
				$action = sanitize_key( $_GET['action'] ?? '' );
				return (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $action . '_' . absint( $document['id'] ) );
			}
			return true;
		}
		$expires = absint( $_GET['gwr_expires'] ?? 0 );
		$sig = sanitize_text_field( wp_unslash( $_GET['gwr_signature'] ?? '' ) );
		if ( $expires && $sig && $expires >= time() && hash_equals( self::signature_for_url( $document, $expires ), $sig ) ) {
			return true;
		}
		$code = sanitize_text_field( wp_unslash( $_GET['booking_code'] ?? '' ) );
		$token = sanitize_text_field( wp_unslash( $_GET['gwr_token'] ?? ( $_GET['booking_token'] ?? '' ) ) );
		$booking = GWR_Bookings::get( $document['booking_id'] );
		if ( ! $booking || $code !== $booking['booking_code'] || ! GWR_Bookings::verify_public_token( $booking, $token ) ) {
			return false;
		}
		$type = self::document_types()[ $document['document_type'] ] ?? array();
		return ! empty( $type['public'] ) && ! in_array( $document['status'], array( 'invalidated', 'archived' ), true );
	}

	/**
	 * Requested document by ID.
	 *
	 * @return array|null
	 */
	private static function requested_document() {
		$document_id = absint( $_GET['document_id'] ?? 0 );
		return $document_id ? self::get( $document_id ) : null;
	}

	private static function signature_for_url( $document, $expires ) {
		return hash_hmac( 'sha256', absint( $document['id'] ) . '|' . absint( $expires ) . '|' . ( $document['file_hash'] ?? '' ), wp_salt( 'auth' ) );
	}

	private static function document_number( $booking, $type, $version ) {
		$types = self::document_types();
		$prefix = $types[ $type ]['prefix'] ?? 'DOC';
		$year = wp_date( 'Y' );
		$base = strtoupper( sanitize_key( $booking['booking_code'] ) );
		$number = 'GWR-' . $prefix . '-' . $year . '-' . $base;
		if ( absint( $version ) > 1 ) {
			$number .= '-V' . absint( $version );
		}
		return $number;
	}

	private static function settings_hash( $settings ) {
		$settings = $settings;
		unset( $settings['logo_id'] );
		return substr( hash( 'sha256', wp_json_encode( $settings ) ), 0, 16 );
	}

	private static function hydrate( $row ) {
		$row['snapshot'] = ! empty( $row['snapshot_json'] ) ? json_decode( (string) $row['snapshot_json'], true ) : array();
		if ( ! is_array( $row['snapshot'] ) ) {
			$row['snapshot'] = array();
		}
		return $row;
	}

	private static function full_name( $data ) {
		return trim( sanitize_text_field( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) ) );
	}

	private static function address_line( $data ) {
		return trim( implode( ', ', array_filter( array_map( 'sanitize_text_field', array(
			$data['address'] ?? '',
			trim( (string) ( $data['postal_code'] ?? '' ) . ' ' . ( $data['city'] ?? '' ) ),
			$data['province'] ?? '',
			$data['country'] ?? '',
		) ) ) ) );
	}

	private static function included_km_label( $vehicle, $booking ) {
		if ( ! $vehicle ) {
			return '';
		}
		$daily = absint( $vehicle['included_km_daily'] ?? 0 );
		$weekly = absint( $vehicle['included_km_weekly'] ?? 0 );
		$monthly = absint( $vehicle['included_km_monthly'] ?? 0 );
		$parts = array();
		if ( $daily ) {
			$parts[] = $daily . ' km/giorno';
		}
		if ( $weekly ) {
			$parts[] = $weekly . ' km/settimana';
		}
		if ( $monthly ) {
			$parts[] = $monthly . ' km/mese';
		}
		return implode( ' - ', $parts );
	}

	private static function normalize_items( $items, $currency ) {
		$output = array();
		foreach ( $items as $item ) {
			$output[] = array(
				'name' => sanitize_text_field( $item['item_name'] ?? '' ),
				'type' => sanitize_key( $item['item_type'] ?? '' ),
				'quantity' => (float) ( $item['quantity'] ?? 0 ),
				'total' => GWR_Bookings::format_money( $item['total_price'] ?? 0, $currency ),
			);
		}
		return $output;
	}

	private static function normalize_payment( $payment, $booking ) {
		if ( ! $payment ) {
			return array(
				'method' => sanitize_key( $booking['payment_method'] ?? '' ),
				'status' => sanitize_key( $booking['payment_status'] ?? '' ),
				'amount' => GWR_Bookings::format_money( $booking['pay_now_amount'] ?? 0, $booking['currency'] ?? 'EUR' ),
			);
		}
		return array(
			'method' => sanitize_key( $payment['provider'] ?? '' ),
			'status' => sanitize_key( $payment['status'] ?? '' ),
			'payment_type' => sanitize_key( $payment['payment_type'] ?? '' ),
			'amount' => class_exists( 'GWR_Money' ) ? GWR_Money::format_minor( $payment['amount'] ?? 0, $payment['currency'] ?? 'EUR' ) : GWR_Bookings::format_money( ( (float) ( $payment['amount'] ?? 0 ) ) / 100, $payment['currency'] ?? 'EUR' ),
			'attempt' => absint( $payment['attempt'] ?? 0 ),
			'updated_at' => GWR_Bookings::format_datetime( $payment['updated_at'] ?? '' ),
		);
	}

	private static function normalize_operation( $operation ) {
		$operation = is_array( $operation ) ? $operation : array();
		return array(
			'effective_datetime' => GWR_Bookings::format_datetime( $operation['effective_datetime'] ?? '' ),
			'odometer' => sanitize_text_field( $operation['odometer'] ?? '' ),
			'fuel_level' => sanitize_text_field( $operation['fuel_level'] ?? '' ),
			'deposit_verified' => ! empty( $operation['deposit_verified'] ) ? __( 'Si', 'gest-web-rent' ) : '',
			'damage' => sanitize_textarea_field( $operation['damage'] ?? '' ),
			'delay_minutes' => sanitize_text_field( $operation['delay_minutes'] ?? '' ),
			'notes' => sanitize_textarea_field( $operation['notes'] ?? '' ),
		);
	}

	private static function require_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) );
		}
	}

	private static function redirect_to_booking( $booking_id, $result, $success_message ) {
		$error = is_wp_error( $result );
		$message = $error ? $result->get_error_message() : $success_message;
		wp_safe_redirect( add_query_arg( array( 'page' => 'gwr-booking-detail', 'booking_id' => absint( $booking_id ), 'gwr_message' => $message, 'gwr_error' => $error ? 1 : 0 ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
