<?php
/**
 * Money helpers for integer minor-unit calculations.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Centralized money utility.
 */
class GWR_Money {
	/**
	 * Convert a decimal amount to cents without relying on binary floats.
	 *
	 * @param mixed $amount Decimal amount.
	 * @return int
	 */
	public static function decimal_to_minor( $amount ) {
		if ( null === $amount || '' === $amount ) {
			return 0;
		}

		if ( is_int( $amount ) ) {
			return $amount * 100;
		}

		$value = trim( str_replace( ',', '.', (string) $amount ) );
		if ( '' === $value || ! preg_match( '/^-?\d+(?:\.\d+)?$/', $value ) ) {
			return 0;
		}

		$negative = '-' === $value[0];
		$value    = ltrim( $value, '+-' );
		$parts    = explode( '.', $value, 2 );
		$euros    = absint( $parts[0] );
		$cents    = isset( $parts[1] ) ? substr( preg_replace( '/\D/', '', $parts[1] ) . '00', 0, 2 ) : '00';
		$minor    = ( $euros * 100 ) + absint( $cents );

		return $negative ? -1 * $minor : $minor;
	}

	/**
	 * Convert cents to a fixed decimal string.
	 *
	 * @param int $minor Minor units.
	 * @return string
	 */
	public static function minor_to_decimal( $minor ) {
		return number_format( (int) $minor / 100, 2, '.', '' );
	}

	/**
	 * Format minor units for display.
	 *
	 * @param int    $minor Minor units.
	 * @param string $currency Currency.
	 * @return string
	 */
	public static function format_minor( $minor, $currency = 'EUR' ) {
		return number_format_i18n( (int) $minor / 100, 2 ) . ' ' . self::normalize_currency( $currency );
	}

	/**
	 * Normalize a currency code.
	 *
	 * @param string $currency Currency.
	 * @return string
	 */
	public static function normalize_currency( $currency ) {
		$currency = strtoupper( preg_replace( '/[^A-Z]/', '', (string) $currency ) );
		return 3 === strlen( $currency ) ? $currency : 'EUR';
	}

	/**
	 * Calculate a percentage amount using basis points.
	 *
	 * @param int   $minor Amount in minor units.
	 * @param mixed $percent Percent.
	 * @return int
	 */
	public static function percent_amount( $minor, $percent ) {
		$basis_points = (int) round( (float) str_replace( ',', '.', (string) $percent ) * 100 );
		if ( 0 === $basis_points || 0 === (int) $minor ) {
			return 0;
		}

		$value = (int) $minor * $basis_points;
		if ( $value >= 0 ) {
			return (int) floor( ( $value + 5000 ) / 10000 );
		}

		return -1 * (int) floor( ( abs( $value ) + 5000 ) / 10000 );
	}

	/**
	 * Round an amount according to configured policy.
	 *
	 * @param int    $minor Amount in minor units.
	 * @param string $mode Rounding mode.
	 * @param int    $increment_minor Increment in minor units.
	 * @return int
	 */
	public static function round_minor( $minor, $mode = 'nearest', $increment_minor = 1 ) {
		$increment_minor = max( 1, absint( $increment_minor ) );
		if ( 1 === $increment_minor ) {
			return (int) $minor;
		}

		$minor = (int) $minor;
		if ( 'up' === $mode ) {
			return (int) ceil( $minor / $increment_minor ) * $increment_minor;
		}
		if ( 'down' === $mode ) {
			return (int) floor( $minor / $increment_minor ) * $increment_minor;
		}

		return (int) round( $minor / $increment_minor ) * $increment_minor;
	}
}
