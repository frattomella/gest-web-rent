<?php
/**
 * Gutenberg compatibility shim.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Blocks are intentionally disabled as primary UX.
 */
class GWR_Blocks {
	/**
	 * No-op: Gest Web Rent now exposes the responsive catalog via shortcode.
	 *
	 * @return void
	 */
	public static function init() {}
}
