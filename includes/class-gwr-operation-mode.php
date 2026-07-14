<?php
/**
 * Central operation-mode helpers.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps feature availability consistent across admin, frontend and services.
 */
class GWR_Operation_Mode {
	const OPTION_NAME = 'gwr_settings';

	/** Allowed operation modes. */
	public static function modes() {
		return array( 'showcase', 'request', 'booking' );
	}

	/** Human-readable mode labels. */
	public static function labels() {
		return array(
			'showcase' => __( 'Vetrina e richieste di informazioni', 'gest-web-rent' ),
			'request'  => __( 'Richiesta di prenotazione', 'gest-web-rent' ),
			'booking'  => __( 'Prenotazione online', 'gest-web-rent' ),
		);
	}

	/** Read the single canonical mode setting. */
	public static function get() {
		$settings = get_option( self::OPTION_NAME, array() );
		$mode     = is_array( $settings ) ? sanitize_key( $settings['operation_mode'] ?? '' ) : '';

		return in_array( $mode, self::modes(), true ) ? $mode : self::safe_default();
	}

	/**
	 * Existing installations default to request mode unless Stripe was explicitly
	 * enabled and has a usable server key. This never enables payments by itself.
	 */
	public static function safe_default() {
		$pricing = get_option( 'gwr_economic_settings', array() );
		if ( is_array( $pricing ) && ! empty( $pricing['stripe_enabled'] ) && self::stripe_secret( $pricing ) ) {
			return 'booking';
		}

		return 'request';
	}

	/** Whether an online payment provider is configured for booking mode. */
	public static function payments_available() {
		if ( 'booking' !== self::get() ) {
			return false;
		}

		$pricing  = get_option( 'gwr_economic_settings', array() );
		$available = is_array( $pricing ) && ! empty( $pricing['stripe_enabled'] ) && '' !== self::stripe_secret( $pricing );

		return (bool) apply_filters( 'gwr_payments_are_available', $available, $pricing );
	}

	/** Return the active Stripe secret without exposing it outside this class. */
	private static function stripe_secret( $pricing ) {
		$mode = 'live' === sanitize_key( $pricing['stripe_mode'] ?? '' ) ? 'live' : 'test';
		$key  = 'live' === $mode ? 'stripe_live_secret_key' : 'stripe_test_secret_key';

		return trim( (string) ( $pricing[ $key ] ?? '' ) );
	}
}

/** Return the active plugin operation mode. */
function gwr_get_operation_mode() {
	return GWR_Operation_Mode::get();
}

/** Whether the plugin is operating as a public showcase. */
function gwr_is_showcase_mode() {
	return 'showcase' === gwr_get_operation_mode();
}

/** Whether the plugin accepts manually reviewed booking requests. */
function gwr_is_request_mode() {
	return 'request' === gwr_get_operation_mode();
}

/** Whether the complete booking workflow is active. */
function gwr_is_booking_mode() {
	return 'booking' === gwr_get_operation_mode();
}

/** Whether a configured online payment provider may be used. */
function gwr_payments_are_available() {
	return GWR_Operation_Mode::payments_available();
}
