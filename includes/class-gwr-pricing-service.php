<?php
/**
 * Central pricing engine, price-list/rule repositories and coupon service.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Professional rental pricing engine.
 */
class GWR_Pricing_Service {
	const DB_VERSION  = '1.7.0';
	const DB_OPTION   = 'gwr_pricing_db_version';
	const OPTION_NAME = 'gwr_economic_settings';
	const VERSION     = '2026.07-phase6';

	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
	}

	/**
	 * Price lists table.
	 *
	 * @return string
	 */
	public static function price_lists_table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_price_lists';
	}

	/**
	 * Generic pricing rules table.
	 *
	 * @return string
	 */
	public static function rules_table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_pricing_rules';
	}

	/**
	 * Coupons table.
	 *
	 * @return string
	 */
	public static function coupons_table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_coupons';
	}

	/**
	 * Coupon usages table.
	 *
	 * @return string
	 */
	public static function coupon_usages_table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_coupon_usages';
	}

	/**
	 * Run idempotent migrations.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}

		self::create_tables();
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::default_settings(), '', false );
		}
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Create pricing and coupon tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$lists   = self::price_lists_table();
		$rules   = self::rules_table();
		$coupons = self::coupons_table();
		$usages  = self::coupon_usages_table();

		$sql_lists = "CREATE TABLE {$lists} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			code varchar(80) NOT NULL,
			description text NULL,
			currency char(3) NOT NULL DEFAULT 'EUR',
			tax_rate decimal(5,2) NULL,
			start_date date NULL,
			end_date date NULL,
			active tinyint(1) NOT NULL DEFAULT 0,
			priority int NOT NULL DEFAULT 0,
			scope_type varchar(30) NOT NULL DEFAULT 'global',
			scope_ids text NULL,
			min_days smallint unsigned NULL,
			max_days smallint unsigned NULL,
			config_json longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY active (active),
			KEY date_range (start_date,end_date),
			KEY priority (priority),
			KEY scope_type (scope_type)
		) {$charset};";

		$sql_rules = "CREATE TABLE {$rules} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rule_type varchar(40) NOT NULL,
			name varchar(190) NOT NULL,
			code varchar(80) NULL,
			description text NULL,
			start_date date NULL,
			end_date date NULL,
			recurring_annual tinyint(1) NOT NULL DEFAULT 0,
			weekdays varchar(40) NULL,
			scope_type varchar(30) NOT NULL DEFAULT 'global',
			scope_ids text NULL,
			adjustment_type varchar(40) NOT NULL DEFAULT 'percent',
			amount_minor int NOT NULL DEFAULT 0,
			percent_value decimal(7,3) NULL,
			price_minor int NOT NULL DEFAULT 0,
			min_days smallint unsigned NULL,
			max_days smallint unsigned NULL,
			priority int NOT NULL DEFAULT 0,
			combinable tinyint(1) NOT NULL DEFAULT 1,
			active tinyint(1) NOT NULL DEFAULT 0,
			config_json longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY rule_type (rule_type),
			KEY active (active),
			KEY date_range (start_date,end_date),
			KEY priority (priority),
			KEY scope_type (scope_type),
			KEY code (code)
		) {$charset};";

		$sql_coupons = "CREATE TABLE {$coupons} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(80) NOT NULL,
			description text NULL,
			discount_type varchar(30) NOT NULL DEFAULT 'percent',
			value_minor int NOT NULL DEFAULT 0,
			percent_value decimal(7,3) NULL,
			free_days smallint unsigned NOT NULL DEFAULT 0,
			min_spend_minor int NOT NULL DEFAULT 0,
			max_discount_minor int NOT NULL DEFAULT 0,
			start_date date NULL,
			end_date date NULL,
			total_uses int unsigned NULL,
			uses_per_email int unsigned NULL,
			scope_type varchar(30) NOT NULL DEFAULT 'global',
			scope_ids text NULL,
			min_days smallint unsigned NULL,
			max_days smallint unsigned NULL,
			combinable tinyint(1) NOT NULL DEFAULT 0,
			single_use tinyint(1) NOT NULL DEFAULT 0,
			allowed_emails text NULL,
			active tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY active (active),
			KEY date_range (start_date,end_date)
		) {$charset};";

		$sql_usages = "CREATE TABLE {$usages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			coupon_id bigint(20) unsigned NOT NULL,
			booking_id bigint(20) unsigned NULL,
			customer_email varchar(190) NOT NULL,
			amount_minor int NOT NULL DEFAULT 0,
			status varchar(30) NOT NULL DEFAULT 'reserved',
			used_at datetime NULL,
			released_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY coupon_id (coupon_id),
			KEY booking_id (booking_id),
			KEY customer_email (customer_email),
			KEY status (status),
			KEY used_at (used_at)
		) {$charset};";

		dbDelta( $sql_lists );
		dbDelta( $sql_rules );
		dbDelta( $sql_coupons );
		dbDelta( $sql_usages );
	}

	/**
	 * Default economic settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'currency'                         => 'EUR',
			'price_format'                     => 'symbol_after',
			'decimals'                         => 2,
			'prices_include_tax'               => 1,
			'default_tax_rate'                 => 22,
			'extra_tax_rate'                   => 22,
			'coverage_tax_rate'                => 22,
			'rounding_mode'                    => 'nearest',
			'rounding_increment_minor'         => 1,
			'minimum_booking_minor'            => 0,
			'minimum_duration_days'            => 1,
			'maximum_duration_days'            => 0,
			'duration_mode'                    => 'daily_with_tolerance',
			'return_tolerance_minutes'         => 59,
			'timezone'                         => wp_timezone_string() ?: 'Europe/Rome',
			'date_change_policy'               => 'recalculate_until_paid',
			'vehicle_change_policy'            => 'recalculate_until_paid',
			'days_per_week'                    => 7,
			'days_per_month'                   => 30,
			'weekly_monthly_mode'              => 'best_price',
			'weekend_days'                     => '6,7',
			'payment_mode'                     => 'request_only',
			'deposit_percent'                  => 20,
			'deposit_fixed_minor'              => 0,
			'deposit_min_minor'                => 0,
			'deposit_max_minor'                => 0,
			'deposit_rounding_increment_minor' => 1,
			'active_payment_methods'           => 'request,pickup',
			'stripe_enabled'                   => 0,
			'stripe_mode'                      => 'test',
			'stripe_test_publishable_key'      => '',
			'stripe_test_secret_key'           => '',
			'stripe_test_webhook_secret'       => '',
			'stripe_live_publishable_key'      => '',
			'stripe_live_secret_key'           => '',
			'stripe_live_webhook_secret'       => '',
			'stripe_currency'                  => 'EUR',
			'stripe_description'               => __( 'Prenotazione noleggio {booking_code}', 'gest-web-rent' ),
			'stripe_session_expiry_minutes'    => 60,
			'stripe_success_url'               => '',
			'stripe_cancel_url'                => '',
			'stripe_cards_enabled'             => 1,
			'stripe_wallets_enabled'           => 1,
			'stripe_receipt_email'             => 1,
			'stripe_admin_refunds'             => 1,
			'default_payment_method'           => 'request',
			'bank_transfer_enabled'            => 0,
			'bank_account_holder'              => '',
			'bank_iban'                        => '',
			'bank_bic'                         => '',
			'bank_name'                        => '',
			'bank_reason_template'             => 'Prenotazione {booking_code}',
			'bank_due_days'                    => 2,
			'bank_instructions'                => '',
			'refund_policy'                    => 'manual',
			'free_cancellation_hours'          => 0,
			'cancellation_penalty_minor'       => 0,
			'refund_deposit_policy'            => 'manual',
			'webhook_last_event_at'            => '',
			'webhook_last_error'               => '',
		);
	}

	/**
	 * Get economic settings.
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
	 * Save settings from an admin form.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function save_settings( $input ) {
		$settings = self::sanitize_settings( $input, self::get_settings() );
		update_option( self::OPTION_NAME, $settings, false );
		return $settings;
	}

	/**
	 * Sanitize settings preserving masked secrets.
	 *
	 * @param array $input Raw input.
	 * @param array $existing Existing settings.
	 * @return array
	 */
	public static function sanitize_settings( $input, $existing = array() ) {
		$input    = is_array( $input ) ? wp_unslash( $input ) : array();
		$existing = array_replace_recursive( self::default_settings(), is_array( $existing ) ? $existing : array() );
		$output   = array();

		$output['currency']                         = GWR_Money::normalize_currency( $input['currency'] ?? $existing['currency'] );
		$output['price_format']                     = self::choice( $input['price_format'] ?? $existing['price_format'], array( 'symbol_after', 'symbol_before', 'code_after' ), 'symbol_after' );
		$output['decimals']                         = min( 4, max( 0, absint( $input['decimals'] ?? $existing['decimals'] ) ) );
		$output['prices_include_tax']               = array_key_exists( 'prices_include_tax', $input ) ? ( ! empty( $input['prices_include_tax'] ) ? 1 : 0 ) : absint( $existing['prices_include_tax'] );
		$output['default_tax_rate']                 = self::percent( $input['default_tax_rate'] ?? $existing['default_tax_rate'] );
		$output['extra_tax_rate']                   = self::percent( $input['extra_tax_rate'] ?? $existing['extra_tax_rate'] );
		$output['coverage_tax_rate']                = self::percent( $input['coverage_tax_rate'] ?? $existing['coverage_tax_rate'] );
		$output['rounding_mode']                    = self::choice( $input['rounding_mode'] ?? $existing['rounding_mode'], array( 'nearest', 'up', 'down' ), 'nearest' );
		$output['rounding_increment_minor']         = max( 1, GWR_Money::decimal_to_minor( $input['rounding_increment'] ?? self::minor_field( $existing['rounding_increment_minor'] ) ) );
		$output['minimum_booking_minor']            = max( 0, GWR_Money::decimal_to_minor( $input['minimum_booking'] ?? self::minor_field( $existing['minimum_booking_minor'] ) ) );
		$output['minimum_duration_days']            = max( 1, absint( $input['minimum_duration_days'] ?? $existing['minimum_duration_days'] ) );
		$output['maximum_duration_days']            = absint( $input['maximum_duration_days'] ?? $existing['maximum_duration_days'] );
		$output['duration_mode']                    = self::choice( $input['duration_mode'] ?? $existing['duration_mode'], array( 'twenty_four_hours', 'calendar_days', 'daily_with_tolerance', 'rounded_fraction' ), 'daily_with_tolerance' );
		$output['return_tolerance_minutes']         = min( 1440, max( 0, absint( $input['return_tolerance_minutes'] ?? $existing['return_tolerance_minutes'] ) ) );
		$output['timezone']                         = sanitize_text_field( $input['timezone'] ?? $existing['timezone'] );
		$output['date_change_policy']               = sanitize_key( $input['date_change_policy'] ?? $existing['date_change_policy'] );
		$output['vehicle_change_policy']            = sanitize_key( $input['vehicle_change_policy'] ?? $existing['vehicle_change_policy'] );
		$output['days_per_week']                    = max( 1, min( 14, absint( $input['days_per_week'] ?? $existing['days_per_week'] ) ) );
		$output['days_per_month']                   = max( 1, min( 62, absint( $input['days_per_month'] ?? $existing['days_per_month'] ) ) );
		$output['weekly_monthly_mode']              = self::choice( $input['weekly_monthly_mode'] ?? $existing['weekly_monthly_mode'], array( 'off', 'best_price', 'always_package' ), 'best_price' );
		$output['weekend_days']                     = self::csv_weekdays( $input['weekend_days'] ?? $existing['weekend_days'] );
		$output['payment_mode']                     = self::choice( $input['payment_mode'] ?? $existing['payment_mode'], array( 'request_only', 'full_online', 'deposit_percent', 'deposit_fixed', 'first_day', 'pickup', 'bank_transfer', 'client_choice' ), 'request_only' );
		$output['deposit_percent']                  = self::percent( $input['deposit_percent'] ?? $existing['deposit_percent'] );
		$output['deposit_fixed_minor']              = max( 0, GWR_Money::decimal_to_minor( $input['deposit_fixed'] ?? self::minor_field( $existing['deposit_fixed_minor'] ) ) );
		$output['deposit_min_minor']                = max( 0, GWR_Money::decimal_to_minor( $input['deposit_min'] ?? self::minor_field( $existing['deposit_min_minor'] ) ) );
		$output['deposit_max_minor']                = max( 0, GWR_Money::decimal_to_minor( $input['deposit_max'] ?? self::minor_field( $existing['deposit_max_minor'] ) ) );
		$output['deposit_rounding_increment_minor'] = max( 1, GWR_Money::decimal_to_minor( $input['deposit_rounding_increment'] ?? self::minor_field( $existing['deposit_rounding_increment_minor'] ) ) );
		$output['active_payment_methods']           = self::csv_keys( $input['active_payment_methods'] ?? $existing['active_payment_methods'] );
		$output['stripe_enabled']                   = array_key_exists( 'stripe_enabled', $input ) ? ( ! empty( $input['stripe_enabled'] ) ? 1 : 0 ) : absint( $existing['stripe_enabled'] );
		$output['stripe_mode']                      = self::choice( $input['stripe_mode'] ?? $existing['stripe_mode'], array( 'test', 'live' ), 'test' );
		$output['stripe_test_publishable_key']      = sanitize_text_field( $input['stripe_test_publishable_key'] ?? $existing['stripe_test_publishable_key'] );
		$output['stripe_test_secret_key']           = self::secret_value( $input, $existing, 'stripe_test_secret_key' );
		$output['stripe_test_webhook_secret']       = self::secret_value( $input, $existing, 'stripe_test_webhook_secret' );
		$output['stripe_live_publishable_key']      = sanitize_text_field( $input['stripe_live_publishable_key'] ?? $existing['stripe_live_publishable_key'] );
		$output['stripe_live_secret_key']           = self::secret_value( $input, $existing, 'stripe_live_secret_key' );
		$output['stripe_live_webhook_secret']       = self::secret_value( $input, $existing, 'stripe_live_webhook_secret' );
		$output['stripe_currency']                  = GWR_Money::normalize_currency( $input['stripe_currency'] ?? $existing['stripe_currency'] );
		$output['stripe_description']               = sanitize_text_field( $input['stripe_description'] ?? $existing['stripe_description'] );
		$output['stripe_session_expiry_minutes']    = max( 30, min( 1440, absint( $input['stripe_session_expiry_minutes'] ?? $existing['stripe_session_expiry_minutes'] ) ) );
		$output['stripe_success_url']               = esc_url_raw( $input['stripe_success_url'] ?? $existing['stripe_success_url'] );
		$output['stripe_cancel_url']                = esc_url_raw( $input['stripe_cancel_url'] ?? $existing['stripe_cancel_url'] );
		$output['stripe_cards_enabled']             = array_key_exists( 'stripe_cards_enabled', $input ) ? ( ! empty( $input['stripe_cards_enabled'] ) ? 1 : 0 ) : absint( $existing['stripe_cards_enabled'] );
		$output['stripe_wallets_enabled']           = array_key_exists( 'stripe_wallets_enabled', $input ) ? ( ! empty( $input['stripe_wallets_enabled'] ) ? 1 : 0 ) : absint( $existing['stripe_wallets_enabled'] );
		$output['stripe_receipt_email']             = array_key_exists( 'stripe_receipt_email', $input ) ? ( ! empty( $input['stripe_receipt_email'] ) ? 1 : 0 ) : absint( $existing['stripe_receipt_email'] );
		$output['stripe_admin_refunds']             = array_key_exists( 'stripe_admin_refunds', $input ) ? ( ! empty( $input['stripe_admin_refunds'] ) ? 1 : 0 ) : absint( $existing['stripe_admin_refunds'] );
		$output['default_payment_method']           = sanitize_key( $input['default_payment_method'] ?? $existing['default_payment_method'] );
		$output['bank_transfer_enabled']            = array_key_exists( 'bank_transfer_enabled', $input ) ? ( ! empty( $input['bank_transfer_enabled'] ) ? 1 : 0 ) : absint( $existing['bank_transfer_enabled'] );
		$output['bank_account_holder']              = sanitize_text_field( $input['bank_account_holder'] ?? $existing['bank_account_holder'] );
		$output['bank_iban']                        = strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( $input['bank_iban'] ?? $existing['bank_iban'] ) ) );
		$output['bank_bic']                         = strtoupper( sanitize_text_field( $input['bank_bic'] ?? $existing['bank_bic'] ) );
		$output['bank_name']                        = sanitize_text_field( $input['bank_name'] ?? $existing['bank_name'] );
		$output['bank_reason_template']             = sanitize_text_field( $input['bank_reason_template'] ?? $existing['bank_reason_template'] );
		$output['bank_due_days']                    = max( 1, min( 30, absint( $input['bank_due_days'] ?? $existing['bank_due_days'] ) ) );
		$output['bank_instructions']                = sanitize_textarea_field( $input['bank_instructions'] ?? $existing['bank_instructions'] );
		$output['refund_policy']                    = self::choice( $input['refund_policy'] ?? $existing['refund_policy'], array( 'manual', 'automatic' ), 'manual' );
		$output['free_cancellation_hours']          = absint( $input['free_cancellation_hours'] ?? $existing['free_cancellation_hours'] );
		$output['cancellation_penalty_minor']       = max( 0, GWR_Money::decimal_to_minor( $input['cancellation_penalty'] ?? self::minor_field( $existing['cancellation_penalty_minor'] ) ) );
		$output['refund_deposit_policy']            = sanitize_key( $input['refund_deposit_policy'] ?? $existing['refund_deposit_policy'] );
		$output['webhook_last_event_at']            = sanitize_text_field( $existing['webhook_last_event_at'] ?? '' );
		$output['webhook_last_error']               = sanitize_text_field( $existing['webhook_last_error'] ?? '' );

		return array_replace_recursive( self::default_settings(), $output );
	}

	/**
	 * Calculate the billable duration.
	 *
	 * @param DateTimeImmutable $pickup Pickup.
	 * @param DateTimeImmutable $return Return.
	 * @param array|null        $settings Settings.
	 * @return array
	 */
	public static function calculate_billable_duration( $pickup, $return, $settings = null ) {
		$settings = is_array( $settings ) ? $settings : self::get_settings();
		$seconds  = max( 0, $return->getTimestamp() - $pickup->getTimestamp() );
		$minutes  = (int) ceil( $seconds / 60 );
		$hours    = (int) ceil( $minutes / 60 );
		$calendar = max( 1, (int) $pickup->setTime( 0, 0 )->diff( $return->setTime( 0, 0 ) )->days );
		$mode     = $settings['duration_mode'] ?? 'daily_with_tolerance';

		if ( 'calendar_days' === $mode ) {
			$billable = $calendar;
		} elseif ( 'rounded_fraction' === $mode || 'twenty_four_hours' === $mode ) {
			$billable = max( 1, (int) ceil( $seconds / DAY_IN_SECONDS ) );
		} else {
			$full_days = (int) floor( $seconds / DAY_IN_SECONDS );
			$remainder = $seconds - ( $full_days * DAY_IN_SECONDS );
			$tolerance = max( 0, absint( $settings['return_tolerance_minutes'] ?? 59 ) ) * MINUTE_IN_SECONDS;
			$billable  = max( 1, $full_days + ( $remainder > $tolerance ? 1 : 0 ) );
		}

		return array(
			'actual_minutes'    => $minutes,
			'actual_hours'      => $hours,
			'calendar_days'     => $calendar,
			'billable_days'     => $billable,
			'tolerance_minutes' => absint( $settings['return_tolerance_minutes'] ?? 0 ),
			'mode'              => sanitize_key( $mode ),
		);
	}

	/**
	 * Main pricing calculation.
	 *
	 * @param array $vehicle Vehicle row.
	 * @param array $period Normalized booking period.
	 * @param array $selection Selected extras/coverages.
	 * @param array $driver Driver data.
	 * @param array $terms_payload Rental terms payload.
	 * @param array $context Additional context.
	 * @return array|WP_Error
	 */
	public static function calculate( $vehicle, $period, $selection, $driver, $terms_payload, $context = array() ) {
		$settings = self::get_settings();
		$duration = $period['duration'] ?? self::calculate_billable_duration( $period['pickup'], $period['return'], $settings );
		$days     = max( 1, absint( $duration['billable_days'] ?? ( $period['days'] ?? 1 ) ) );

		if ( $days < absint( $settings['minimum_duration_days'] ?? 1 ) ) {
			return new WP_Error( 'gwr_rental_too_short', sprintf( __( 'La durata minima configurata e di %d giorni.', 'gest-web-rent' ), absint( $settings['minimum_duration_days'] ) ) );
		}
		if ( ! empty( $settings['maximum_duration_days'] ) && $days > absint( $settings['maximum_duration_days'] ) ) {
			return new WP_Error( 'gwr_rental_too_long', sprintf( __( 'La durata massima configurata e di %d giorni.', 'gest-web-rent' ), absint( $settings['maximum_duration_days'] ) ) );
		}
		if ( ! empty( $vehicle['min_rental_days'] ) && $days < absint( $vehicle['min_rental_days'] ) ) {
			return new WP_Error( 'gwr_rental_too_short', sprintf( __( 'La durata minima per questo veicolo e di %d giorni.', 'gest-web-rent' ), absint( $vehicle['min_rental_days'] ) ) );
		}
		if ( ! empty( $vehicle['max_rental_days'] ) && $days > absint( $vehicle['max_rental_days'] ) ) {
			return new WP_Error( 'gwr_rental_too_long', sprintf( __( 'La durata massima per questo veicolo e di %d giorni.', 'gest-web-rent' ), absint( $vehicle['max_rental_days'] ) ) );
		}

		$terms     = $terms_payload['rental_terms'] ?? array();
		$list      = self::select_price_list( $vehicle, $period, $days );
		$currency  = GWR_Money::normalize_currency( $list['currency'] ?? ( $terms['general']['currency'] ?? $settings['currency'] ) );
		$daily     = self::base_daily_minor( $vehicle, $list );
		if ( $daily <= 0 ) {
			return new WP_Error( 'gwr_price_missing', __( 'La tariffa del veicolo non e configurata.', 'gest-web-rent' ) );
		}

		$rules        = self::matching_rules( array( 'seasonal', 'duration', 'weekend', 'special', 'supplement', 'discount' ), $vehicle, $period, $days );
		$daily_lines  = self::daily_lines( $vehicle, $period, $days, $daily, $settings, $rules );
		$base_raw     = $daily * $days;
		$rental_total = array_sum( wp_list_pluck( $daily_lines, 'amount_minor' ) );
		$duration_adjustments = self::duration_adjustments( $vehicle, $days, $daily, $rental_total, $settings, $rules );
		foreach ( $duration_adjustments as $line ) {
			$rental_total += (int) $line['amount_minor'];
		}
		$rental_total = max( 0, $rental_total );

		$items            = array();
		$extras_total     = self::selected_extras_total( $terms_payload['extras'] ?? array(), $selection, $days, $rental_total, $items );
		if ( is_wp_error( $extras_total ) ) {
			return $extras_total;
		}
		$coverages_total  = self::selected_coverages_total( $terms['insurance_coverages'] ?? array(), $selection, $days, $rental_total, $items );
		$supplements      = self::automatic_supplements( $rules, $vehicle, $period, $days, $driver, $rental_total, $settings, $items );
		$supplements_total = array_sum( wp_list_pluck( $supplements, 'amount_minor' ) );
		$pre_discount     = $rental_total + $extras_total + $coverages_total + $supplements_total;
		$automatic_discounts = self::automatic_discounts( $rules, $vehicle, $period, $days, $pre_discount );
		$discounts_total  = array_sum( wp_list_pluck( $automatic_discounts, 'amount_minor' ) );
		$after_discounts  = max( 0, $pre_discount - $discounts_total );
		$coupon           = GWR_Coupon_Service::calculate_discount( sanitize_text_field( $context['coupon_code'] ?? '' ), array(
			'vehicle'        => $vehicle,
			'period'         => $period,
			'days'           => $days,
			'subtotal_minor' => $after_discounts,
			'customer_email' => sanitize_email( $context['customer_email'] ?? '' ),
		) );
		if ( is_wp_error( $coupon ) ) {
			return $coupon;
		}
		$coupon_minor     = absint( $coupon['amount_minor'] ?? 0 );
		$taxable_subtotal = max( 0, $after_discounts - $coupon_minor );
		$tax              = self::tax_breakdown( $taxable_subtotal, $terms, $settings, $list, $vehicle );
		$grand_total      = 'excluded' === $tax['mode'] ? $taxable_subtotal + $tax['tax_total_minor'] : $taxable_subtotal;
		$grand_total      = GWR_Money::round_minor( $grand_total, $settings['rounding_mode'], absint( $settings['rounding_increment_minor'] ) );
		$minimum          = absint( $settings['minimum_booking_minor'] ?? 0 );
		$vehicle_minimum  = GWR_Money::decimal_to_minor( $vehicle['minimum_price'] ?? 0 );
		if ( $vehicle_minimum ) {
			$minimum = max( $minimum, $vehicle_minimum );
		}
		if ( $minimum && $grand_total < $minimum ) {
			$grand_total = $minimum;
		}

		$payment          = self::payment_split( $grand_total, $daily, $days, $settings, sanitize_key( $context['payment_method'] ?? '' ) );
		$deposit_minor    = self::security_deposit_minor( $vehicle, $terms );

		return array(
			'pricing_version'          => self::VERSION,
			'currency'                 => $currency,
			'duration'                 => $duration,
			'price_list'               => $list ? self::public_price_list_snapshot( $list ) : null,
			'base_price'               => GWR_Money::minor_to_decimal( $rental_total ),
			'base_raw_minor'           => $base_raw,
			'daily_lines'              => $daily_lines,
			'duration_adjustments'     => $duration_adjustments,
			'seasonal_adjustments'     => self::flatten_adjustments( $daily_lines, 'seasonal' ),
			'supplements'              => $supplements,
			'extras'                   => self::filter_items( $items, 'extra' ),
			'coverages'                => self::filter_items( $items, 'coverage' ),
			'automatic_discounts'      => $automatic_discounts,
			'coupon'                   => $coupon,
			'taxes'                    => $tax['lines'],
			'subtotal'                 => GWR_Money::minor_to_decimal( $taxable_subtotal ),
			'tax_total'                => GWR_Money::minor_to_decimal( $tax['tax_total_minor'] ),
			'grand_total'              => GWR_Money::minor_to_decimal( $grand_total ),
			'pay_now'                  => GWR_Money::minor_to_decimal( $payment['pay_now_minor'] ),
			'pay_later'                => GWR_Money::minor_to_decimal( $payment['pay_later_minor'] ),
			'deposit_due_at_pickup'    => GWR_Money::minor_to_decimal( $deposit_minor ),
			'deposit_policy'           => array(
				'security_deposit_minor' => $deposit_minor,
				'due_at_pickup_minor'    => $deposit_minor,
				'online_authorization'   => 0,
			),
			'payment'                  => $payment,
			'rounding'                 => array(
				'mode'            => $settings['rounding_mode'],
				'increment_minor' => absint( $settings['rounding_increment_minor'] ),
			),
			'applied_rules'            => self::applied_rule_snapshot( $daily_lines, $duration_adjustments, $supplements, $automatic_discounts, $coupon ),
			'calculated_at'            => current_time( 'mysql' ),
			'base_minor'               => $rental_total,
			'extras_minor'             => $extras_total,
			'coverages_minor'          => $coverages_total,
			'supplements_minor'        => $supplements_total,
			'discounts_minor'          => $discounts_total + $coupon_minor,
			'taxes_minor'              => $tax['tax_total_minor'],
			'total_minor'              => $grand_total,
			'deposit_minor'            => $deposit_minor,
			'pay_now_minor'            => $payment['pay_now_minor'],
			'pay_later_minor'          => $payment['pay_later_minor'],
			'coupon_id'                => absint( $coupon['coupon_id'] ?? 0 ),
			'coupon_code'              => sanitize_text_field( $coupon['code'] ?? '' ),
			'items'                    => $items,
		);
	}

	/**
	 * Save a price list.
	 *
	 * @param array $input Raw input.
	 * @param int   $id Optional ID.
	 * @return int|WP_Error
	 */
	public static function save_price_list( $input, $id = 0 ) {
		global $wpdb;
		$input = is_array( $input ) ? wp_unslash( $input ) : array();
		$name  = sanitize_text_field( $input['name'] ?? '' );
		$code  = strtoupper( preg_replace( '/[^A-Z0-9_-]/', '', sanitize_text_field( $input['code'] ?? '' ) ) );
		if ( '' === $name || '' === $code ) {
			return new WP_Error( 'gwr_price_list_invalid', __( 'Nome e codice listino sono obbligatori.', 'gest-web-rent' ) );
		}
		$data = array(
			'name'        => $name,
			'code'        => $code,
			'description' => sanitize_textarea_field( $input['description'] ?? '' ),
			'currency'    => GWR_Money::normalize_currency( $input['currency'] ?? 'EUR' ),
			'tax_rate'    => self::percent( $input['tax_rate'] ?? '' ),
			'start_date'  => self::nullable_date( $input['start_date'] ?? '' ),
			'end_date'    => self::nullable_date( $input['end_date'] ?? '' ),
			'active'      => ! empty( $input['active'] ) ? 1 : 0,
			'priority'    => intval( $input['priority'] ?? 0 ),
			'scope_type'  => self::scope_type( $input['scope_type'] ?? 'global' ),
			'scope_ids'   => sanitize_text_field( $input['scope_ids'] ?? '' ),
			'min_days'    => self::nullable_absint( $input['min_days'] ?? '' ),
			'max_days'    => self::nullable_absint( $input['max_days'] ?? '' ),
			'config_json' => wp_json_encode( self::sanitize_config_json( $input['config_json'] ?? '' ) ),
			'updated_at'  => current_time( 'mysql' ),
		);
		$id = absint( $id );
		if ( $id ) {
			$wpdb->update( self::price_lists_table(), $data, array( 'id' => $id ) );
			return $id;
		}
		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( self::price_lists_table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Save a pricing rule.
	 *
	 * @param array $input Raw input.
	 * @param int   $id Optional ID.
	 * @return int|WP_Error
	 */
	public static function save_rule( $input, $id = 0 ) {
		global $wpdb;
		$input = is_array( $input ) ? wp_unslash( $input ) : array();
		$name  = sanitize_text_field( $input['name'] ?? '' );
		if ( '' === $name ) {
			return new WP_Error( 'gwr_rule_invalid', __( 'Il nome regola e obbligatorio.', 'gest-web-rent' ) );
		}
		$data = array(
			'rule_type'        => self::choice( $input['rule_type'] ?? '', array( 'seasonal', 'duration', 'weekend', 'special', 'supplement', 'discount' ), 'seasonal' ),
			'name'             => $name,
			'code'             => sanitize_key( $input['code'] ?? '' ),
			'description'      => sanitize_textarea_field( $input['description'] ?? '' ),
			'start_date'       => self::nullable_date( $input['start_date'] ?? '' ),
			'end_date'         => self::nullable_date( $input['end_date'] ?? '' ),
			'recurring_annual' => ! empty( $input['recurring_annual'] ) ? 1 : 0,
			'weekdays'         => self::csv_weekdays( $input['weekdays'] ?? '' ),
			'scope_type'       => self::scope_type( $input['scope_type'] ?? 'global' ),
			'scope_ids'        => sanitize_text_field( $input['scope_ids'] ?? '' ),
			'adjustment_type'  => self::choice( $input['adjustment_type'] ?? '', array( 'percent', 'fixed', 'override', 'discount_percent', 'discount_fixed', 'free_days' ), 'percent' ),
			'amount_minor'     => GWR_Money::decimal_to_minor( $input['amount'] ?? 0 ),
			'percent_value'    => self::percent( $input['percent_value'] ?? '' ),
			'price_minor'      => GWR_Money::decimal_to_minor( $input['price'] ?? 0 ),
			'min_days'         => self::nullable_absint( $input['min_days'] ?? '' ),
			'max_days'         => self::nullable_absint( $input['max_days'] ?? '' ),
			'priority'         => intval( $input['priority'] ?? 0 ),
			'combinable'       => ! empty( $input['combinable'] ) ? 1 : 0,
			'active'           => ! empty( $input['active'] ) ? 1 : 0,
			'config_json'      => wp_json_encode( self::sanitize_config_json( $input['config_json'] ?? '' ) ),
			'updated_at'       => current_time( 'mysql' ),
		);
		$id = absint( $id );
		if ( $id ) {
			$wpdb->update( self::rules_table(), $data, array( 'id' => $id ) );
			return $id;
		}
		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( self::rules_table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Save a coupon.
	 *
	 * @param array $input Raw input.
	 * @param int   $id Optional ID.
	 * @return int|WP_Error
	 */
	public static function save_coupon( $input, $id = 0 ) {
		global $wpdb;
		$input = is_array( $input ) ? wp_unslash( $input ) : array();
		$code  = GWR_Coupon_Service::normalize_code( $input['code'] ?? '' );
		if ( '' === $code ) {
			return new WP_Error( 'gwr_coupon_invalid', __( 'Il codice coupon e obbligatorio.', 'gest-web-rent' ) );
		}
		$data = array(
			'code'               => $code,
			'description'        => sanitize_textarea_field( $input['description'] ?? '' ),
			'discount_type'      => self::choice( $input['discount_type'] ?? '', array( 'percent', 'amount', 'free_days' ), 'percent' ),
			'value_minor'        => GWR_Money::decimal_to_minor( $input['value'] ?? 0 ),
			'percent_value'      => self::percent( $input['percent_value'] ?? '' ),
			'free_days'          => absint( $input['free_days'] ?? 0 ),
			'min_spend_minor'    => GWR_Money::decimal_to_minor( $input['min_spend'] ?? 0 ),
			'max_discount_minor' => GWR_Money::decimal_to_minor( $input['max_discount'] ?? 0 ),
			'start_date'         => self::nullable_date( $input['start_date'] ?? '' ),
			'end_date'           => self::nullable_date( $input['end_date'] ?? '' ),
			'total_uses'         => self::nullable_absint( $input['total_uses'] ?? '' ),
			'uses_per_email'     => self::nullable_absint( $input['uses_per_email'] ?? '' ),
			'scope_type'         => self::scope_type( $input['scope_type'] ?? 'global' ),
			'scope_ids'          => sanitize_text_field( $input['scope_ids'] ?? '' ),
			'min_days'           => self::nullable_absint( $input['min_days'] ?? '' ),
			'max_days'           => self::nullable_absint( $input['max_days'] ?? '' ),
			'combinable'         => ! empty( $input['combinable'] ) ? 1 : 0,
			'single_use'         => ! empty( $input['single_use'] ) ? 1 : 0,
			'allowed_emails'     => sanitize_textarea_field( $input['allowed_emails'] ?? '' ),
			'active'             => ! empty( $input['active'] ) ? 1 : 0,
			'updated_at'         => current_time( 'mysql' ),
		);
		$id = absint( $id );
		if ( $id ) {
			$wpdb->update( self::coupons_table(), $data, array( 'id' => $id ) );
			return $id;
		}
		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( self::coupons_table(), $data );
		return (int) $wpdb->insert_id;
	}

	/** Query price lists. */
	public static function price_lists( $active_only = false ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::price_lists_table();
		if ( $active_only ) {
			$sql .= ' WHERE active=1';
		}
		$sql .= ' ORDER BY priority DESC, name ASC';
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/** Query pricing rules. */
	public static function rules( $type = '' ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::rules_table();
		if ( $type ) {
			$sql = $wpdb->prepare( $sql . ' WHERE rule_type=%s', sanitize_key( $type ) );
		}
		$sql .= ' ORDER BY active DESC, priority DESC, name ASC';
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/** Delete repository row. */
	public static function delete_row( $table, $id ) {
		global $wpdb;
		$allowed = array( self::price_lists_table(), self::rules_table(), self::coupons_table() );
		if ( ! in_array( $table, $allowed, true ) ) {
			return false;
		}
		return (bool) $wpdb->delete( $table, array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/** Internal: choose the best matching price list. */
	private static function select_price_list( $vehicle, $period, $days ) {
		$preferred_code = strtoupper( sanitize_text_field( $vehicle['price_list_code'] ?? '' ) );
		foreach ( self::price_lists( true ) as $list ) {
			if ( $preferred_code && strtoupper( $list['code'] ) !== $preferred_code ) {
				continue;
			}
			if ( ! self::date_range_matches_period( $list, $period ) || ! self::days_match( $list, $days ) || ! self::scope_matches( $list, $vehicle ) ) {
				continue;
			}
			$list['config'] = self::decode_json( $list['config_json'] ?? '' );
			return $list;
		}
		if ( $preferred_code ) {
			foreach ( self::price_lists( true ) as $list ) {
				if ( ! self::date_range_matches_period( $list, $period ) || ! self::days_match( $list, $days ) || ! self::scope_matches( $list, $vehicle ) ) {
					continue;
				}
				$list['config'] = self::decode_json( $list['config_json'] ?? '' );
				return $list;
			}
		}
		return null;
	}

	private static function public_price_list_snapshot( $list ) {
		return array(
			'id'       => absint( $list['id'] ),
			'name'     => sanitize_text_field( $list['name'] ),
			'code'     => sanitize_text_field( $list['code'] ),
			'currency' => GWR_Money::normalize_currency( $list['currency'] ),
			'priority' => intval( $list['priority'] ),
		);
	}

	private static function base_daily_minor( $vehicle, $list ) {
		$config = is_array( $list ) ? self::decode_json( $list['config_json'] ?? '' ) : array();
		if ( ! empty( $config['daily_price'] ) ) {
			return GWR_Money::decimal_to_minor( $config['daily_price'] );
		}
		if ( ! empty( $config['daily_price_minor'] ) ) {
			return absint( $config['daily_price_minor'] );
		}
		return GWR_Money::decimal_to_minor( $vehicle['daily_price'] ?? 0 );
	}

	private static function matching_rules( $types, $vehicle, $period, $days ) {
		global $wpdb;
		$types = array_values( array_filter( array_map( 'sanitize_key', (array) $types ) ) );
		if ( empty( $types ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$sql = 'SELECT * FROM ' . self::rules_table() . " WHERE active=1 AND rule_type IN ({$placeholders}) ORDER BY priority DESC, id ASC";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $types ), ARRAY_A );
		$output = array();
		foreach ( $rows as $row ) {
			if ( ! self::date_range_matches_period( $row, $period ) || ! self::days_match( $row, $days ) || ! self::scope_matches( $row, $vehicle ) ) {
				continue;
			}
			$row['config'] = self::decode_json( $row['config_json'] ?? '' );
			$output[] = $row;
		}
		return $output;
	}

	private static function daily_lines( $vehicle, $period, $days, $daily, $settings, $rules ) {
		$lines        = array();
		$weekend_days = self::weekday_list( $settings['weekend_days'] ?? '6,7' );
		$cursor       = $period['pickup'];
		$weekend_minor = GWR_Money::decimal_to_minor( $vehicle['weekend_price'] ?? 0 );
		for ( $index = 0; $index < $days; $index++ ) {
			$date         = $cursor->modify( '+' . $index . ' days' );
			$weekday      = (int) $date->format( 'N' );
			$amount       = ( $weekend_minor > 0 && in_array( $weekday, $weekend_days, true ) ) ? $weekend_minor : $daily;
			$base_amount  = $amount;
			$adjustments  = array();
			foreach ( $rules as $rule ) {
				if ( ! in_array( $rule['rule_type'], array( 'seasonal', 'weekend', 'special' ), true ) ) {
					continue;
				}
				if ( 'weekend' === $rule['rule_type'] && ! in_array( $weekday, self::weekday_list( $rule['weekdays'] ?: implode( ',', $weekend_days ) ), true ) ) {
					continue;
				}
				if ( ! self::rule_matches_day( $rule, $date ) ) {
					continue;
				}
				$before = $amount;
				$amount = self::apply_rule_adjustment( $amount, $rule, 'daily' );
				$delta  = $amount - $before;
				if ( 0 !== $delta ) {
					$adjustments[] = self::adjustment_line( $rule, $delta );
				}
				if ( empty( $rule['combinable'] ) ) {
					break;
				}
			}
			$lines[] = array(
				'date'              => $date->format( 'Y-m-d' ),
				'weekday'           => $weekday,
				'base_amount_minor' => $base_amount,
				'amount_minor'      => max( 0, $amount ),
				'label'             => sprintf( __( 'Giorno %d', 'gest-web-rent' ), $index + 1 ),
				'adjustments'       => $adjustments,
			);
		}
		return $lines;
	}

	private static function duration_adjustments( $vehicle, $days, $daily, $current_total, $settings, $rules ) {
		$lines = array();
		if ( 'off' !== ( $settings['weekly_monthly_mode'] ?? 'best_price' ) ) {
			$package = self::weekly_monthly_total( $vehicle, $days, $daily, $settings );
			if ( $package > 0 && ( 'always_package' === $settings['weekly_monthly_mode'] || $package < $current_total ) ) {
				$lines[] = array(
					'name'         => __( 'Tariffa settimanale/mensile', 'gest-web-rent' ),
					'code'         => 'vehicle_long_term',
					'amount_minor' => $package - $current_total,
					'rule_id'      => 0,
				);
				$current_total = $package;
			}
		}
		foreach ( $rules as $rule ) {
			if ( 'duration' !== $rule['rule_type'] ) {
				continue;
			}
			$before = $current_total;
			$after  = self::apply_duration_rule( $current_total, $daily, $days, $rule );
			$delta  = $after - $before;
			if ( 0 !== $delta ) {
				$lines[] = self::adjustment_line( $rule, $delta );
				$current_total = $after;
			}
			if ( empty( $rule['combinable'] ) ) {
				break;
			}
		}
		return $lines;
	}

	private static function weekly_monthly_total( $vehicle, $days, $daily, $settings ) {
		$week_days     = max( 1, absint( $settings['days_per_week'] ?? 7 ) );
		$month_days    = max( 1, absint( $settings['days_per_month'] ?? 30 ) );
		$weekly_minor  = GWR_Money::decimal_to_minor( $vehicle['weekly_price'] ?? 0 );
		$monthly_minor = GWR_Money::decimal_to_minor( $vehicle['monthly_price'] ?? 0 );
		$remaining     = $days;
		$total         = 0;
		if ( $monthly_minor > 0 ) {
			$months    = (int) floor( $remaining / $month_days );
			$total    += $months * $monthly_minor;
			$remaining = $remaining - ( $months * $month_days );
		}
		if ( $weekly_minor > 0 ) {
			$weeks     = (int) floor( $remaining / $week_days );
			$total    += $weeks * $weekly_minor;
			$remaining = $remaining - ( $weeks * $week_days );
		}
		$total += $remaining * $daily;
		return $total;
	}

	private static function selected_extras_total( $extras, $selection, $days, $base, &$items ) {
		$total = 0;
		$selected = array();
		foreach ( $selection['extras'] ?? array() as $row ) {
			$selected[ sanitize_key( $row['id'] ?? '' ) ] = max( 1, absint( $row['quantity'] ?? 1 ) );
		}
		foreach ( $extras as $extra ) {
			$id = sanitize_key( $extra['id'] ?? '' );
			if ( ! $id || ( empty( $extra['mandatory'] ) && ! isset( $selected[ $id ] ) ) ) {
				continue;
			}
			$quantity = isset( $selected[ $id ] ) ? absint( $selected[ $id ] ) : max( 1, absint( $extra['min_quantity'] ?? 1 ) );
			$min      = max( ! empty( $extra['mandatory'] ) ? 1 : 0, absint( $extra['min_quantity'] ?? 0 ) );
			$max      = max( 1, absint( $extra['max_quantity'] ?? 1 ), $min );
			if ( $quantity < $min || $quantity > $max ) {
				return new WP_Error( 'gwr_extra_quantity', sprintf( __( 'Quantita non valida per %s.', 'gest-web-rent' ), $extra['name'] ?? $id ) );
			}
			$line = self::price_for_mode( absint( $extra['price_minor'] ?? 0 ), $extra['price_mode'] ?? 'per_rental', $quantity, $days, $base, 0 );
			$total += $line;
			$items[] = self::price_item( 'extra', $id, $extra['name'] ?? $id, $quantity, absint( $extra['price_minor'] ?? 0 ), $extra['price_mode'] ?? 'per_rental', $days, $line, $extra );
		}
		return $total;
	}

	private static function selected_coverages_total( $coverages, $selection, $days, $base, &$items ) {
		$total = 0;
		$selected = array_flip( array_map( 'sanitize_key', $selection['coverages'] ?? array() ) );
		foreach ( $coverages as $coverage ) {
			$id = sanitize_key( $coverage['id'] ?? '' );
			if ( 'optional' !== ( $coverage['status'] ?? '' ) || ! isset( $selected[ $id ] ) ) {
				continue;
			}
			$line = self::price_for_mode( absint( $coverage['cost_minor'] ?? 0 ), $coverage['price_mode'] ?? 'per_rental', 1, $days, $base, (float) ( $coverage['cost'] ?? 0 ) );
			$total += $line;
			$items[] = self::price_item( 'coverage', $id, $coverage['name'] ?? $id, 1, absint( $coverage['cost_minor'] ?? 0 ), $coverage['price_mode'] ?? 'per_rental', $days, $line, $coverage );
		}
		return $total;
	}

	private static function automatic_supplements( $rules, $vehicle, $period, $days, $driver, $base, $settings, &$items ) {
		$lines = array();
		foreach ( $rules as $rule ) {
			if ( 'supplement' !== $rule['rule_type'] || ! self::supplement_conditions_match( $rule, $period, $driver, $days ) ) {
				continue;
			}
			$quantity = max( 1, absint( $rule['config']['quantity'] ?? 1 ) );
			$mode     = sanitize_key( $rule['config']['price_mode'] ?? 'per_rental' );
			$unit     = $rule['price_minor'] ? absint( $rule['price_minor'] ) : absint( $rule['amount_minor'] );
			$amount   = self::price_for_mode( $unit, $mode, $quantity, $days, $base, (float) $rule['percent_value'] );
			if ( $amount <= 0 && $rule['percent_value'] > 0 ) {
				$amount = GWR_Money::percent_amount( $base, $rule['percent_value'] );
			}
			if ( $amount <= 0 ) {
				continue;
			}
			$line = self::adjustment_line( $rule, $amount );
			$line['price_mode'] = $mode;
			$lines[] = $line;
			$items[] = self::price_item( 'surcharge', sanitize_key( $rule['code'] ?: 'rule_' . $rule['id'] ), $rule['name'], $quantity, $unit, $mode, $days, $amount, $rule );
			if ( empty( $rule['combinable'] ) ) {
				break;
			}
		}
		return $lines;
	}

	private static function automatic_discounts( $rules, $vehicle, $period, $days, $subtotal ) {
		$lines = array();
		foreach ( $rules as $rule ) {
			if ( 'discount' !== $rule['rule_type'] ) {
				continue;
			}
			$amount = self::discount_amount( $rule, $subtotal, $days );
			if ( $amount <= 0 ) {
				continue;
			}
			$line = self::adjustment_line( $rule, $amount );
			$line['amount_minor'] = $amount;
			$lines[] = $line;
			if ( empty( $rule['combinable'] ) ) {
				break;
			}
		}
		return $lines;
	}

	private static function tax_breakdown( $subtotal, $terms, $settings, $list, $vehicle = array() ) {
		$terms_mode = $terms['general']['taxes'] ?? '';
		$mode       = $terms_mode ? $terms_mode : ( ! empty( $settings['prices_include_tax'] ) ? 'included' : 'excluded' );
		if ( isset( $list['tax_rate'] ) && '' !== (string) $list['tax_rate'] ) {
			$rate = (float) $list['tax_rate'];
		} elseif ( isset( $vehicle['vehicle_tax_rate'] ) && '' !== (string) $vehicle['vehicle_tax_rate'] ) {
			$rate = (float) $vehicle['vehicle_tax_rate'];
		} else {
			$rate = (float) ( $terms['general']['tax_rate'] ?? $settings['default_tax_rate'] );
		}
		$tax        = 0;
		if ( $rate > 0 && 'excluded' === $mode ) {
			$tax = GWR_Money::percent_amount( $subtotal, $rate );
		} elseif ( $rate > 0 && 'included' === $mode ) {
			$tax = (int) round( $subtotal * $rate / ( 100 + $rate ) );
		}
		return array(
			'mode'            => $mode,
			'tax_total_minor' => $tax,
			'lines'           => $tax > 0 ? array( array( 'name' => 'IVA', 'rate' => $rate, 'mode' => $mode, 'amount_minor' => $tax ) ) : array(),
		);
	}

	private static function payment_split( $total, $daily, $days, $settings, $requested_method ) {
		$method = $requested_method ?: sanitize_key( $settings['default_payment_method'] ?? 'request' );
		$mode   = sanitize_key( $settings['payment_mode'] ?? 'request_only' );
		if ( in_array( $method, array( 'request', 'pickup' ), true ) || in_array( $mode, array( 'request_only', 'pickup' ), true ) ) {
			return array( 'payment_method' => $method, 'mode' => $mode, 'pay_now_minor' => 0, 'pay_later_minor' => $total );
		}
		if ( 'full_online' === $mode ) {
			$pay_now = $total;
		} elseif ( 'deposit_fixed' === $mode ) {
			$pay_now = absint( $settings['deposit_fixed_minor'] ?? 0 );
		} elseif ( 'first_day' === $mode ) {
			$pay_now = $daily;
		} else {
			$pay_now = GWR_Money::percent_amount( $total, $settings['deposit_percent'] ?? 0 );
		}
		if ( ! empty( $settings['deposit_min_minor'] ) ) {
			$pay_now = max( $pay_now, absint( $settings['deposit_min_minor'] ) );
		}
		if ( ! empty( $settings['deposit_max_minor'] ) ) {
			$pay_now = min( $pay_now, absint( $settings['deposit_max_minor'] ) );
		}
		$pay_now = min( $total, max( 0, GWR_Money::round_minor( $pay_now, $settings['rounding_mode'], absint( $settings['deposit_rounding_increment_minor'] ?? 1 ) ) ) );
		return array( 'payment_method' => $method, 'mode' => $mode, 'pay_now_minor' => $pay_now, 'pay_later_minor' => max( 0, $total - $pay_now ) );
	}

	private static function security_deposit_minor( $vehicle, $terms ) {
		if ( isset( $terms['security_deposit']['amount'] ) ) {
			return max( 0, GWR_Money::decimal_to_minor( $terms['security_deposit']['amount'] ) );
		}
		return max( 0, GWR_Money::decimal_to_minor( $vehicle['deposit'] ?? 0 ) );
	}

	private static function price_for_mode( $unit, $mode, $quantity, $days, $base, $percent ) {
		if ( 'free' === $mode ) {
			return 0;
		}
		if ( 'per_day' === $mode ) {
			return $unit * $quantity * $days;
		}
		if ( 'percentage' === $mode ) {
			return GWR_Money::percent_amount( $base, $percent ) * $quantity;
		}
		return $unit * $quantity;
	}

	private static function price_item( $type, $id, $name, $quantity, $unit_minor, $mode, $days, $total_minor, $snapshot ) {
		return array(
			'item_type'     => sanitize_key( $type ),
			'item_id'       => sanitize_key( $id ),
			'item_name'     => sanitize_text_field( $name ),
			'quantity'      => absint( $quantity ),
			'unit_price'    => GWR_Money::minor_to_decimal( $unit_minor ),
			'pricing_mode'  => sanitize_key( $mode ),
			'days'          => absint( $days ),
			'total_price'   => GWR_Money::minor_to_decimal( $total_minor ),
			'snapshot_json' => wp_json_encode( $snapshot ),
		);
	}

	private static function apply_rule_adjustment( $amount, $rule, $context ) {
		$type = sanitize_key( $rule['adjustment_type'] ?? 'percent' );
		if ( 'override' === $type && absint( $rule['price_minor'] ) > 0 ) {
			return absint( $rule['price_minor'] );
		}
		if ( 'fixed' === $type ) {
			return max( 0, $amount + (int) $rule['amount_minor'] );
		}
		if ( 'discount_fixed' === $type ) {
			return max( 0, $amount - absint( $rule['amount_minor'] ) );
		}
		if ( 'discount_percent' === $type ) {
			return max( 0, $amount - GWR_Money::percent_amount( $amount, $rule['percent_value'] ) );
		}
		return max( 0, $amount + GWR_Money::percent_amount( $amount, $rule['percent_value'] ) );
	}

	private static function apply_duration_rule( $current_total, $daily, $days, $rule ) {
		if ( 'override' === $rule['adjustment_type'] && absint( $rule['price_minor'] ) > 0 ) {
			return absint( $rule['price_minor'] ) * $days;
		}
		if ( 'fixed' === $rule['adjustment_type'] ) {
			return max( 0, $current_total + (int) $rule['amount_minor'] );
		}
		if ( 'discount_fixed' === $rule['adjustment_type'] ) {
			return max( 0, $current_total - absint( $rule['amount_minor'] ) );
		}
		if ( 'discount_percent' === $rule['adjustment_type'] || 'percent' === $rule['adjustment_type'] ) {
			return max( 0, $current_total - GWR_Money::percent_amount( $current_total, $rule['percent_value'] ) );
		}
		return $current_total;
	}

	private static function discount_amount( $rule, $subtotal, $days ) {
		if ( 'free_days' === $rule['adjustment_type'] ) {
			return min( $subtotal, absint( $rule['price_minor'] ) * max( 1, absint( $rule['config']['free_days'] ?? 1 ) ) );
		}
		if ( 'discount_fixed' === $rule['adjustment_type'] || 'fixed' === $rule['adjustment_type'] ) {
			return min( $subtotal, absint( $rule['amount_minor'] ) );
		}
		return min( $subtotal, GWR_Money::percent_amount( $subtotal, $rule['percent_value'] ) );
	}

	private static function adjustment_line( $rule, $amount_minor ) {
		return array(
			'rule_id'      => absint( $rule['id'] ?? 0 ),
			'name'         => sanitize_text_field( $rule['name'] ?? '' ),
			'code'         => sanitize_key( $rule['code'] ?? '' ),
			'type'         => sanitize_key( $rule['rule_type'] ?? '' ),
			'amount_minor' => (int) $amount_minor,
			'priority'     => intval( $rule['priority'] ?? 0 ),
		);
	}

	private static function rule_matches_day( $rule, $date ) {
		if ( ! empty( $rule['weekdays'] ) && ! in_array( (int) $date->format( 'N' ), self::weekday_list( $rule['weekdays'] ), true ) ) {
			return false;
		}
		if ( empty( $rule['start_date'] ) && empty( $rule['end_date'] ) ) {
			return true;
		}
		if ( ! empty( $rule['recurring_annual'] ) ) {
			$current = $date->format( 'm-d' );
			$start   = $rule['start_date'] ? substr( $rule['start_date'], 5 ) : '01-01';
			$end     = $rule['end_date'] ? substr( $rule['end_date'], 5 ) : '12-31';
			return $start <= $end ? ( $current >= $start && $current <= $end ) : ( $current >= $start || $current <= $end );
		}
		$current = $date->format( 'Y-m-d' );
		if ( ! empty( $rule['start_date'] ) && $current < $rule['start_date'] ) {
			return false;
		}
		if ( ! empty( $rule['end_date'] ) && $current > $rule['end_date'] ) {
			return false;
		}
		return true;
	}

	private static function date_range_matches_period( $row, $period ) {
		if ( ! empty( $row['recurring_annual'] ) ) {
			return true;
		}
		$start = substr( $period['pickup_mysql'], 0, 10 );
		$end   = substr( $period['return_mysql'], 0, 10 );
		if ( ! empty( $row['start_date'] ) && $row['start_date'] > $end ) {
			return false;
		}
		if ( ! empty( $row['end_date'] ) && $row['end_date'] < $start ) {
			return false;
		}
		return true;
	}

	public static function days_match( $row, $days ) {
		if ( ! empty( $row['min_days'] ) && $days < absint( $row['min_days'] ) ) {
			return false;
		}
		if ( ! empty( $row['max_days'] ) && $days > absint( $row['max_days'] ) ) {
			return false;
		}
		return true;
	}

	private static function scope_matches( $row, $vehicle ) {
		$type = sanitize_key( $row['scope_type'] ?? 'global' );
		if ( in_array( $type, array( 'global', 'all' ), true ) ) {
			return true;
		}
		$values = array_filter( array_map( 'trim', explode( ',', (string) ( $row['scope_ids'] ?? '' ) ) ) );
		if ( empty( $values ) ) {
			return false;
		}
		if ( 'vehicle' === $type ) {
			return in_array( (string) absint( $vehicle['id'] ?? 0 ), $values, true );
		}
		if ( 'category' === $type ) {
			return in_array( strtolower( (string) ( $vehicle['category'] ?? '' ) ), array_map( 'strtolower', $values ), true );
		}
		if ( 'location' === $type || 'site' === $type ) {
			return in_array( strtolower( (string) ( $vehicle['location'] ?? '' ) ), array_map( 'strtolower', $values ), true );
		}
		return false;
	}

	private static function supplement_conditions_match( $rule, $period, $driver, $days ) {
		$config = $rule['config'] ?? array();
		if ( ! empty( $config['driver_min_age'] ) || ! empty( $config['driver_max_age'] ) ) {
			$age = self::years_between( $driver['birth_date'] ?? '', $period['pickup'] );
			if ( ! empty( $config['driver_min_age'] ) && $age < absint( $config['driver_min_age'] ) ) {
				return false;
			}
			if ( ! empty( $config['driver_max_age'] ) && $age > absint( $config['driver_max_age'] ) ) {
				return false;
			}
		}
		if ( ! empty( $config['max_days'] ) && $days > absint( $config['max_days'] ) ) {
			return false;
		}
		if ( ! empty( $config['min_days'] ) && $days < absint( $config['min_days'] ) ) {
			return false;
		}
		if ( ! empty( $config['pickup_after'] ) && $period['pickup']->format( 'H:i' ) < sanitize_text_field( $config['pickup_after'] ) ) {
			return false;
		}
		if ( ! empty( $config['return_after'] ) && $period['return']->format( 'H:i' ) < sanitize_text_field( $config['return_after'] ) ) {
			return false;
		}
		return true;
	}

	private static function years_between( $date, $target ) {
		$object = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $date, wp_timezone() );
		$errors = DateTimeImmutable::getLastErrors();
		return $object && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) ? (int) $object->diff( $target )->y : -1;
	}

	private static function flatten_adjustments( $daily_lines, $type ) {
		$output = array();
		foreach ( $daily_lines as $line ) {
			foreach ( $line['adjustments'] as $adjustment ) {
				if ( $type === ( $adjustment['type'] ?? '' ) ) {
					$output[] = array_merge( array( 'date' => $line['date'] ), $adjustment );
				}
			}
		}
		return $output;
	}

	private static function filter_items( $items, $type ) {
		return array_values( array_filter( $items, function( $item ) use ( $type ) { return $type === ( $item['item_type'] ?? '' ); } ) );
	}

	private static function applied_rule_snapshot( $daily_lines, $duration_adjustments, $supplements, $discounts, $coupon ) {
		$rules = array();
		foreach ( $daily_lines as $line ) {
			foreach ( $line['adjustments'] as $adjustment ) {
				if ( ! empty( $adjustment['rule_id'] ) ) {
					$rules[ $adjustment['rule_id'] ] = $adjustment;
				}
			}
		}
		foreach ( array_merge( $duration_adjustments, $supplements, $discounts ) as $line ) {
			if ( ! empty( $line['rule_id'] ) ) {
				$rules[ $line['rule_id'] ] = $line;
			}
		}
		if ( ! empty( $coupon['coupon_id'] ) ) {
			$rules[ 'coupon_' . absint( $coupon['coupon_id'] ) ] = $coupon;
		}
		return array_values( $rules );
	}

	private static function weekday_list( $csv ) {
		return array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $csv ) ), function( $day ) { return $day >= 1 && $day <= 7; } ) ) );
	}

	private static function csv_weekdays( $value ) {
		return implode( ',', self::weekday_list( $value ) );
	}

	private static function csv_keys( $value ) {
		$items = is_array( $value ) ? $value : explode( ',', (string) $value );
		return implode( ',', array_values( array_unique( array_filter( array_map( 'sanitize_key', $items ) ) ) ) );
	}

	private static function decode_json( $json ) {
		$value = json_decode( (string) $json, true );
		return is_array( $value ) ? $value : array();
	}

	private static function sanitize_config_json( $json ) {
		if ( is_array( $json ) ) {
			return self::sanitize_config_array( $json );
		}
		$json = trim( (string) $json );
		if ( '' === $json ) {
			return array();
		}
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? self::sanitize_config_array( $decoded ) : array();
	}

	private static function sanitize_config_array( $value ) {
		if ( is_array( $value ) ) {
			$output = array();
			foreach ( $value as $key => $item ) {
				$output[ sanitize_key( $key ) ] = self::sanitize_config_array( $item );
			}
			return $output;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return 0 + $value;
		}
		return sanitize_text_field( (string) $value );
	}

	private static function nullable_date( $value ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : null;
	}

	private static function nullable_absint( $value ) {
		return '' === (string) $value ? null : absint( $value );
	}

	private static function percent( $value ) {
		if ( '' === (string) $value ) {
			return null;
		}
		return max( 0, min( 100, round( (float) str_replace( ',', '.', (string) $value ), 3 ) ) );
	}

	private static function choice( $value, $allowed, $default ) {
		$value = sanitize_key( $value );
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	private static function scope_type( $value ) {
		return self::choice( $value, array( 'global', 'all', 'vehicle', 'category', 'location', 'site' ), 'global' );
	}

	private static function minor_field( $minor ) {
		return GWR_Money::minor_to_decimal( absint( $minor ) );
	}

	private static function secret_value( $input, $existing, $key ) {
		if ( ! empty( $input[ $key . '_clear' ] ) ) {
			return '';
		}
		$value = sanitize_text_field( $input[ $key ] ?? '' );
		if ( '' === $value || preg_match( '/^\*+$/', $value ) ) {
			return sanitize_text_field( $existing[ $key ] ?? '' );
		}
		return $value;
	}
}

/**
 * Coupon validation and usage accounting.
 */
class GWR_Coupon_Service {
	/**
	 * Normalize coupon codes.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function normalize_code( $code ) {
		return strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( (string) $code ) ) );
	}

	/**
	 * Return coupons.
	 *
	 * @return array
	 */
	public static function coupons() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . GWR_Pricing_Service::coupons_table() . ' ORDER BY active DESC, code ASC', ARRAY_A );
	}

	/**
	 * Calculate coupon discount without marking usage.
	 *
	 * @param string $code Coupon code.
	 * @param array  $context Pricing context.
	 * @return array|WP_Error
	 */
	public static function calculate_discount( $code, $context ) {
		$code = self::normalize_code( $code );
		if ( '' === $code ) {
			return array();
		}
		$coupon = self::get_by_code( $code );
		if ( ! $coupon || empty( $coupon['active'] ) ) {
			return new WP_Error( 'gwr_coupon_invalid', __( 'Il coupon non e valido.', 'gest-web-rent' ) );
		}
		$valid = self::validate_coupon( $coupon, $context );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$subtotal = absint( $context['subtotal_minor'] ?? 0 );
		if ( 'amount' === $coupon['discount_type'] ) {
			$amount = absint( $coupon['value_minor'] );
		} elseif ( 'free_days' === $coupon['discount_type'] ) {
			$days = max( 1, absint( $context['days'] ?? 1 ) );
			$amount = $days ? (int) floor( $subtotal / $days ) * absint( $coupon['free_days'] ) : 0;
		} else {
			$amount = GWR_Money::percent_amount( $subtotal, $coupon['percent_value'] );
		}
		if ( ! empty( $coupon['max_discount_minor'] ) ) {
			$amount = min( $amount, absint( $coupon['max_discount_minor'] ) );
		}
		$amount = min( $subtotal, max( 0, $amount ) );
		return array(
			'coupon_id'    => absint( $coupon['id'] ),
			'code'         => $coupon['code'],
			'type'         => $coupon['discount_type'],
			'amount_minor' => $amount,
			'description'  => sanitize_text_field( $coupon['description'] ),
		);
	}

	/**
	 * Reserve a coupon usage after booking creation.
	 *
	 * @param int    $coupon_id Coupon ID.
	 * @param int    $booking_id Booking ID.
	 * @param string $email Customer email.
	 * @param int    $amount_minor Amount.
	 * @return void
	 */
	public static function reserve_usage( $coupon_id, $booking_id, $email, $amount_minor ) {
		if ( ! $coupon_id ) {
			return;
		}
		global $wpdb;
		$wpdb->insert(
			GWR_Pricing_Service::coupon_usages_table(),
			array(
				'coupon_id'      => absint( $coupon_id ),
				'booking_id'     => absint( $booking_id ),
				'customer_email' => sanitize_email( $email ),
				'amount_minor'   => absint( $amount_minor ),
				'status'         => 'reserved',
				'created_at'     => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Mark coupon usage as used.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public static function mark_used_by_booking( $booking_id ) {
		global $wpdb;
		$wpdb->update(
			GWR_Pricing_Service::coupon_usages_table(),
			array( 'status' => 'used', 'used_at' => current_time( 'mysql' ) ),
			array( 'booking_id' => absint( $booking_id ), 'status' => 'reserved' )
		);
	}

	/**
	 * Release coupon usage.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public static function release_by_booking( $booking_id ) {
		global $wpdb;
		$wpdb->update(
			GWR_Pricing_Service::coupon_usages_table(),
			array( 'status' => 'released', 'released_at' => current_time( 'mysql' ) ),
			array( 'booking_id' => absint( $booking_id ), 'status' => 'reserved' )
		);
	}

	private static function get_by_code( $code ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GWR_Pricing_Service::coupons_table() . ' WHERE code=%s', self::normalize_code( $code ) ), ARRAY_A );
	}

	private static function validate_coupon( $coupon, $context ) {
		$today = current_time( 'Y-m-d' );
		if ( ! empty( $coupon['start_date'] ) && $today < $coupon['start_date'] ) {
			return new WP_Error( 'gwr_coupon_not_started', __( 'Il coupon non e ancora valido.', 'gest-web-rent' ) );
		}
		if ( ! empty( $coupon['end_date'] ) && $today > $coupon['end_date'] ) {
			return new WP_Error( 'gwr_coupon_expired', __( 'Il coupon e scaduto.', 'gest-web-rent' ) );
		}
		if ( absint( $coupon['min_spend_minor'] ) && absint( $context['subtotal_minor'] ?? 0 ) < absint( $coupon['min_spend_minor'] ) ) {
			return new WP_Error( 'gwr_coupon_min_spend', __( 'Il totale non raggiunge il minimo richiesto dal coupon.', 'gest-web-rent' ) );
		}
		if ( ! GWR_Pricing_Service::days_match( $coupon, absint( $context['days'] ?? 1 ) ) ) {
			return new WP_Error( 'gwr_coupon_duration', __( 'Il coupon non e valido per questa durata.', 'gest-web-rent' ) );
		}
		if ( ! self::scope_matches_coupon( $coupon, $context['vehicle'] ?? array() ) ) {
			return new WP_Error( 'gwr_coupon_scope', __( 'Il coupon non e valido per questo veicolo.', 'gest-web-rent' ) );
		}
		if ( ! self::email_allowed( $coupon, sanitize_email( $context['customer_email'] ?? '' ) ) ) {
			return new WP_Error( 'gwr_coupon_email', __( 'Il coupon non e autorizzato per questa email.', 'gest-web-rent' ) );
		}
		if ( self::usage_limit_reached( $coupon, sanitize_email( $context['customer_email'] ?? '' ) ) ) {
			return new WP_Error( 'gwr_coupon_limit', __( 'Il coupon ha raggiunto il limite di utilizzi.', 'gest-web-rent' ) );
		}
		return true;
	}

	private static function scope_matches_coupon( $coupon, $vehicle ) {
		$type = sanitize_key( $coupon['scope_type'] ?? 'global' );
		if ( in_array( $type, array( 'global', 'all' ), true ) ) {
			return true;
		}
		$values = array_filter( array_map( 'trim', explode( ',', (string) $coupon['scope_ids'] ) ) );
		if ( 'vehicle' === $type ) {
			return in_array( (string) absint( $vehicle['id'] ?? 0 ), $values, true );
		}
		if ( 'category' === $type ) {
			return in_array( strtolower( (string) ( $vehicle['category'] ?? '' ) ), array_map( 'strtolower', $values ), true );
		}
		if ( 'location' === $type || 'site' === $type ) {
			return in_array( strtolower( (string) ( $vehicle['location'] ?? '' ) ), array_map( 'strtolower', $values ), true );
		}
		return false;
	}

	private static function email_allowed( $coupon, $email ) {
		$allowed = array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', (string) $coupon['allowed_emails'] ) ) );
		return empty( $allowed ) || in_array( $email, $allowed, true );
	}

	private static function usage_limit_reached( $coupon, $email ) {
		global $wpdb;
		$where = "coupon_id=%d AND status IN ('reserved','used')";
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . GWR_Pricing_Service::coupon_usages_table() . ' WHERE ' . $where, absint( $coupon['id'] ) ) );
		if ( ! empty( $coupon['total_uses'] ) && $count >= absint( $coupon['total_uses'] ) ) {
			return true;
		}
		if ( $email && ! empty( $coupon['uses_per_email'] ) ) {
			$email_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . GWR_Pricing_Service::coupon_usages_table() . " WHERE coupon_id=%d AND customer_email=%s AND status IN ('reserved','used')", absint( $coupon['id'] ), $email ) );
			return $email_count >= absint( $coupon['uses_per_email'] );
		}
		return false;
	}
}
