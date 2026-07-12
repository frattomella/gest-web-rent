<?php
/**
 * Custom storage for Gest Web Rent vehicles.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Vehicle storage, images and availability.
 *
 * The class name is kept for backward compatibility with previous internal
 * includes, but the main experience no longer uses a WordPress CPT.
 */
class GWR_CPT {
	const DB_VERSION = '1.7.0';
	const POST_TYPE  = 'gwr_vehicle';

	/**
	 * Register lightweight runtime hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// Custom tables are managed on activation/admin_init. No public CPT UI is registered.
	}

	/**
	 * Vehicles table name.
	 *
	 * @return string
	 */
	public static function vehicles_table() {
		global $wpdb;

		return $wpdb->prefix . 'gwr_vehicles';
	}

	/**
	 * Vehicle images table name.
	 *
	 * @return string
	 */
	public static function images_table() {
		global $wpdb;

		return $wpdb->prefix . 'gwr_vehicle_images';
	}

	/**
	 * Availability table name.
	 *
	 * @return string
	 */
	public static function availability_table() {
		global $wpdb;

		return $wpdb->prefix . 'gwr_availability';
	}

	/**
	 * Ensure custom storage exists and legacy data is migrated.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_storage() {
		if ( get_option( 'gwr_db_version' ) === self::DB_VERSION ) {
			return;
		}

		self::create_tables();
		self::maybe_migrate_cpt_vehicles();
		update_option( 'gwr_db_version', self::DB_VERSION, false );
	}

	/**
	 * Create all custom tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$vehicles = self::vehicles_table();
		$images = self::images_table();
		$availability = self::availability_table();

		$sql_vehicles = "CREATE TABLE {$vehicles} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(190) NOT NULL,
			brand varchar(120) NULL,
			model varchar(120) NULL,
			version varchar(190) NULL,
			category varchar(120) NULL,
			year smallint unsigned NULL,
			fuel varchar(80) NULL,
			transmission varchar(80) NULL,
			seats tinyint unsigned NULL,
			doors tinyint unsigned NULL,
			color varchar(80) NULL,
			location varchar(190) NULL,
			daily_price decimal(10,2) NULL,
			weekend_price decimal(10,2) NULL,
			weekly_price decimal(10,2) NULL,
			monthly_price decimal(10,2) NULL,
			minimum_price decimal(10,2) NULL,
			hourly_price decimal(10,2) NULL,
			vehicle_tax_rate decimal(5,2) NULL,
			price_list_code varchar(80) NULL,
			tariff_band varchar(80) NULL,
			pricing_priority int NOT NULL DEFAULT 0,
			price_on_request tinyint(1) NOT NULL DEFAULT 0,
			deposit decimal(10,2) NULL,
			deductible varchar(190) NULL,
			included_km_daily int unsigned NULL,
			included_km_weekly int unsigned NULL,
			included_km_monthly int unsigned NULL,
			extra_km_price decimal(10,2) NULL,
			min_driver_age tinyint unsigned NULL,
			min_license_years tinyint unsigned NULL,
			required_license varchar(50) NULL,
			min_rental_days tinyint unsigned NULL,
			max_rental_days smallint unsigned NULL,
			insurance_included tinyint(1) NOT NULL DEFAULT 0,
			second_driver_included tinyint(1) NOT NULL DEFAULT 0,
			home_delivery tinyint(1) NOT NULL DEFAULT 0,
			description longtext NULL,
			rental_notes longtext NULL,
			features longtext NULL,
			status varchar(30) NOT NULL DEFAULT 'active',
			featured tinyint(1) NOT NULL DEFAULT 0,
			sort_order int NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY featured (featured),
			KEY sort_order (sort_order),
			KEY brand (brand),
			KEY category (category)
		) {$charset};";

		$sql_images = "CREATE TABLE {$images} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			vehicle_id bigint(20) unsigned NOT NULL,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			image_url text NULL,
			is_cover tinyint(1) NOT NULL DEFAULT 0,
			sort_order int NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY vehicle_id (vehicle_id),
			KEY is_cover (is_cover),
			KEY sort_order (sort_order)
		) {$charset};";

		$sql_availability = "CREATE TABLE {$availability} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			vehicle_id bigint(20) unsigned NOT NULL,
			start_date date NOT NULL,
			end_date date NOT NULL,
			status varchar(30) NOT NULL,
			internal_note text NULL,
			external_reference varchar(190) NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY vehicle_id (vehicle_id),
			KEY date_range (start_date,end_date),
			KEY status (status)
		) {$charset};";

		dbDelta( $sql_vehicles );
		dbDelta( $sql_images );
		dbDelta( $sql_availability );
	}

	/**
	 * Backward-compatible alias used by older activation code.
	 *
	 * @return void
	 */
	public static function create_availability_table() {
		self::create_tables();
	}

	/**
	 * Legacy no-op kept for old wrappers.
	 *
	 * @return void
	 */
	public static function register_post_types() {}

	/**
	 * Legacy no-op kept for old wrappers.
	 *
	 * @return void
	 */
	public static function register_taxonomies() {}

	/**
	 * Vehicle statuses for admin and filters.
	 *
	 * @return array
	 */
	public static function vehicle_statuses() {
		return array(
			'active'      => __( 'Attivo', 'gest-web-rent' ),
			'draft'       => __( 'Bozza', 'gest-web-rent' ),
			'unavailable' => __( 'Non disponibile', 'gest-web-rent' ),
			'maintenance' => __( 'In manutenzione', 'gest-web-rent' ),
		);
	}

	/**
	 * Availability statuses. Every status means unavailable on frontend.
	 *
	 * @return array
	 */
	public static function availability_statuses() {
		return array(
			'busy'        => __( 'Occupato', 'gest-web-rent' ),
			'maintenance' => __( 'Manutenzione', 'gest-web-rent' ),
			'reserved'    => __( 'Riservato', 'gest-web-rent' ),
			'unavailable' => __( 'Non disponibile', 'gest-web-rent' ),
		);
	}

	/**
	 * Feature checkbox options.
	 *
	 * @return array
	 */
	public static function feature_options() {
		return array(
			'air_conditioning' => __( 'Climatizzatore', 'gest-web-rent' ),
			'navigation'       => __( 'Navigatore', 'gest-web-rent' ),
			'bluetooth'        => __( 'Bluetooth', 'gest-web-rent' ),
			'apple_carplay'    => __( 'Apple CarPlay', 'gest-web-rent' ),
			'android_auto'     => __( 'Android Auto', 'gest-web-rent' ),
			'parking_sensors'  => __( 'Sensori parcheggio', 'gest-web-rent' ),
			'rear_camera'      => __( 'Telecamera posteriore', 'gest-web-rent' ),
			'cruise_control'   => __( 'Cruise control', 'gest-web-rent' ),
			'abs'              => __( 'ABS', 'gest-web-rent' ),
			'esp'              => __( 'ESP', 'gest-web-rent' ),
			'airbag'           => __( 'Airbag', 'gest-web-rent' ),
			'winter_tires'     => __( 'Pneumatici invernali', 'gest-web-rent' ),
			'child_seat'       => __( 'Seggiolino bambini', 'gest-web-rent' ),
			'snow_chains'      => __( 'Catene neve', 'gest-web-rent' ),
			'usb'              => __( 'USB', 'gest-web-rent' ),
			'keyless'          => __( 'Keyless', 'gest-web-rent' ),
		);
	}

	/**
	 * Empty vehicle defaults.
	 *
	 * @return array
	 */
	public static function empty_vehicle() {
		return array(
			'id'                     => 0,
			'title'                  => '',
			'brand'                  => '',
			'model'                  => '',
			'version'                => '',
			'category'               => '',
			'year'                   => '',
			'fuel'                   => '',
			'transmission'           => '',
			'seats'                  => '',
			'doors'                  => '',
			'color'                  => '',
			'location'               => '',
			'daily_price'            => '',
			'weekend_price'          => '',
			'weekly_price'           => '',
			'monthly_price'          => '',
			'minimum_price'          => '',
			'hourly_price'           => '',
			'vehicle_tax_rate'       => '',
			'price_list_code'        => '',
			'tariff_band'            => '',
			'pricing_priority'       => 0,
			'price_on_request'       => 0,
			'deposit'                => '',
			'deductible'             => '',
			'included_km_daily'      => '',
			'included_km_weekly'     => '',
			'included_km_monthly'    => '',
			'extra_km_price'         => '',
			'min_driver_age'         => '',
			'min_license_years'      => '',
			'required_license'       => '',
			'min_rental_days'        => '',
			'max_rental_days'        => '',
			'insurance_included'     => 0,
			'second_driver_included' => 0,
			'home_delivery'          => 0,
			'description'            => '',
			'rental_notes'           => '',
			'features'               => '[]',
			'status'                 => 'active',
			'featured'               => 0,
			'sort_order'             => 0,
			'created_at'             => '',
			'updated_at'             => '',
		);
	}

	/**
	 * Sanitize vehicle form data.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_vehicle_data( $input ) {
		$input = is_array( $input ) ? wp_unslash( $input ) : array();
		$statuses = self::vehicle_statuses();
		$features = array();

		if ( isset( $input['features'] ) && is_array( $input['features'] ) ) {
			foreach ( array_keys( self::feature_options() ) as $feature_key ) {
				if ( in_array( $feature_key, array_map( 'sanitize_key', $input['features'] ), true ) ) {
					$features[] = $feature_key;
				}
			}
		}

		$status = isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'active';
		if ( ! array_key_exists( $status, $statuses ) ) {
			$status = 'active';
		}

		return array(
			'title'                  => isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '',
			'brand'                  => isset( $input['brand'] ) ? sanitize_text_field( $input['brand'] ) : '',
			'model'                  => isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : '',
			'version'                => isset( $input['version'] ) ? sanitize_text_field( $input['version'] ) : '',
			'category'               => isset( $input['category'] ) ? sanitize_text_field( $input['category'] ) : '',
			'year'                   => isset( $input['year'] ) ? absint( $input['year'] ) : 0,
			'fuel'                   => isset( $input['fuel'] ) ? sanitize_text_field( $input['fuel'] ) : '',
			'transmission'           => isset( $input['transmission'] ) ? sanitize_text_field( $input['transmission'] ) : '',
			'seats'                  => isset( $input['seats'] ) ? absint( $input['seats'] ) : 0,
			'doors'                  => isset( $input['doors'] ) ? absint( $input['doors'] ) : 0,
			'color'                  => isset( $input['color'] ) ? sanitize_text_field( $input['color'] ) : '',
			'location'               => isset( $input['location'] ) ? sanitize_text_field( $input['location'] ) : '',
			'daily_price'            => self::sanitize_decimal( $input['daily_price'] ?? '' ),
			'weekend_price'          => self::sanitize_decimal( $input['weekend_price'] ?? '' ),
			'weekly_price'           => self::sanitize_decimal( $input['weekly_price'] ?? '' ),
			'monthly_price'          => self::sanitize_decimal( $input['monthly_price'] ?? '' ),
			'minimum_price'          => self::sanitize_decimal( $input['minimum_price'] ?? '' ),
			'hourly_price'           => self::sanitize_decimal( $input['hourly_price'] ?? '' ),
			'vehicle_tax_rate'       => self::sanitize_decimal( $input['vehicle_tax_rate'] ?? '' ),
			'price_list_code'        => isset( $input['price_list_code'] ) ? strtoupper( preg_replace( '/[^A-Z0-9_-]/', '', sanitize_text_field( $input['price_list_code'] ) ) ) : '',
			'tariff_band'            => isset( $input['tariff_band'] ) ? sanitize_text_field( $input['tariff_band'] ) : '',
			'pricing_priority'       => isset( $input['pricing_priority'] ) ? intval( $input['pricing_priority'] ) : 0,
			'price_on_request'       => ! empty( $input['price_on_request'] ) ? 1 : 0,
			'deposit'                => self::sanitize_decimal( $input['deposit'] ?? '' ),
			'deductible'             => isset( $input['deductible'] ) ? sanitize_text_field( $input['deductible'] ) : '',
			'included_km_daily'      => isset( $input['included_km_daily'] ) ? absint( $input['included_km_daily'] ) : 0,
			'included_km_weekly'     => isset( $input['included_km_weekly'] ) ? absint( $input['included_km_weekly'] ) : 0,
			'included_km_monthly'    => isset( $input['included_km_monthly'] ) ? absint( $input['included_km_monthly'] ) : 0,
			'extra_km_price'         => self::sanitize_decimal( $input['extra_km_price'] ?? '' ),
			'min_driver_age'         => isset( $input['min_driver_age'] ) ? absint( $input['min_driver_age'] ) : 0,
			'min_license_years'      => isset( $input['min_license_years'] ) ? absint( $input['min_license_years'] ) : 0,
			'required_license'       => isset( $input['required_license'] ) ? sanitize_text_field( $input['required_license'] ) : '',
			'min_rental_days'        => isset( $input['min_rental_days'] ) ? absint( $input['min_rental_days'] ) : 0,
			'max_rental_days'        => isset( $input['max_rental_days'] ) ? absint( $input['max_rental_days'] ) : 0,
			'insurance_included'     => ! empty( $input['insurance_included'] ) ? 1 : 0,
			'second_driver_included' => ! empty( $input['second_driver_included'] ) ? 1 : 0,
			'home_delivery'          => ! empty( $input['home_delivery'] ) ? 1 : 0,
			'description'            => isset( $input['description'] ) ? wp_kses_post( $input['description'] ) : '',
			'rental_notes'           => isset( $input['rental_notes'] ) ? sanitize_textarea_field( $input['rental_notes'] ) : '',
			'features'               => wp_json_encode( $features ),
			'status'                 => $status,
			'featured'               => ! empty( $input['featured'] ) ? 1 : 0,
			'sort_order'             => isset( $input['sort_order'] ) ? intval( $input['sort_order'] ) : 0,
		);
	}

	/**
	 * Save vehicle.
	 *
	 * @param array $input Raw form data.
	 * @param int   $vehicle_id Vehicle ID.
	 * @return int|WP_Error
	 */
	public static function save_vehicle( $input, $vehicle_id = 0 ) {
		global $wpdb;

		$data = self::sanitize_vehicle_data( $input );
		if ( '' === $data['title'] ) {
			return new WP_Error( 'gwr_missing_title', __( 'Inserisci il titolo veicolo.', 'gest-web-rent' ) );
		}

		$now = current_time( 'mysql' );
		$table = self::vehicles_table();
		$formats = self::vehicle_formats();
		$vehicle_id = absint( $vehicle_id );

		if ( $vehicle_id ) {
			$data['updated_at'] = $now;
			$formats[] = '%s';
			$wpdb->update( $table, $data, array( 'id' => $vehicle_id ), $formats, array( '%d' ) );
			return $vehicle_id;
		}

		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		$formats[] = '%s';
		$formats[] = '%s';
		$wpdb->insert( $table, $data, $formats );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Vehicle data formats.
	 *
	 * @return array
	 */
	private static function vehicle_formats() {
		return array(
			'%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s',
			'%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%d', '%d', '%f', '%s', '%d', '%d', '%d', '%f', '%d', '%d',
			'%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d',
		);
	}

	/**
	 * Get one vehicle.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return array|null
	 */
	public static function get_vehicle( $vehicle_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::vehicles_table() . ' WHERE id = %d',
				absint( $vehicle_id )
			),
			ARRAY_A
		);

		return $row ? array_replace( self::empty_vehicle(), $row ) : null;
	}

	/**
	 * Get vehicles with filters.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function get_vehicles( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'status'       => '',
				'frontend'     => false,
				'search'       => '',
				'category'     => '',
				'brand'        => '',
				'fuel'         => '',
				'transmission' => '',
				'location'     => '',
				'max_price'    => 0,
				'seats'        => 0,
				'start_date'   => '',
				'end_date'     => '',
				'limit'        => 0,
			)
		);

		$table = self::vehicles_table();
		$availability = self::availability_table();
		$where = array();
		$values = array();

		if ( $args['frontend'] ) {
			$where[] = "v.status = 'active'";
		} elseif ( $args['status'] ) {
			$where[] = 'v.status = %s';
			$values[] = sanitize_key( $args['status'] );
		}

		if ( '' !== (string) $args['search'] ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[] = '(v.title LIKE %s OR v.brand LIKE %s OR v.model LIKE %s OR v.version LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		foreach ( array( 'category', 'brand', 'fuel', 'transmission', 'location' ) as $field ) {
			if ( '' !== (string) $args[ $field ] ) {
				$where[] = "v.{$field} = %s";
				$values[] = sanitize_text_field( $args[ $field ] );
			}
		}

		if ( absint( $args['seats'] ) ) {
			$where[] = 'v.seats >= %d';
			$values[] = absint( $args['seats'] );
		}

		if ( (float) $args['max_price'] > 0 ) {
			$where[] = 'v.daily_price <= %f';
			$values[] = (float) $args['max_price'];
		}

		$date_range = self::normalize_date_range( $args['start_date'], $args['end_date'] );
		if ( ! is_wp_error( $date_range ) && ! empty( $date_range['start_date'] ) ) {
			$where[] = "NOT EXISTS (
				SELECT 1 FROM {$availability} a
				WHERE a.vehicle_id = v.id
				AND a.start_date <= %s
				AND a.end_date >= %s
			)";
			$values[] = $date_range['end_date'];
			$values[] = $date_range['start_date'];
		}

		$sql = "SELECT v.* FROM {$table} v";
		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY v.featured DESC, v.sort_order ASC, v.created_at DESC';

		if ( absint( $args['limit'] ) ) {
			$sql .= ' LIMIT %d';
			$values[] = absint( $args['limit'] );
		}

		if ( $values ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete a vehicle and related rows.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return void
	 */
	public static function delete_vehicle( $vehicle_id ) {
		global $wpdb;

		$vehicle_id = absint( $vehicle_id );
		$wpdb->delete( self::vehicles_table(), array( 'id' => $vehicle_id ), array( '%d' ) );
		$wpdb->delete( self::images_table(), array( 'vehicle_id' => $vehicle_id ), array( '%d' ) );
		$wpdb->delete( self::availability_table(), array( 'vehicle_id' => $vehicle_id ), array( '%d' ) );
	}

	/**
	 * Duplicate a vehicle.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return int
	 */
	public static function duplicate_vehicle( $vehicle_id ) {
		$vehicle = self::get_vehicle( $vehicle_id );
		if ( ! $vehicle ) {
			return 0;
		}

		unset( $vehicle['id'], $vehicle['created_at'], $vehicle['updated_at'] );
		$vehicle['title'] .= ' - copia';
		$vehicle['status'] = 'draft';
		$vehicle['features'] = self::vehicle_features( $vehicle );

		$new_id = self::save_vehicle( $vehicle, 0 );
		if ( is_wp_error( $new_id ) || ! $new_id ) {
			return 0;
		}

		$images = self::get_vehicle_images( $vehicle_id );
		$image_ids = array();
		$cover_id = 0;
		foreach ( $images as $image ) {
			if ( ! empty( $image['attachment_id'] ) ) {
				$image_ids[] = absint( $image['attachment_id'] );
				if ( ! empty( $image['is_cover'] ) ) {
					$cover_id = absint( $image['attachment_id'] );
				}
			}
		}
		self::save_vehicle_images( $new_id, $image_ids, $cover_id );

		return (int) $new_id;
	}

	/**
	 * Save vehicle image ordering.
	 *
	 * @param int   $vehicle_id Vehicle ID.
	 * @param array $image_ids Attachment IDs.
	 * @param int   $cover_id Cover attachment ID.
	 * @return void
	 */
	public static function save_vehicle_images( $vehicle_id, $image_ids, $cover_id = 0 ) {
		global $wpdb;

		$vehicle_id = absint( $vehicle_id );
		$cover_id = absint( $cover_id );
		$image_ids = is_array( $image_ids ) ? array_map( 'absint', $image_ids ) : array();
		$image_ids = array_values( array_unique( array_filter( $image_ids ) ) );

		$wpdb->delete( self::images_table(), array( 'vehicle_id' => $vehicle_id ), array( '%d' ) );

		if ( empty( $image_ids ) ) {
			return;
		}

		if ( ! in_array( $cover_id, $image_ids, true ) ) {
			$cover_id = $image_ids[0];
		}

		foreach ( $image_ids as $index => $attachment_id ) {
			$wpdb->insert(
				self::images_table(),
				array(
					'vehicle_id'    => $vehicle_id,
					'attachment_id' => $attachment_id,
					'image_url'     => '',
					'is_cover'      => $attachment_id === $cover_id ? 1 : 0,
					'sort_order'    => $index,
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%d', '%d', '%s' )
			);
		}
	}

	/**
	 * Get image rows.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return array
	 */
	public static function get_vehicle_images( $vehicle_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::images_table() . ' WHERE vehicle_id = %d ORDER BY is_cover DESC, sort_order ASC, id ASC',
				absint( $vehicle_id )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get public image data.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return array
	 */
	public static function get_vehicle_image_data( $vehicle_id ) {
		$items = array();
		foreach ( self::get_vehicle_images( $vehicle_id ) as $image ) {
			$url = '';
			if ( ! empty( $image['attachment_id'] ) ) {
				$url = wp_get_attachment_image_url( absint( $image['attachment_id'] ), 'large' );
			}
			if ( ! $url && ! empty( $image['image_url'] ) ) {
				$url = esc_url_raw( $image['image_url'] );
			}
			if ( $url ) {
				$items[] = array(
					'id'       => absint( $image['attachment_id'] ),
					'url'      => $url,
					'is_cover' => ! empty( $image['is_cover'] ),
				);
			}
		}

		return $items;
	}

	/**
	 * Cover image URL.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return string
	 */
	public static function cover_image_url( $vehicle_id ) {
		$images = self::get_vehicle_image_data( $vehicle_id );

		return $images ? $images[0]['url'] : '';
	}

	/**
	 * Save all availability rows for one vehicle.
	 *
	 * @param int   $vehicle_id Vehicle ID.
	 * @param array $rows Rows.
	 * @return void
	 */
	public static function save_availability_rows( $vehicle_id, $rows ) {
		global $wpdb;

		$vehicle_id = absint( $vehicle_id );
		$wpdb->delete( self::availability_table(), array( 'vehicle_id' => $vehicle_id ), array( '%d' ) );

		if ( ! is_array( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			self::save_availability( $vehicle_id, $row );
		}
	}

	/**
	 * Save one availability row.
	 *
	 * @param int   $vehicle_id Vehicle ID.
	 * @param array $row Row.
	 * @param int   $row_id Existing row ID.
	 * @return int|WP_Error
	 */
	public static function save_availability( $vehicle_id, $row, $row_id = 0 ) {
		global $wpdb;

		$clean = self::sanitize_availability_row( $row );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		$vehicle_id = absint( $vehicle_id );
		$row_id = absint( $row_id );
		$now = current_time( 'mysql' );
		$data = array(
			'vehicle_id'          => $vehicle_id,
			'start_date'          => $clean['start_date'],
			'end_date'            => $clean['end_date'],
			'status'              => $clean['status'],
			'internal_note'       => $clean['internal_note'],
			'external_reference'  => $clean['external_reference'],
			'updated_at'          => $now,
		);
		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $row_id ) {
			$wpdb->update( self::availability_table(), $data, array( 'id' => $row_id, 'vehicle_id' => $vehicle_id ), $formats, array( '%d', '%d' ) );
			return $row_id;
		}

		$data['created_at'] = $now;
		$formats[] = '%s';
		$wpdb->insert( self::availability_table(), $data, $formats );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete availability row.
	 *
	 * @param int $row_id Row ID.
	 * @return void
	 */
	public static function delete_availability( $row_id ) {
		global $wpdb;

		$wpdb->delete( self::availability_table(), array( 'id' => absint( $row_id ) ), array( '%d' ) );
	}

	/**
	 * Get availability rows.
	 *
	 * @param int  $vehicle_id Vehicle ID.
	 * @param bool $future_only Future rows only.
	 * @param int  $limit Limit.
	 * @return array
	 */
	public static function availability_rows( $vehicle_id = 0, $future_only = false, $limit = 0 ) {
		global $wpdb;

		$where = array();
		$values = array();
		if ( $vehicle_id ) {
			$where[] = 'a.vehicle_id = %d';
			$values[] = absint( $vehicle_id );
		}
		if ( $future_only ) {
			$where[] = 'a.end_date >= %s';
			$values[] = current_time( 'Y-m-d' );
		}

		$sql = 'SELECT a.*, v.title AS vehicle_title FROM ' . self::availability_table() . ' a LEFT JOIN ' . self::vehicles_table() . ' v ON v.id = a.vehicle_id';
		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY a.start_date ASC, a.end_date ASC';
		if ( absint( $limit ) ) {
			$sql .= ' LIMIT %d';
			$values[] = absint( $limit );
		}
		if ( $values ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Upcoming availability rows.
	 *
	 * @param int|null $vehicle_id Optional vehicle ID.
	 * @param int      $limit Limit.
	 * @return array
	 */
	public static function upcoming_availability( $vehicle_id = null, $limit = 20 ) {
		return self::availability_rows( $vehicle_id ? absint( $vehicle_id ) : 0, true, absint( $limit ) );
	}

	/**
	 * Check if a vehicle is available in a date range.
	 *
	 * @param int    $vehicle_id Vehicle ID.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return bool
	 */
	public static function is_available( $vehicle_id, $start_date = '', $end_date = '' ) {
		global $wpdb;

		$range = self::normalize_date_range( $start_date, $end_date );
		if ( is_wp_error( $range ) || empty( $range['start_date'] ) ) {
			return true;
		}

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::availability_table() . ' WHERE vehicle_id = %d AND start_date <= %s AND end_date >= %s',
				absint( $vehicle_id ),
				$range['end_date'],
				$range['start_date']
			)
		);

		return 0 === $count;
	}

	/**
	 * Sanitize one availability row.
	 *
	 * @param array $row Row.
	 * @return array|WP_Error
	 */
	public static function sanitize_availability_row( $row ) {
		$row = is_array( $row ) ? wp_unslash( $row ) : array();
		$start_date = $row['start_date'] ?? ( $row['date_from'] ?? '' );
		$end_date = $row['end_date'] ?? ( $row['date_to'] ?? '' );
		$range = self::normalize_date_range( $start_date, $end_date );
		if ( is_wp_error( $range ) || empty( $range['start_date'] ) ) {
			return new WP_Error( 'gwr_invalid_dates', __( 'Date non valide.', 'gest-web-rent' ) );
		}

		$status = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'busy';
		if ( 'occupied' === $status || 'booked' === $status ) {
			$status = 'busy';
		}
		if ( ! array_key_exists( $status, self::availability_statuses() ) ) {
			$status = 'busy';
		}

		return array(
			'start_date'         => $range['start_date'],
			'end_date'           => $range['end_date'],
			'status'             => $status,
			'internal_note'      => isset( $row['internal_note'] ) ? sanitize_text_field( $row['internal_note'] ) : '',
			'external_reference' => isset( $row['external_reference'] ) ? sanitize_text_field( $row['external_reference'] ) : '',
		);
	}

	/**
	 * Normalize dates.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array|WP_Error
	 */
	public static function normalize_date_range( $start_date, $end_date ) {
		$start_date = sanitize_text_field( (string) $start_date );
		$end_date = sanitize_text_field( (string) $end_date );

		if ( '' === $start_date && '' === $end_date ) {
			return array( 'start_date' => '', 'end_date' => '' );
		}

		if ( '' === $start_date ) {
			$start_date = $end_date;
		}
		if ( '' === $end_date ) {
			$end_date = $start_date;
		}
		if ( ! self::is_valid_date( $start_date ) || ! self::is_valid_date( $end_date ) ) {
			return new WP_Error( 'gwr_invalid_date', __( 'Inserisci date valide.', 'gest-web-rent' ) );
		}
		if ( $end_date < $start_date ) {
			return new WP_Error( 'gwr_date_order', __( 'La data fine non puo precedere la data inizio.', 'gest-web-rent' ) );
		}

		return array( 'start_date' => $start_date, 'end_date' => $end_date );
	}

	/**
	 * Validate Y-m-d date.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	public static function is_valid_date( $date ) {
		$parsed = date_create_from_format( 'Y-m-d', (string) $date );

		return $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Dashboard counts.
	 *
	 * @return array
	 */
	public static function counts() {
		global $wpdb;

		$table = self::vehicles_table();
		$availability = self::availability_table();

		return array(
			'total'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
			'active'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" ),
			'featured'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE featured = 1" ),
			'availability' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$availability} WHERE end_date >= %s", current_time( 'Y-m-d' ) ) ),
		);
	}

	/**
	 * Distinct filter values.
	 *
	 * @param string $field Field name.
	 * @return array
	 */
	public static function filter_values( $field ) {
		global $wpdb;

		$allowed = array( 'category', 'brand', 'fuel', 'transmission', 'location' );
		if ( ! in_array( $field, $allowed, true ) ) {
			return array();
		}

		$sql = "SELECT DISTINCT {$field} FROM " . self::vehicles_table() . " WHERE status = 'active' AND {$field} <> '' ORDER BY {$field} ASC";
		$values = $wpdb->get_col( $sql );

		return is_array( $values ) ? array_map( 'sanitize_text_field', $values ) : array();
	}

	/**
	 * Decode feature list.
	 *
	 * @param array $vehicle Vehicle.
	 * @return array
	 */
	public static function vehicle_features( $vehicle ) {
		$features = json_decode( (string) ( $vehicle['features'] ?? '' ), true );

		return is_array( $features ) ? array_values( array_intersect( array_keys( self::feature_options() ), array_map( 'sanitize_key', $features ) ) ) : array();
	}

	/**
	 * Public vehicle payload for modal.
	 *
	 * @param array $vehicle Vehicle row.
	 * @param bool  $extended Include commercial terms used only by the modal.
	 * @return array
	 */
	public static function public_payload( $vehicle, $extended = true ) {
		$features = array();
		foreach ( self::vehicle_features( $vehicle ) as $feature ) {
			$features[] = self::feature_options()[ $feature ];
		}

		$payload = array(
			'id'                     => absint( $vehicle['id'] ),
			'title'                  => $vehicle['title'],
			'brand'                  => $vehicle['brand'],
			'model'                  => $vehicle['model'],
			'version'                => $vehicle['version'],
			'category'               => $vehicle['category'],
			'year'                   => $vehicle['year'],
			'fuel'                   => $vehicle['fuel'],
			'transmission'           => $vehicle['transmission'],
			'seats'                  => $vehicle['seats'],
			'doors'                  => $vehicle['doors'],
			'color'                  => $vehicle['color'],
			'location'               => $vehicle['location'],
			'daily_price'            => self::format_price( $vehicle['daily_price'] ),
			'weekend_price'          => self::format_price( $vehicle['weekend_price'] ),
			'weekly_price'           => self::format_price( $vehicle['weekly_price'] ),
			'monthly_price'          => self::format_price( $vehicle['monthly_price'] ),
			'minimum_price'          => self::format_price( $vehicle['minimum_price'] ?? '' ),
			'hourly_price'           => self::format_price( $vehicle['hourly_price'] ?? '' ),
			'vehicle_tax_rate'       => $vehicle['vehicle_tax_rate'] ?? '',
			'price_list_code'        => $vehicle['price_list_code'] ?? '',
			'tariff_band'            => $vehicle['tariff_band'] ?? '',
			'pricing_priority'       => $vehicle['pricing_priority'] ?? 0,
			'price_on_request'       => ! empty( $vehicle['price_on_request'] ),
			'deposit'                => self::format_price( $vehicle['deposit'] ),
			'deductible'             => $vehicle['deductible'],
			'included_km_daily'      => $vehicle['included_km_daily'],
			'included_km_weekly'     => $vehicle['included_km_weekly'],
			'included_km_monthly'    => $vehicle['included_km_monthly'],
			'extra_km_price'         => self::format_price( $vehicle['extra_km_price'] ),
			'min_driver_age'         => $vehicle['min_driver_age'],
			'min_license_years'      => $vehicle['min_license_years'],
			'required_license'       => $vehicle['required_license'],
			'min_rental_days'        => $vehicle['min_rental_days'],
			'max_rental_days'        => $vehicle['max_rental_days'],
			'insurance_included'     => ! empty( $vehicle['insurance_included'] ),
			'second_driver_included' => ! empty( $vehicle['second_driver_included'] ),
			'home_delivery'          => ! empty( $vehicle['home_delivery'] ),
			'description'            => wp_kses_post( $vehicle['description'] ),
			'rental_notes'           => nl2br( esc_html( $vehicle['rental_notes'] ) ),
			'features'               => $features,
			'featured'               => ! empty( $vehicle['featured'] ),
			'images'                 => self::get_vehicle_image_data( $vehicle['id'] ),
			'daily_price_amount'     => null === $vehicle['daily_price'] || '' === $vehicle['daily_price'] ? null : (float) $vehicle['daily_price'],
		);
		if ( $extended && class_exists( 'GWR_Rental_Terms' ) ) {
			$payload = array_merge( $payload, GWR_Rental_Terms::public_payload( absint( $vehicle['id'] ), $vehicle ) );
		}

		return $payload;
	}

	/**
	 * Format price for display.
	 *
	 * @param mixed $price Price.
	 * @return string
	 */
	public static function format_price( $price ) {
		if ( '' === (string) $price || null === $price || (float) $price <= 0 ) {
			return '';
		}

		return 'EUR ' . number_format_i18n( (float) $price, 2 );
	}

	/**
	 * Migrate legacy CPT data once.
	 *
	 * @return void
	 */
	public static function maybe_migrate_cpt_vehicles() {
		global $wpdb;

		if ( get_option( 'gwr_cpt_migrated_to_tables_110' ) ) {
			return;
		}

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			$post_id = absint( $post_id );
			$legacy_availability = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT start_date, end_date, status, internal_note, external_reference FROM ' . self::availability_table() . ' WHERE vehicle_id = %d',
					$post_id
				),
				ARRAY_A
			);
			$wpdb->delete( self::availability_table(), array( 'vehicle_id' => $post_id ), array( '%d' ) );

			$data = array(
				'title'                  => get_the_title( $post_id ),
				'brand'                  => get_post_meta( $post_id, '_gwr_brand', true ),
				'model'                  => get_post_meta( $post_id, '_gwr_model', true ),
				'version'                => get_post_meta( $post_id, '_gwr_version', true ),
				'category'               => get_post_meta( $post_id, '_gwr_category', true ),
				'year'                   => get_post_meta( $post_id, '_gwr_year', true ),
				'fuel'                   => get_post_meta( $post_id, '_gwr_fuel', true ),
				'transmission'           => get_post_meta( $post_id, '_gwr_transmission', true ),
				'seats'                  => get_post_meta( $post_id, '_gwr_seats', true ),
				'doors'                  => get_post_meta( $post_id, '_gwr_doors', true ),
				'color'                  => get_post_meta( $post_id, '_gwr_color', true ),
				'location'               => get_post_meta( $post_id, '_gwr_location', true ),
				'daily_price'            => get_post_meta( $post_id, '_gwr_price_day', true ),
				'weekend_price'          => get_post_meta( $post_id, '_gwr_price_weekend', true ),
				'weekly_price'           => get_post_meta( $post_id, '_gwr_price_week', true ),
				'monthly_price'          => get_post_meta( $post_id, '_gwr_price_month', true ),
				'deposit'                => get_post_meta( $post_id, '_gwr_deposit', true ),
				'deductible'             => get_post_meta( $post_id, '_gwr_deductible', true ),
				'included_km_daily'      => get_post_meta( $post_id, '_gwr_max_km_day', true ),
				'included_km_weekly'     => get_post_meta( $post_id, '_gwr_max_km_week', true ),
				'included_km_monthly'    => get_post_meta( $post_id, '_gwr_max_km_month', true ),
				'extra_km_price'         => get_post_meta( $post_id, '_gwr_extra_km_price', true ),
				'min_driver_age'         => get_post_meta( $post_id, '_gwr_min_age', true ),
				'min_license_years'      => get_post_meta( $post_id, '_gwr_min_license_years', true ),
				'required_license'       => get_post_meta( $post_id, '_gwr_license_required', true ),
				'min_rental_days'        => get_post_meta( $post_id, '_gwr_min_rental_days', true ),
				'max_rental_days'        => get_post_meta( $post_id, '_gwr_max_rental_days', true ),
				'insurance_included'     => get_post_meta( $post_id, '_gwr_insurance_included', true ),
				'second_driver_included' => get_post_meta( $post_id, '_gwr_second_driver', true ),
				'home_delivery'          => get_post_meta( $post_id, '_gwr_home_delivery', true ),
				'description'            => get_post_field( 'post_content', $post_id ),
				'rental_notes'           => get_post_meta( $post_id, '_gwr_rental_notes', true ),
				'status'                 => 'publish' === get_post_status( $post_id ) ? 'active' : 'draft',
				'featured'               => get_post_meta( $post_id, '_gwr_featured', true ),
			);

			$new_id = self::save_vehicle( $data );
			if ( is_wp_error( $new_id ) || ! $new_id ) {
				continue;
			}

			$thumb_id = get_post_thumbnail_id( $post_id );
			if ( $thumb_id ) {
				self::save_vehicle_images( $new_id, array( $thumb_id ), $thumb_id );
			}

			$availability = get_post_meta( $post_id, '_gwr_availability', true );
			if ( is_array( $availability ) ) {
				self::save_availability_rows( $new_id, $availability );
			} elseif ( is_array( $legacy_availability ) && ! empty( $legacy_availability ) ) {
				self::save_availability_rows( $new_id, $legacy_availability );
			}
		}

		update_option( 'gwr_cpt_migrated_to_tables_110', current_time( 'mysql' ), false );
	}

	/**
	 * Sanitize decimal number.
	 *
	 * @param mixed $value Value.
	 * @return float|null
	 */
	private static function sanitize_decimal( $value ) {
		if ( '' === (string) $value ) {
			return null;
		}

		return (float) str_replace( ',', '.', sanitize_text_field( (string) $value ) );
	}
}
