<?php
/**
 * Rental terms storage, validation and inheritance.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Centralized commercial terms service.
 */
class GWR_Rental_Terms {
	const GLOBAL_OPTION   = 'gwr_rental_terms';
	const VEHICLE_OPTION  = 'gwr_vehicle_rental_terms';
	const DATA_VERSION    = 1;

	/** Ensure options exist on both fresh and upgraded installations. */
	public static function maybe_initialize() {
		if ( false === get_option( self::GLOBAL_OPTION, false ) ) {
			add_option( self::GLOBAL_OPTION, self::defaults(), '', false );
		}
		if ( false === get_option( self::VEHICLE_OPTION, false ) ) {
			add_option( self::VEHICLE_OPTION, array(), '', false );
		}
	}

	/**
	 * Sections that can be inherited independently.
	 *
	 * @return array
	 */
	public static function section_labels() {
		return array(
		'general'                => __( 'Generali', 'gest-web-rent' ),
		'included_services'      => __( 'Servizi inclusi', 'gest-web-rent' ),
		'excluded_services'      => __( 'Servizi esclusi', 'gest-web-rent' ),
		'insurance_coverages'    => __( 'Assicurazioni', 'gest-web-rent' ),
		'excesses'               => __( 'Franchigie', 'gest-web-rent' ),
		'security_deposit'       => __( 'Deposito', 'gest-web-rent' ),
		'driver_requirements'    => __( 'Conducente', 'gest-web-rent' ),
		'required_documents'     => __( 'Documenti', 'gest-web-rent' ),
		'fuel_policy'            => __( 'Carburante', 'gest-web-rent' ),
		'mileage_policy'         => __( 'Chilometraggio', 'gest-web-rent' ),
		'payment_policy'         => __( 'Pagamento', 'gest-web-rent' ),
		'cancellation_policy'    => __( 'Cancellazione', 'gest-web-rent' ),
		'pickup_return_policy'   => __( 'Ritiro e riconsegna', 'gest-web-rent' ),
		'territorial_policy'     => __( 'Territorio', 'gest-web-rent' ),
		'extras'                 => __( 'Extra', 'gest-web-rent' ),
		'faq'                    => __( 'FAQ', 'gest-web-rent' ),
		);
	}

	/**
	 * Empty normalized terms.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = array( '_version' => self::DATA_VERSION );
		foreach ( array_keys( self::section_labels() ) as $section ) {
			$defaults[ $section ] = in_array( $section, self::repeater_sections(), true ) ? array() : array();
		}

		return $defaults;
	}

	/**
	 * Get validated global terms.
	 *
	 * @return array
	 */
	public static function global_terms() {
		$value = get_option( self::GLOBAL_OPTION, array() );

		return self::sanitize_terms( is_array( $value ) ? $value : array() );
	}

	/**
	 * Save global terms.
	 *
	 * @param array $input Raw terms.
	 * @return array
	 */
	public static function save_global_terms( $input ) {
		$terms = self::sanitize_terms( $input );
		update_option( self::GLOBAL_OPTION, $terms, false );

		return $terms;
	}

	/**
	 * Get all vehicle overrides in one option read.
	 *
	 * @return array
	 */
	public static function vehicle_override_map() {
		$value = get_option( self::VEHICLE_OPTION, array() );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Get one vehicle override.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return array
	 */
	public static function vehicle_override( $vehicle_id ) {
		$map = self::vehicle_override_map();
		$value = $map[ absint( $vehicle_id ) ] ?? array();
		$inherit = array();
		foreach ( array_keys( self::section_labels() ) as $section ) {
			$inherit[ $section ] = ! isset( $value['inherit'][ $section ] ) || ! empty( $value['inherit'][ $section ] );
		}

		return array(
			'inherit' => $inherit,
			'terms'   => self::sanitize_terms( $value['terms'] ?? array() ),
		);
	}

	/**
	 * Save one vehicle override.
	 *
	 * @param int   $vehicle_id Vehicle ID.
	 * @param array $input Raw override.
	 * @return void
	 */
	public static function save_vehicle_override( $vehicle_id, $input ) {
		$vehicle_id = absint( $vehicle_id );
		if ( ! $vehicle_id ) {
			return;
		}

		$input = is_array( $input ) ? wp_unslash( $input ) : array();
		$is_new = empty( $input['inherit'] ) && empty( $input['terms'] );
		$inherit = array();
		foreach ( array_keys( self::section_labels() ) as $section ) {
			$inherit[ $section ] = $is_new || ! empty( $input['inherit'][ $section ] );
		}

		$map = self::vehicle_override_map();
		$map[ $vehicle_id ] = array(
			'inherit' => $inherit,
			'terms'   => self::sanitize_terms( $input['terms'] ?? array() ),
		);
		update_option( self::VEHICLE_OPTION, $map, false );
	}

	/**
	 * Delete overrides when a vehicle is deleted.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return void
	 */
	public static function delete_vehicle_override( $vehicle_id ) {
		$map = self::vehicle_override_map();
		unset( $map[ absint( $vehicle_id ) ] );
		update_option( self::VEHICLE_OPTION, $map, false );
	}

	/**
	 * Duplicate overrides with the vehicle.
	 *
	 * @param int $source_id Source vehicle.
	 * @param int $target_id Target vehicle.
	 * @return void
	 */
	public static function duplicate_vehicle_override( $source_id, $target_id ) {
		$map = self::vehicle_override_map();
		if ( isset( $map[ absint( $source_id ) ] ) ) {
			$map[ absint( $target_id ) ] = $map[ absint( $source_id ) ];
			update_option( self::VEHICLE_OPTION, $map, false );
		}
	}

	/**
	 * Resolve global terms and per-vehicle overrides.
	 *
	 * @param int        $vehicle_id Vehicle ID.
	 * @param array|null $vehicle Vehicle row for legacy field fallback.
	 * @return array
	 */
	public static function effective_terms( $vehicle_id, $vehicle = null ) {
		$global = self::global_terms();
		$override = self::vehicle_override( $vehicle_id );
		$effective = self::defaults();

		foreach ( array_keys( self::section_labels() ) as $section ) {
			$effective[ $section ] = ! empty( $override['inherit'][ $section ] )
				? $global[ $section ]
				: $override['terms'][ $section ];
		}

		if ( ! is_array( $vehicle ) && class_exists( 'GWR_CPT' ) ) {
			$vehicle = GWR_CPT::get_vehicle( $vehicle_id );
		}
		$effective = self::apply_vehicle_fallbacks( $effective, is_array( $vehicle ) ? $vehicle : array() );

		return self::public_terms( $effective, $vehicle_id, sanitize_text_field( $vehicle['category'] ?? '' ) );
	}

	/**
	 * Public payload with no inactive or internal-only values.
	 *
	 * @param int   $vehicle_id Vehicle ID.
	 * @param array $vehicle Vehicle row.
	 * @return array
	 */
	public static function public_payload( $vehicle_id, $vehicle = array() ) {
		$terms = self::effective_terms( $vehicle_id, $vehicle );
		$extras = $terms['extras'];
		unset( $terms['extras'], $terms['_version'] );

		return array(
			'rental_terms' => self::remove_empty( $terms ),
			'extras'       => array_values( $extras ),
		);
	}

	/**
	 * Validate the entire terms structure.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_terms( $input ) {
		$input = is_array( $input ) ? wp_unslash( $input ) : array();
		$output = self::defaults();
		$output['general'] = self::sanitize_fields( $input['general'] ?? array(), self::field_schema()['general'] );

		foreach ( self::repeater_sections() as $section ) {
			$output[ $section ] = self::sanitize_repeater( $input[ $section ] ?? array(), self::repeater_schema()[ $section ] );
		}
		foreach ( self::field_schema() as $section => $schema ) {
			if ( 'general' !== $section ) {
				$output[ $section ] = self::sanitize_fields( $input[ $section ] ?? array(), $schema );
			}
		}

		self::normalize_dependencies( $output );

		return $output;
	}

	/**
	 * Field sections schema shared with the admin renderer.
	 *
	 * @return array
	 */
	public static function field_schema() {
		return array(
			'general' => array(
				'title' => 'text', 'intro' => 'textarea', 'rental_name' => 'text', 'currency' => 'currency',
				'taxes' => 'taxes', 'terms_url' => 'url', 'general_note' => 'textarea', 'updated_at' => 'date',
			),
			'excesses' => array(
				'damage_amount' => 'decimal', 'damage_percent' => 'percent', 'theft_amount' => 'decimal', 'theft_percent' => 'percent',
				'fire_amount' => 'decimal', 'glass_amount' => 'decimal', 'tyres_amount' => 'decimal', 'generic_amount' => 'decimal',
				'reduction_amount' => 'decimal', 'minimum' => 'decimal', 'maximum' => 'decimal', 'description' => 'textarea', 'conditions' => 'textarea', 'active' => 'bool',
			),
			'security_deposit' => array(
				'required' => 'bool', 'amount' => 'decimal', 'currency' => 'currency', 'method' => 'deposit_method', 'card_required' => 'bool',
				'card_holder_required' => 'bool', 'card_networks' => 'text', 'debit_cards_allowed' => 'bool', 'block_timing' => 'text',
				'release_timing' => 'text', 'description' => 'textarea', 'conditions' => 'textarea', 'operational_notes' => 'internal_textarea',
			),
			'driver_requirements' => array(
				'min_age' => 'integer', 'max_age' => 'integer', 'min_license_years' => 'integer', 'international_license' => 'bool',
				'eu_license' => 'bool', 'young_driver_surcharge' => 'bool', 'senior_driver_surcharge' => 'bool', 'surcharge_cost' => 'decimal',
				'surcharge_mode' => 'price_mode', 'main_driver_required' => 'bool', 'additional_drivers' => 'bool', 'max_drivers' => 'integer',
				'description' => 'textarea', 'notes' => 'textarea',
			),
			'fuel_policy' => array(
				'type' => 'fuel_type', 'minimum_level' => 'integer', 'charge_percent' => 'integer', 'missing_fuel_cost' => 'decimal',
				'refuel_service_cost' => 'decimal', 'description' => 'textarea', 'notes' => 'textarea',
			),
			'mileage_policy' => array(
				'type' => 'mileage_type', 'included_km' => 'integer', 'extra_km_cost' => 'decimal', 'currency' => 'currency',
				'territorial_limits' => 'text', 'foreign_use' => 'bool', 'motorway_use' => 'bool', 'description' => 'textarea', 'notes' => 'textarea',
			),
			'payment_policy' => array(
				'type' => 'payment_type', 'deposit_percent' => 'percent', 'deposit_amount' => 'decimal', 'balance_timing' => 'text',
				'accepted_methods' => 'text', 'card_required' => 'bool', 'bank_transfer' => 'bool', 'cash' => 'bool', 'online_payment' => 'bool',
				'description' => 'textarea', 'notes' => 'textarea',
			),
			'cancellation_policy' => array(
				'allowed' => 'bool', 'free' => 'bool', 'hours_limit' => 'integer', 'days_limit' => 'integer', 'fixed_penalty' => 'decimal',
				'percent_penalty' => 'percent', 'no_show' => 'textarea', 'date_changes' => 'bool', 'vehicle_changes' => 'bool',
				'refund' => 'bool', 'refund_timing' => 'text', 'description' => 'textarea', 'notes' => 'textarea',
			),
			'pickup_return_policy' => array(
				'pickup_mode' => 'text', 'return_mode' => 'text', 'location_name' => 'text', 'address' => 'text', 'instructions' => 'textarea',
				'contact_name' => 'text', 'phone' => 'phone', 'email' => 'email', 'appointment_only' => 'bool', 'after_hours_return' => 'bool',
				'after_hours_cost' => 'decimal', 'late_tolerance_minutes' => 'integer', 'late_cost' => 'decimal', 'home_delivery' => 'bool',
				'delivery_cost' => 'decimal', 'notes' => 'textarea',
			),
			'territorial_policy' => array(
				'national_use' => 'bool', 'foreign_use' => 'bool', 'allowed_countries' => 'text', 'forbidden_countries' => 'text',
				'authorization_required' => 'bool', 'authorization_cost' => 'decimal', 'ferries' => 'bool', 'islands' => 'bool',
				'motorway' => 'bool', 'unpaved_roads' => 'bool', 'professional_use' => 'bool', 'sports_use' => 'bool',
				'towing' => 'bool', 'sublease' => 'bool', 'description' => 'textarea', 'notes' => 'textarea',
			),
		);
	}

	/**
	 * Repeater sections schema.
	 *
	 * @return array
	 */
	public static function repeater_schema() {
		return array(
			'included_services' => array( 'id' => 'key', 'title' => 'text', 'description' => 'textarea', 'icon' => 'icon', 'note' => 'textarea', 'active' => 'bool', 'sort_order' => 'integer' ),
			'excluded_services' => array( 'id' => 'key', 'title' => 'text', 'description' => 'textarea', 'cost' => 'decimal', 'unit' => 'text', 'note' => 'textarea', 'active' => 'bool', 'sort_order' => 'integer' ),
			'insurance_coverages' => array( 'id' => 'key', 'name' => 'text', 'code' => 'key', 'type' => 'coverage_type', 'description' => 'textarea', 'status' => 'coverage_status', 'cost' => 'decimal', 'price_mode' => 'price_mode', 'excess' => 'decimal', 'maximum' => 'text', 'deposit_required' => 'bool', 'conditions' => 'textarea', 'exclusions' => 'textarea', 'active' => 'bool', 'sort_order' => 'integer' ),
			'required_documents' => array( 'id' => 'key', 'name' => 'text', 'description' => 'textarea', 'required' => 'bool', 'at_pickup' => 'bool', 'at_booking' => 'bool', 'private_customer' => 'bool', 'business_customer' => 'bool', 'active' => 'bool', 'sort_order' => 'integer' ),
			'extras' => array( 'id' => 'key', 'name' => 'text', 'code' => 'key', 'description' => 'textarea', 'category' => 'extra_category', 'price' => 'decimal', 'currency' => 'currency', 'price_mode' => 'extra_price_mode', 'min_quantity' => 'integer', 'max_quantity' => 'integer', 'available' => 'bool', 'available_quantity' => 'integer', 'mandatory' => 'bool', 'default_selected' => 'bool', 'taxable' => 'bool', 'all_vehicles' => 'bool', 'vehicle_ids' => 'id_list', 'vehicle_categories' => 'text', 'icon' => 'icon', 'active' => 'bool', 'sort_order' => 'integer' ),
			'faq' => array( 'id' => 'key', 'question' => 'text', 'answer' => 'html', 'category' => 'faq_category', 'active' => 'bool', 'sort_order' => 'integer' ),
		);
	}

	/**
	 * Select choices for schema types.
	 *
	 * @return array
	 */
	public static function choices() {
		return array(
			'currency' => array( 'EUR' => 'EUR', 'USD' => 'USD', 'GBP' => 'GBP', 'CHF' => 'CHF' ),
			'taxes' => array( '' => __( 'Non indicato', 'gest-web-rent' ), 'included' => __( 'Tasse incluse', 'gest-web-rent' ), 'excluded' => __( 'Tasse escluse', 'gest-web-rent' ) ),
			'price_mode' => array( 'per_day' => __( 'Per giorno', 'gest-web-rent' ), 'per_rental' => __( 'Per noleggio', 'gest-web-rent' ), 'percentage' => __( 'Percentuale', 'gest-web-rent' ) ),
			'extra_price_mode' => array( 'per_day' => __( 'Per giorno', 'gest-web-rent' ), 'per_rental' => __( 'Per noleggio', 'gest-web-rent' ), 'per_unit' => __( 'Per unita', 'gest-web-rent' ), 'free' => __( 'Gratuito', 'gest-web-rent' ) ),
			'coverage_status' => array( 'included' => __( 'Inclusa', 'gest-web-rent' ), 'optional' => __( 'Opzionale', 'gest-web-rent' ), 'unavailable' => __( 'Non disponibile', 'gest-web-rent' ) ),
			'coverage_type' => array( 'rca' => 'RCA', 'theft' => __( 'Furto', 'gest-web-rent' ), 'fire' => __( 'Incendio', 'gest-web-rent' ), 'damage' => __( 'Danni', 'gest-web-rent' ), 'kasko' => 'Kasko', 'glass' => __( 'Cristalli', 'gest-web-rent' ), 'tyres' => __( 'Pneumatici', 'gest-web-rent' ), 'natural_events' => __( 'Eventi naturali', 'gest-web-rent' ), 'vandalism' => __( 'Atti vandalici', 'gest-web-rent' ), 'roadside' => __( 'Assistenza', 'gest-web-rent' ), 'driver_injury' => __( 'Infortuni conducente', 'gest-web-rent' ) ),
			'deposit_method' => array( 'preauthorization' => __( 'Preautorizzazione', 'gest-web-rent' ), 'charge' => __( 'Addebito', 'gest-web-rent' ), 'cash' => __( 'Contanti', 'gest-web-rent' ), 'bank_transfer' => __( 'Bonifico', 'gest-web-rent' ), 'other' => __( 'Altra', 'gest-web-rent' ) ),
			'fuel_type' => array( 'full_full' => __( 'Pieno / pieno', 'gest-web-rent' ), 'full_empty' => __( 'Pieno / vuoto', 'gest-web-rent' ), 'same_level' => __( 'Stesso livello', 'gest-web-rent' ), 'included' => __( 'Carburante incluso', 'gest-web-rent' ), 'minimum' => __( 'Livello minimo', 'gest-web-rent' ), 'electric' => __( 'Elettrico', 'gest-web-rent' ), 'custom' => __( 'Personalizzata', 'gest-web-rent' ) ),
			'mileage_type' => array( 'unlimited' => __( 'Illimitato', 'gest-web-rent' ), 'daily' => __( 'Giornaliero', 'gest-web-rent' ), 'total' => __( 'Totale', 'gest-web-rent' ), 'custom' => __( 'Personalizzato', 'gest-web-rent' ) ),
			'payment_type' => array( 'advance' => __( 'Anticipato', 'gest-web-rent' ), 'pickup' => __( 'Al ritiro', 'gest-web-rent' ), 'partial' => __( 'Parziale', 'gest-web-rent' ), 'request' => __( 'Su richiesta', 'gest-web-rent' ) ),
			'extra_category' => array( 'safety' => __( 'Sicurezza', 'gest-web-rent' ), 'comfort' => __( 'Comfort', 'gest-web-rent' ), 'driver' => __( 'Conducente', 'gest-web-rent' ), 'delivery' => __( 'Consegna', 'gest-web-rent' ), 'mileage' => __( 'Chilometraggio', 'gest-web-rent' ), 'insurance' => __( 'Assicurazione', 'gest-web-rent' ), 'accessories' => __( 'Accessori', 'gest-web-rent' ), 'other' => __( 'Altro', 'gest-web-rent' ) ),
			'faq_category' => array( 'payment' => __( 'Pagamento', 'gest-web-rent' ), 'deposit' => __( 'Deposito', 'gest-web-rent' ), 'fuel' => __( 'Carburante', 'gest-web-rent' ), 'mileage' => __( 'Chilometraggio', 'gest-web-rent' ), 'documents' => __( 'Documenti', 'gest-web-rent' ), 'cancellation' => __( 'Cancellazione', 'gest-web-rent' ), 'insurance' => __( 'Assicurazioni', 'gest-web-rent' ), 'pickup' => __( 'Ritiro', 'gest-web-rent' ), 'other' => __( 'Altro', 'gest-web-rent' ) ),
			'icon' => array( '' => __( 'Nessuna', 'gest-web-rent' ), 'check' => __( 'Spunta', 'gest-web-rent' ), 'shield' => __( 'Scudo', 'gest-web-rent' ), 'road' => __( 'Strada', 'gest-web-rent' ), 'driver' => __( 'Conducente', 'gest-web-rent' ), 'location' => __( 'Posizione', 'gest-web-rent' ), 'document' => __( 'Documento', 'gest-web-rent' ), 'plus' => __( 'Extra', 'gest-web-rent' ) ),
		);
	}

	/** @return array */
	private static function repeater_sections() {
		return array( 'included_services', 'excluded_services', 'insurance_coverages', 'required_documents', 'extras', 'faq' );
	}

	/**
	 * Sanitize scalar fields from schema.
	 *
	 * @param array $input Input.
	 * @param array $schema Schema.
	 * @return array
	 */
	private static function sanitize_fields( $input, $schema ) {
		$input = is_array( $input ) ? $input : array();
		$output = array();
		foreach ( $schema as $key => $type ) {
			$output[ $key ] = self::sanitize_value( $input[ $key ] ?? '', $type );
		}

		return $output;
	}

	/**
	 * Sanitize repeatable rows and ensure stable unique IDs.
	 *
	 * @param array $rows Rows.
	 * @param array $schema Schema.
	 * @return array
	 */
	private static function sanitize_repeater( $rows, $schema ) {
		$rows = is_array( $rows ) ? $rows : array();
		$output = array();
		$used_ids = array();
		foreach ( $rows as $index => $row ) {
			$row = self::sanitize_fields( is_array( $row ) ? $row : array(), $schema );
			$label = $row['title'] ?? ( $row['name'] ?? ( $row['question'] ?? '' ) );
			if ( '' === trim( (string) $label ) ) {
				continue;
			}
			$id = sanitize_key( $row['id'] ?? '' );
			if ( ! $id || isset( $used_ids[ $id ] ) ) {
				$id = 'gwr_' . substr( md5( $label . '|' . $index . '|' . wp_generate_uuid4() ), 0, 12 );
			}
			$row['id'] = $id;
			$row['sort_order'] = count( $output );
			$used_ids[ $id ] = true;
			$output[] = $row;
		}

		return $output;
	}

	/**
	 * Sanitize one value.
	 *
	 * @param mixed  $value Value.
	 * @param string $type Type.
	 * @return mixed
	 */
	private static function sanitize_value( $value, $type ) {
		if ( 'bool' === $type ) {
			return ! empty( $value );
		}
		if ( 'integer' === $type ) {
			return '' === $value ? null : max( 0, absint( $value ) );
		}
		if ( in_array( $type, array( 'decimal', 'percent' ), true ) ) {
			if ( '' === $value || null === $value ) {
				return null;
			}
			$number = (float) str_replace( ',', '.', sanitize_text_field( $value ) );
			return 'percent' === $type ? min( 100, max( 0, $number ) ) : max( 0, round( $number, 2 ) );
		}
		if ( 'url' === $type ) {
			return esc_url_raw( $value );
		}
		if ( 'email' === $type ) {
			return sanitize_email( $value );
		}
		if ( 'phone' === $type ) {
			return preg_replace( '/[^0-9+ ]/', '', sanitize_text_field( $value ) );
		}
		if ( 'textarea' === $type || 'internal_textarea' === $type ) {
			return sanitize_textarea_field( $value );
		}
		if ( 'html' === $type ) {
			return wp_kses_post( $value );
		}
		if ( 'id_list' === $type ) {
			$values = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
			return array_values( array_unique( array_filter( array_map( 'absint', $values ) ) ) );
		}
		if ( 'key' === $type ) {
			return sanitize_key( $value );
		}
		if ( isset( self::choices()[ $type ] ) ) {
			$key = sanitize_key( $value );
			return array_key_exists( $key, self::choices()[ $type ] ) ? $key : '';
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Enforce cross-field consistency.
	 *
	 * @param array $terms Terms by reference.
	 * @return void
	 */
	private static function normalize_dependencies( &$terms ) {
		$driver = &$terms['driver_requirements'];
		if ( $driver['min_age'] && $driver['max_age'] && $driver['max_age'] <= $driver['min_age'] ) {
			$driver['max_age'] = null;
		}
		if ( ! $driver['young_driver_surcharge'] && ! $driver['senior_driver_surcharge'] ) {
			$driver['surcharge_cost'] = null;
			$driver['surcharge_mode'] = '';
		}
		if ( 'unlimited' === $terms['mileage_policy']['type'] ) {
			$terms['mileage_policy']['included_km'] = null;
			$terms['mileage_policy']['extra_km_cost'] = null;
		}
		if ( ! $terms['security_deposit']['required'] ) {
			$terms['security_deposit']['amount'] = null;
		}
		if ( ! $terms['cancellation_policy']['allowed'] ) {
			$terms['cancellation_policy']['free'] = false;
		}
		foreach ( $terms['extras'] as &$extra ) {
			if ( 'free' === $extra['price_mode'] ) {
				$extra['price'] = 0.0;
			}
			$extra['min_quantity'] = max( $extra['mandatory'] ? 1 : 0, (int) $extra['min_quantity'] );
			$extra['max_quantity'] = max( $extra['min_quantity'], (int) $extra['max_quantity'] );
		}
		unset( $extra );
	}

	/**
	 * Preserve existing vehicle commercial fields when no structured values exist.
	 *
	 * @param array $terms Terms.
	 * @param array $vehicle Vehicle.
	 * @return array
	 */
	private static function apply_vehicle_fallbacks( $terms, $vehicle ) {
		if ( empty( array_filter( $terms['security_deposit'] ) ) && ! empty( $vehicle['deposit'] ) ) {
			$terms['security_deposit'] = array( 'required' => true, 'amount' => (float) $vehicle['deposit'], 'currency' => 'EUR' );
		}
		if ( empty( array_filter( $terms['excesses'] ) ) && ! empty( $vehicle['deductible'] ) ) {
			$terms['excesses'] = array( 'description' => sanitize_text_field( $vehicle['deductible'] ), 'active' => true );
		}
		if ( empty( array_filter( $terms['driver_requirements'] ) ) ) {
			$terms['driver_requirements'] = self::remove_empty( array(
				'min_age' => absint( $vehicle['min_driver_age'] ?? 0 ),
				'min_license_years' => absint( $vehicle['min_license_years'] ?? 0 ),
				'description' => ! empty( $vehicle['required_license'] ) ? sprintf( __( 'Patente richiesta: %s', 'gest-web-rent' ), sanitize_text_field( $vehicle['required_license'] ) ) : '',
			) );
		}
		if ( empty( array_filter( $terms['mileage_policy'] ) ) ) {
			$included = absint( $vehicle['included_km_daily'] ?? 0 );
			$terms['mileage_policy'] = self::remove_empty( array(
				'type' => $included ? 'daily' : '', 'included_km' => $included,
				'extra_km_cost' => isset( $vehicle['extra_km_price'] ) ? (float) $vehicle['extra_km_price'] : null, 'currency' => 'EUR',
			) );
		}
		if ( empty( $terms['included_services'] ) ) {
			$legacy = array();
			if ( ! empty( $vehicle['insurance_included'] ) ) {
				$legacy[] = array( 'id' => 'legacy_insurance', 'title' => __( 'Assicurazione inclusa', 'gest-web-rent' ), 'active' => true, 'sort_order' => 0 );
			}
			if ( ! empty( $vehicle['second_driver_included'] ) ) {
				$legacy[] = array( 'id' => 'legacy_second_driver', 'title' => __( 'Secondo conducente incluso', 'gest-web-rent' ), 'active' => true, 'sort_order' => 1 );
			}
			$terms['included_services'] = $legacy;
		}
		if ( empty( array_filter( $terms['pickup_return_policy'] ) ) ) {
			$terms['pickup_return_policy'] = self::remove_empty( array(
				'location_name' => sanitize_text_field( $vehicle['location'] ?? '' ),
				'home_delivery' => ! empty( $vehicle['home_delivery'] ),
			) );
		}

		return $terms;
	}

	/**
	 * Prepare active, ordered, public terms.
	 *
	 * @param array $terms Terms.
	 * @param int    $vehicle_id Vehicle ID.
	 * @param string $vehicle_category Vehicle category.
	 * @return array
	 */
	private static function public_terms( $terms, $vehicle_id, $vehicle_category = '' ) {
		foreach ( self::repeater_sections() as $section ) {
			$rows = array_filter( $terms[ $section ], function( $row ) use ( $section, $vehicle_id, $vehicle_category ) {
				if ( empty( $row['active'] ) ) {
					return false;
				}
				if ( 'extras' === $section ) {
					if ( empty( $row['available'] ) ) {
						return false;
					}
					$categories = array_filter( array_map( 'trim', explode( ',', (string) ( $row['vehicle_categories'] ?? '' ) ) ) );
					$category_match = $vehicle_category && in_array( strtolower( $vehicle_category ), array_map( 'strtolower', $categories ), true );
					if ( empty( $row['all_vehicles'] ) && ! in_array( absint( $vehicle_id ), $row['vehicle_ids'] ?? array(), true ) && ! $category_match ) {
						return false;
					}
				}
				return true;
			} );
			usort( $rows, function( $a, $b ) { return (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 ); } );
			$terms[ $section ] = array_map( array( __CLASS__, 'prepare_public_row' ), array_values( $rows ) );
		}

		foreach ( $terms as $section => &$value ) {
			if ( is_array( $value ) && ! in_array( $section, self::repeater_sections(), true ) ) {
				unset( $value['operational_notes'] );
				$value = self::remove_empty( $value );
			}
		}
		unset( $value );

		return $terms;
	}

	/** @param array $row Row. @return array */
	private static function prepare_public_row( $row ) {
		unset( $row['vehicle_ids'], $row['vehicle_categories'], $row['all_vehicles'], $row['available_quantity'], $row['sort_order'], $row['active'] );
		foreach ( array( 'price', 'cost', 'excess' ) as $price_key ) {
			if ( isset( $row[ $price_key ] ) && is_numeric( $row[ $price_key ] ) ) {
				$row[ $price_key . '_minor' ] = (int) round( (float) $row[ $price_key ] * 100 );
			}
		}

		return self::remove_empty( $row );
	}

	/**
	 * Recursively remove empty public values while retaining false and zero.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function remove_empty( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$output = array();
		foreach ( $value as $key => $item ) {
			$item = self::remove_empty( $item );
			if ( null === $item || '' === $item || ( is_array( $item ) && empty( $item ) ) ) {
				continue;
			}
			$output[ $key ] = $item;
		}

		return $output;
	}
}

/**
 * Public helper for themes and integrations.
 *
 * @param int $vehicle_id Vehicle ID.
 * @return array
 */
function gwr_get_effective_rental_terms( $vehicle_id ) {
	return GWR_Rental_Terms::effective_terms( absint( $vehicle_id ) );
}
