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
		GWR_Rental_Terms::maybe_initialize();
		GWR_Pricing_Service::init();
		GWR_Bookings::init();
		GWR_Payment_Service::init();
		GWR_Document_Service::init();
		GWR_Attachment_Service::init();
		GWR_Notification_Service::init();
		GWR_Customer_Request_Service::init();
		GWR_Customer_Portal::init();
		GWR_Admin::init();
		GWR_Rental_Terms_Admin::init();
		GWR_Bookings_Admin::init();
		GWR_Pricing_Admin::init();
		GWR_Payments_Admin::init();
		GWR_Documents_Admin::init();
		GWR_Frontend::init();
		GWR_Blocks::init();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( 'GWR_CPT', 'maybe_upgrade_storage' ), 5 );

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
		GWR_CPT::create_tables();
		GWR_Pricing_Service::create_tables();
		GWR_Bookings::create_tables();
		GWR_Payment_Service::create_tables();
		GWR_Document_Service::create_tables();
		GWR_Attachment_Service::create_tables();
		GWR_Notification_Service::create_tables();
		GWR_Customer_Request_Service::create_tables();
		GWR_CPT::maybe_migrate_cpt_vehicles();
		update_option( 'gwr_db_version', GWR_CPT::DB_VERSION, false );
		update_option( GWR_Pricing_Service::DB_OPTION, GWR_Pricing_Service::DB_VERSION, false );
		update_option( GWR_Bookings::DB_OPTION, GWR_Bookings::DB_VERSION, false );
		update_option( GWR_Payment_Service::DB_OPTION, GWR_Payment_Service::DB_VERSION, false );
		update_option( GWR_Document_Service::DB_OPTION, GWR_Document_Service::DB_VERSION, false );
		update_option( GWR_Attachment_Service::DB_OPTION, GWR_Attachment_Service::DB_VERSION, false );
		update_option( GWR_Notification_Service::DB_OPTION, GWR_Notification_Service::DB_VERSION, false );
		update_option( GWR_Customer_Request_Service::DB_OPTION, GWR_Customer_Request_Service::DB_VERSION, false );

		if ( false === get_option( GWR_Admin::OPTION_NAME, false ) ) {
			add_option( GWR_Admin::OPTION_NAME, GWR_Admin::default_settings(), '', false );
		}
		if ( false === get_option( GWR_Rental_Terms::GLOBAL_OPTION, false ) ) {
			add_option( GWR_Rental_Terms::GLOBAL_OPTION, GWR_Rental_Terms::defaults(), '', false );
		}
		if ( false === get_option( GWR_Rental_Terms::VEHICLE_OPTION, false ) ) {
			add_option( GWR_Rental_Terms::VEHICLE_OPTION, array(), '', false );
		}
		if ( false === get_option( GWR_Pricing_Service::OPTION_NAME, false ) ) {
			add_option( GWR_Pricing_Service::OPTION_NAME, GWR_Pricing_Service::default_settings(), '', false );
		}
		if ( false === get_option( GWR_Document_Service::OPTION_NAME, false ) ) {
			add_option( GWR_Document_Service::OPTION_NAME, GWR_Document_Service::default_settings(), '', false );
		}
		if ( false === get_option( GWR_Notification_Service::OPTION_NAME, false ) ) {
			add_option( GWR_Notification_Service::OPTION_NAME, GWR_Notification_Service::defaults(), '', false );
		}

		flush_rewrite_rules();
	}

	/**
	 * Deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'gwr_expire_pending_bookings' );
		wp_clear_scheduled_hook( 'gwr_reconcile_payments' );
		wp_clear_scheduled_hook( GWR_Notification_Service::CRON_HOOK );
		flush_rewrite_rules();
	}
}
