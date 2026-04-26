<?php
/**
 * Backward-compatible plugin facade.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'GWR_Plugin' ) ) {
	/**
	 * Compatibility wrapper for older checks expecting class-gwr-plugin.php.
	 */
	class GWR_Plugin {
		/**
		 * Proxy activation to the current core class.
		 *
		 * @return void
		 */
		public static function activate() {
			GWR_Core::activate();
		}

		/**
		 * Proxy post type registration to the current CPT class.
		 *
		 * @return void
		 */
		public static function register_post_type() {
			GWR_CPT::register_post_types();
		}
	}
}
