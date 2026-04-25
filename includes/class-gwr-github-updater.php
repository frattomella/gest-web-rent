<?php
/**
 * GitHub Releases updater for Gest Web Rent.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight updater that exposes GitHub Releases to WordPress core updates.
 */
class GWR_GitHub_Updater {
	const OWNER          = 'frattomella';
	const REPO           = 'gest-web-rent';
	const SLUG           = 'gest-web-rent';
	const OPTION_NAME    = 'gwr_settings';
	const TRANSIENT_NAME = 'gwr_github_release';

	/**
	 * Absolute plugin file.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin basename, e.g. gest-web-rent/gest-web-rent.php.
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Installed plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Absolute plugin file.
	 * @param string $version Installed plugin version.
	 */
	public function __construct( $plugin_file, $version ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->version         = $version;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_folder' ), 10, 4 );
		add_filter( 'upgrader_pre_download', array( $this, 'download_private_package' ), 10, 4 );
	}

	/**
	 * Add update data to the WordPress update transient.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public function check_for_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		if ( empty( $transient->checked ) || empty( $transient->checked[ $this->plugin_basename ] ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $transient;
		}

		$latest_version = $this->get_release_version( $release );
		$package_url    = $this->get_package_url( $release );

		if ( ! $latest_version || ! $package_url || ! version_compare( $latest_version, $this->version, '>' ) ) {
			return $transient;
		}

		if ( empty( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		$transient->response[ $this->plugin_basename ] = (object) array(
			'id'            => self::OWNER . '/' . self::REPO,
			'slug'          => self::SLUG,
			'plugin'        => $this->plugin_basename,
			'new_version'   => $latest_version,
			'url'           => $this->get_repository_url(),
			'package'       => $package_url,
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'tested'        => '6.8',
			'icons'         => array(),
			'banners'       => array(),
			'banners_rtl'   => array(),
			'upgrade_notice' => $this->get_upgrade_notice( $release ),
		);

		return $transient;
	}

	/**
	 * Provide details for the "View version details" modal.
	 *
	 * @param false|object|array $result Existing result.
	 * @param string             $action API action.
	 * @param object             $args API args.
	 * @return false|object|array
	 */
	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $result;
		}

		$version = $this->get_release_version( $release );
		$body    = ! empty( $release['body'] ) ? $release['body'] : __( 'Release pubblicata su GitHub.', 'gest-web-rent' );

		return (object) array(
			'name'          => 'Gest Web Rent',
			'slug'          => self::SLUG,
			'version'       => $version,
			'author'        => '<a href="https://github.com/' . esc_attr( self::OWNER ) . '">Francesco Frattomella</a>',
			'homepage'      => $this->get_repository_url(),
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'tested'        => '6.8',
			'last_updated'  => isset( $release['published_at'] ) ? $release['published_at'] : '',
			'sections'      => array(
				'description' => '<p>' . esc_html__( 'Gestione veicoli a noleggio con catalogo frontend, disponibilita e contatti WhatsApp Business/email.', 'gest-web-rent' ) . '</p>',
				'changelog'   => wp_kses_post( nl2br( esc_html( $body ) ) ),
			),
			'download_link' => $this->get_package_url( $release ),
		);
	}

	/**
	 * Ensure the extracted folder keeps the expected plugin directory name.
	 *
	 * GitHub source archives can extract as owner-repo-hash. The release workflow
	 * ships gest-web-rent.zip correctly, but this fallback keeps updates safe.
	 *
	 * @param string      $source Extracted source path.
	 * @param string      $remote_source Remote source path.
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $hook_extra Extra upgrade context.
	 * @return string
	 */
	public function fix_source_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( empty( $hook_extra['plugin'] ) || $this->plugin_basename !== $hook_extra['plugin'] ) {
			return $source;
		}

		if ( self::SLUG === basename( $source ) ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return $source;
		}

		$target = trailingslashit( $remote_source ) . self::SLUG;

		if ( $wp_filesystem->exists( $target ) ) {
			$wp_filesystem->delete( $target, true );
		}

		if ( $wp_filesystem->move( $source, $target, true ) ) {
			return $target;
		}

		return $source;
	}

	/**
	 * Download packages with an Authorization header when a private token exists.
	 *
	 * @param false|WP_Error|string $reply Existing pre-download result.
	 * @param string                $package Package URL.
	 * @param WP_Upgrader           $upgrader Upgrader instance.
	 * @param array                 $hook_extra Extra upgrade context.
	 * @return false|WP_Error|string
	 */
	public function download_private_package( $reply, $package, $upgrader, $hook_extra = array() ) {
		if ( false !== $reply ) {
			return $reply;
		}

		if ( empty( $hook_extra['plugin'] ) || $this->plugin_basename !== $hook_extra['plugin'] ) {
			return $reply;
		}

		$token = $this->get_token();

		if ( ! $token || false === strpos( $package, 'github.com' ) ) {
			return $reply;
		}

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp_file = wp_tempnam( $package );

		if ( ! $tmp_file ) {
			return new WP_Error( 'gwr_temp_file_failed', __( 'Impossibile creare un file temporaneo per il download.', 'gest-web-rent' ) );
		}

		$response = wp_remote_get(
			$package,
			array(
				'timeout'     => 60,
				'redirection' => 5,
				'headers'     => $this->get_github_headers( true ),
			)
		);

		if ( is_wp_error( $response ) ) {
			@unlink( $tmp_file );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== (int) $code ) {
			@unlink( $tmp_file );
			return new WP_Error( 'gwr_download_failed', sprintf( __( 'Download GitHub non riuscito. Codice risposta: %d', 'gest-web-rent' ), (int) $code ) );
		}

		$bytes = file_put_contents( $tmp_file, wp_remote_retrieve_body( $response ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( false === $bytes ) {
			@unlink( $tmp_file );
			return new WP_Error( 'gwr_write_failed', __( 'Impossibile salvare il pacchetto scaricato.', 'gest-web-rent' ) );
		}

		return $tmp_file;
	}

	/**
	 * Retrieve and cache latest GitHub release data.
	 *
	 * @return array|false
	 */
	private function get_latest_release() {
		$cached = get_site_transient( self::TRANSIENT_NAME );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::OWNER . '/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 15,
				'headers' => $this->get_github_headers(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== (int) $code ) {
			return false;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
			return false;
		}

		set_site_transient( self::TRANSIENT_NAME, $release, 6 * HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * Build GitHub request headers.
	 *
	 * @param bool $download Whether this is an asset download request.
	 * @return array
	 */
	private function get_github_headers( $download = false ) {
		$headers = array(
			'Accept'     => $download ? 'application/octet-stream' : 'application/vnd.github+json',
			'User-Agent' => self::SLUG . '-wordpress-updater',
		);

		$token = $this->get_token();

		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		return $headers;
	}

	/**
	 * Read optional GitHub token from plugin settings.
	 *
	 * @return string
	 */
	private function get_token() {
		$options = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $options ) || empty( $options['github_access_token'] ) ) {
			return '';
		}

		return trim( (string) $options['github_access_token'] );
	}

	/**
	 * Extract semantic version from release tag.
	 *
	 * @param array $release GitHub release data.
	 * @return string
	 */
	private function get_release_version( $release ) {
		if ( empty( $release['tag_name'] ) ) {
			return '';
		}

		return ltrim( (string) $release['tag_name'], 'vV' );
	}

	/**
	 * Resolve package URL from release assets, falling back to source ZIP.
	 *
	 * @param array $release GitHub release data.
	 * @return string
	 */
	private function get_package_url( $release ) {
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( empty( $asset['name'] ) || self::SLUG . '.zip' !== $asset['name'] ) {
					continue;
				}

				if ( $this->get_token() && ! empty( $asset['url'] ) ) {
					return esc_url_raw( $asset['url'] );
				}

				if ( ! empty( $asset['browser_download_url'] ) ) {
					return esc_url_raw( $asset['browser_download_url'] );
				}
			}
		}

		if ( ! empty( $release['zipball_url'] ) ) {
			return esc_url_raw( $release['zipball_url'] );
		}

		return '';
	}

	/**
	 * Build repository URL.
	 *
	 * @return string
	 */
	private function get_repository_url() {
		return 'https://github.com/' . self::OWNER . '/' . self::REPO;
	}

	/**
	 * Build a short upgrade notice from release body.
	 *
	 * @param array $release GitHub release data.
	 * @return string
	 */
	private function get_upgrade_notice( $release ) {
		if ( empty( $release['body'] ) ) {
			return '';
		}

		return wp_trim_words( wp_strip_all_tags( (string) $release['body'] ), 24, '...' );
	}
}
