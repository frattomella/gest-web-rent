<?php
/**
 * Plugin Name: Gest Web Rent
 * Plugin URI: https://github.com/frattomella/gest-web-rent
 * Description: Gestione veicoli a noleggio con catalogo frontend, disponibilita e contatti WhatsApp Business/email.
 * Version: 1.3.0
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

define( 'GWR_VERSION', '1.3.0' );
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
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-cpt.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-admin.php';
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
