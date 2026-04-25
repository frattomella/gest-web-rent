<?php
/**
 * Plugin Name: Gest Web Rent
 * Plugin URI: https://github.com/frattomella/gest-web-rent
 * Description: Gestione veicoli a noleggio con catalogo frontend, disponibilita e contatti WhatsApp Business/email.
 * Version: 1.0.0
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

define( 'GWR_VERSION', '1.0.0' );
define( 'GWR_PLUGIN_FILE', __FILE__ );
define( 'GWR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'GWR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GWR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GWR_PLUGIN_DIR . 'includes/class-gwr-plugin.php';
require_once GWR_PLUGIN_DIR . 'includes/class-gwr-github-updater.php';

/**
 * Boot the plugin after WordPress has loaded translations and pluggable APIs.
 */
function gwr_bootstrap() {
	load_plugin_textdomain( 'gest-web-rent', false, dirname( GWR_PLUGIN_BASENAME ) . '/languages' );

	GWR_Plugin::instance()->init();

	if ( is_admin() || wp_doing_cron() ) {
		new GWR_GitHub_Updater( GWR_PLUGIN_FILE, GWR_VERSION );
	}
}
add_action( 'plugins_loaded', 'gwr_bootstrap' );

register_activation_hook( __FILE__, array( 'GWR_Plugin', 'activate' ) );
