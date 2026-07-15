<?php
/**
 * Plugin Name: Gest Web Rent
 * Plugin URI: https://github.com/frattomella/gest-web-rent
 * Description: Gestione veicoli a noleggio con catalogo frontend, disponibilita e contatti WhatsApp Business/email.
 * Version: 2.0.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Francesco Frattomella
 * Author URI: https://github.com/frattomella
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gest-web-rent
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'GWR_VERSION', '2.0.2' );
define( 'GWR_PLUGIN_FILE', __FILE__ );
define( 'GWR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'GWR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GWR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GWR_GITHUB_REPOSITORY', 'frattomella/gest-web-rent' );

/**
 * Version assets by modification time during development and release builds.
 *
 * @param string $relative_path Asset path relative to the plugin directory.
 * @return string
 */
function gwr_asset_version( $relative_path ) {
	$relative_path = ltrim( (string) $relative_path, '/\\' );
	$absolute_path = GWR_PLUGIN_DIR . $relative_path;

	if ( file_exists( $absolute_path ) ) {
		$mtime = filemtime( $absolute_path );

		if ( $mtime ) {
			return (string) $mtime;
		}
	}

	return GWR_VERSION;
}

require_once GWR_PLUGIN_DIR . 'includes/class-gwr-github-updater.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-money.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-operation-mode.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-cpt.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-rental-terms.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-pricing-service.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-bookings.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-payment-service.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-pdf-service.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-document-template-service.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-document-service.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-attachment-service.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-notification-service.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-customer-request-service.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-customer-portal.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-inquiries.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-admin.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-rental-terms-admin.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-bookings-admin.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-pricing-admin.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-payments-admin.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-documents-admin.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-frontend.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-blocks.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-core.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-plugin.php';

/**
 * Boot the plugin.
 */
function gwr_boot_plugin() {
	$core = new GWR_Core();
	$core->boot();
}
gwr_boot_plugin();

register_activation_hook( __FILE__, array( 'GWR_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GWR_Core', 'deactivate' ) );
