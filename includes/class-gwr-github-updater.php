<?php
/**
 * GitHub Releases updater for Gest Web Rent.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

class GWR_GitHub_Updater {
	const OWNER             = 'frattomella';
	const REPO              = 'gest-web-rent';
	const SLUG              = 'gest-web-rent';
	const OPTION_NAME       = 'gwr_settings';
	const TRANSIENT_NAME    = 'gwr_github_release';
	const RELEASE_TRANSIENT = 'gwr_github_release_payload';
	const RELEASE_TTL       = 900;
	const ERROR_TRANSIENT   = 'gwr_github_release_error';

	private static $initialized = false;

	public function __construct( $plugin_file = '', $version = '' ) {
		self::init();
	}

	public static function init() {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'http_request_args', array( __CLASS__, 'filter_http_args' ), 20, 2 );
		add_filter( 'upgrader_post_install', array( __CLASS__, 'fix_install_directory' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache_after_upgrade' ), 20, 2 );
	}

	public static function clear_cache() {
		delete_site_transient( self::RELEASE_TRANSIENT );
		delete_site_transient( self::TRANSIENT_NAME );
		delete_site_transient( self::ERROR_TRANSIENT );
		delete_site_transient( 'update_plugins' );
	}

	public static function summary() {
		$release = self::latest_release();
		return array(
			'enabled'        => true,
			'repository'     => self::repository(),
			'repository_url' => self::repository_url(),
			'branch'         => 'main',
			'asset_name'     => self::asset_name(),
			'has_token'      => ! empty( self::token() ),
			'latest_version' => is_wp_error( $release ) ? '' : ( $release['version'] ?? '' ),
			'package_url'    => is_wp_error( $release ) ? '' : ( $release['package_url'] ?? '' ),
			'last_error'     => self::last_error(),
		);
	}

	public static function repository() { return self::OWNER . '/' . self::REPO; }
	public static function asset_name() { return self::SLUG . '.zip'; }
	public static function current_version() { return ltrim( (string) GWR_VERSION, 'vV' ); }
	public static function plugin_file() { return plugin_basename( GWR_PLUGIN_FILE ); }
	public static function plugin_slug() { return dirname( plugin_basename( GWR_PLUGIN_FILE ) ); }
	public static function repository_url() { return 'https://github.com/' . self::repository(); }

	public static function token() {
		$options = get_option( self::OPTION_NAME, array() );
		return is_array( $options ) && ! empty( $options['github_access_token'] ) ? trim( (string) $options['github_access_token'] ) : '';
	}

	public static function last_error() {
		$error = get_site_transient( self::ERROR_TRANSIENT );
		return is_string( $error ) ? $error : '';
	}

	public static function latest_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::RELEASE_TRANSIENT );
			if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
				return $cached;
			}
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::repository() . '/releases/latest',
			array( 'timeout' => 15, 'headers' => self::api_headers( false ) )
		);

		if ( is_wp_error( $response ) ) {
			self::store_error( $response->get_error_message() );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$error = new WP_Error( 'gwr_github_http', sprintf( 'GitHub ha risposto con HTTP %d.', $code ) );
			self::store_error( $error->get_error_message() );
			return $error;
		}

		$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$release = self::normalize_release_payload( $payload );
		if ( ! is_array( $release ) || empty( $release['version'] ) ) {
			$error = new WP_Error( 'gwr_github_payload', 'Release GitHub non valida.' );
			self::store_error( $error->get_error_message() );
			return $error;
		}

		delete_site_transient( self::ERROR_TRANSIENT );
		set_site_transient( self::RELEASE_TRANSIENT, $release, self::RELEASE_TTL );
		set_site_transient( self::TRANSIENT_NAME, $payload, self::RELEASE_TTL );
		return $release;
	}

	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) { $transient = new stdClass(); }
		if ( empty( $transient->checked ) || ! is_array( $transient->checked ) ) { return $transient; }
		$plugin_file = self::plugin_file();
		if ( ! isset( $transient->checked[ $plugin_file ] ) ) { return $transient; }
		$release = self::latest_release();
		if ( is_wp_error( $release ) || empty( $release['version'] ) || empty( $release['package_url'] ) ) { return $transient; }

		if ( version_compare( $release['version'], self::current_version(), '<=' ) ) {
			if ( empty( $transient->no_update ) || ! is_array( $transient->no_update ) ) { $transient->no_update = array(); }
			$transient->no_update[ $plugin_file ] = (object) array( 'slug' => self::plugin_slug(), 'plugin' => $plugin_file, 'new_version' => self::current_version(), 'url' => $release['html_url'] ?: self::repository_url(), 'package' => '' );
			return $transient;
		}

		if ( empty( $transient->response ) || ! is_array( $transient->response ) ) { $transient->response = array(); }
		if ( isset( $transient->no_update ) && is_array( $transient->no_update ) ) { unset( $transient->no_update[ $plugin_file ] ); }
		$transient->response[ $plugin_file ] = (object) array( 'id' => self::repository(), 'slug' => self::plugin_slug(), 'plugin' => $plugin_file, 'new_version' => $release['version'], 'url' => $release['html_url'] ?: self::repository_url(), 'package' => $release['package_url'], 'requires' => '6.0', 'requires_php' => '7.4', 'tested' => '6.8', 'upgrade_notice' => self::upgrade_notice( $release ) );
		return $transient;
	}

	public function check_for_update( $transient ) { return self::inject_update( $transient ); }

	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || sanitize_key( (string) $args->slug ) !== sanitize_key( self::plugin_slug() ) ) { return $result; }
		$release = self::latest_release();
		if ( is_wp_error( $release ) ) { return $result; }
		$headers = get_file_data( GWR_PLUGIN_FILE, array( 'Name' => 'Plugin Name', 'Description' => 'Description', 'Author' => 'Author' ) );
		return (object) array( 'name' => $headers['Name'] ?: 'Gest Web Rent', 'slug' => self::plugin_slug(), 'version' => $release['version'], 'author' => $headers['Author'], 'homepage' => $release['html_url'] ?: self::repository_url(), 'download_link' => $release['package_url'], 'last_updated' => $release['published_at'], 'requires' => '6.0', 'requires_php' => '7.4', 'tested' => '6.8', 'sections' => array( 'description' => wpautop( esc_html( $headers['Description'] ) ), 'changelog' => wpautop( esc_html( (string) ( $release['body'] ?: 'Nessun changelog disponibile.' ) ) ) ) );
	}

	public function plugin_information( $result, $action, $args ) { return self::plugin_info( $result, $action, $args ); }

	public static function filter_http_args( $args, $url ) {
		if ( 0 !== strpos( (string) $url, 'https://api.github.com/repos/' . self::repository() . '/' ) ) { return $args; }
		$headers = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		$binary = false !== strpos( (string) $url, '/releases/assets/' ) || false !== strpos( (string) $url, '/zipball/' );
		$args['headers'] = array_merge( $headers, self::api_headers( $binary ) );
		return $args;
	}

	public static function fix_install_directory( $response, $hook_extra, $result ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::plugin_file() || empty( $result['destination'] ) ) { return $response; }
		$expected = trailingslashit( WP_PLUGIN_DIR ) . self::plugin_slug();
		$current = untrailingslashit( (string) $result['destination'] );
		if ( $current === untrailingslashit( $expected ) ) { return $result; }
		global $wp_filesystem;
		if ( ! $wp_filesystem ) { return $result; }
		if ( $wp_filesystem->exists( $expected ) ) { $wp_filesystem->delete( $expected, true ); }
		if ( function_exists( 'move_dir' ) ) { $moved = move_dir( $current, $expected, true ); if ( is_wp_error( $moved ) ) { return $moved; } }
		elseif ( ! $wp_filesystem->move( $current, $expected, true ) ) { return new WP_Error( 'gwr_github_move_failed', 'Impossibile spostare il plugin aggiornato nella cartella finale.' ); }
		$result['destination'] = $expected;
		$result['destination_name'] = basename( $expected );
		return $result;
	}

	public function fix_source_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) { return $source; }

	public static function clear_cache_after_upgrade( $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) { return; }
		if ( ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) && in_array( self::plugin_file(), $hook_extra['plugins'], true ) ) || ( ! empty( $hook_extra['plugin'] ) && $hook_extra['plugin'] === self::plugin_file() ) ) { self::clear_cache(); }
	}

	public function download_private_package( $reply, $package, $upgrader, $hook_extra = array() ) { return $reply; }

	private static function normalize_release_payload( $payload ) {
		if ( ! is_array( $payload ) || empty( $payload['tag_name'] ) ) { return false; }
		$asset = self::match_asset( isset( $payload['assets'] ) && is_array( $payload['assets'] ) ? $payload['assets'] : array() );
		$asset_api_url = ! empty( $asset['url'] ) ? esc_url_raw( (string) $asset['url'] ) : '';
		$asset_browser_url = ! empty( $asset['browser_download_url'] ) ? esc_url_raw( (string) $asset['browser_download_url'] ) : '';
		$zipball_url = ! empty( $payload['zipball_url'] ) ? esc_url_raw( (string) $payload['zipball_url'] ) : '';
		$release = array( 'tag' => sanitize_text_field( (string) $payload['tag_name'] ), 'version' => ltrim( sanitize_text_field( (string) $payload['tag_name'] ), 'vV' ), 'name' => sanitize_text_field( (string) ( $payload['name'] ?? $payload['tag_name'] ) ), 'body' => (string) ( $payload['body'] ?? '' ), 'html_url' => esc_url_raw( (string) ( $payload['html_url'] ?? self::repository_url() ) ), 'published_at' => sanitize_text_field( (string) ( $payload['published_at'] ?? '' ) ), 'zipball_url' => $zipball_url, 'asset_api_url' => $asset_api_url, 'asset_browser_url' => $asset_browser_url, 'asset_name' => ! empty( $asset['name'] ) ? sanitize_file_name( (string) $asset['name'] ) : '' );
		// Public repositories should use browser_download_url. The GitHub API asset URL needs an octet-stream Accept header and can produce a non-ZIP JSON response in WordPress updater flows, which may leave the plugin deactivated after update.
		$release['package_url'] = self::token() && $asset_api_url ? $asset_api_url : ( $asset_browser_url ?: $zipball_url );
		return $release;
	}

	private static function match_asset( $assets ) {
		if ( empty( $assets ) ) { return array(); }
		$preferred = self::asset_name();
		foreach ( $assets as $asset ) { if ( is_array( $asset ) && ! empty( $asset['name'] ) && sanitize_file_name( (string) $asset['name'] ) === $preferred ) { return $asset; } }
		foreach ( $assets as $asset ) { if ( is_array( $asset ) && ! empty( $asset['name'] ) && '.zip' === substr( strtolower( (string) $asset['name'] ), -4 ) ) { return $asset; } }
		return array();
	}

	private static function api_headers( $binary = false ) {
		$headers = array( 'User-Agent' => self::SLUG . '/' . self::current_version() . '; ' . home_url( '/' ), 'Accept' => $binary ? 'application/octet-stream' : 'application/vnd.github+json' );
		$token = self::token();
		if ( '' !== $token ) { $headers['Authorization'] = 'Bearer ' . $token; }
		return $headers;
	}

	private static function store_error( $message ) { set_site_transient( self::ERROR_TRANSIENT, sanitize_text_field( (string) $message ), self::RELEASE_TTL ); }
	private static function upgrade_notice( $release ) { return empty( $release['body'] ) ? '' : wp_trim_words( wp_strip_all_tags( (string) $release['body'] ), 24, '...' ); }
}
