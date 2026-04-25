<?php
/**
 * Core bootstrap for Gest Web Rent.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates plugin modules.
 */
class GWR_Core {
	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public function boot() {
		GWR_CPT::init();
		GWR_Admin::init();
		GWR_Frontend::init();
		GWR_Blocks::init();

		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( is_admin() || wp_doing_cron() ) {
			new GWR_GitHub_Updater( GWR_PLUGIN_FILE, GWR_VERSION );
		}
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'gest-web-rent', false, dirname( GWR_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		GWR_CPT::register_post_types();
		GWR_CPT::register_taxonomies();

		if ( false === get_option( GWR_Admin::OPTION_NAME, false ) ) {
			add_option( GWR_Admin::OPTION_NAME, GWR_Admin::default_settings(), '', false );
		}

		flush_rewrite_rules();
	}

	/**
	 * Deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
