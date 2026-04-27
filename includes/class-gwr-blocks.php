<?php
/**
 * Editor integration for Gest Web Rent catalog.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register a dynamic catalog block that renders the same responsive shortcode.
 */
class GWR_Blocks {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
	}

	/**
	 * Register dynamic blocks.
	 *
	 * @return void
	 */
	public static function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'gwr-catalog-block-editor',
			GWR_PLUGIN_URL . 'public/assets/js/gwr-catalog-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
			gwr_asset_version( 'public/assets/js/gwr-catalog-block.js' ),
			true
		);

		register_block_type(
			'gest-web-rent/catalog',
			array(
				'api_version'     => 2,
				'editor_script'   => 'gwr-catalog-block-editor',
				'render_callback' => array( __CLASS__, 'render_catalog_block' ),
				'attributes'      => array(
					'title' => array(
						'type'    => 'string',
						'default' => __( 'Catalogo noleggio', 'gest-web-rent' ),
					),
					'subtitle' => array(
						'type'    => 'string',
						'default' => __( 'Scegli le date, filtra il parco veicoli e apri i dettagli senza cambiare pagina.', 'gest-web-rent' ),
					),
					'max_width' => array(
						'type'    => 'string',
						'default' => '1380',
					),
				),
			)
		);
	}

	/**
	 * Render the dynamic catalog block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_catalog_block( $attributes = array() ) {
		if ( ! class_exists( 'GWR_Frontend' ) ) {
			return '';
		}

		$atts = array(
			'title'     => isset( $attributes['title'] ) ? sanitize_text_field( $attributes['title'] ) : __( 'Catalogo noleggio', 'gest-web-rent' ),
			'subtitle'  => isset( $attributes['subtitle'] ) ? sanitize_text_field( $attributes['subtitle'] ) : __( 'Scegli le date, filtra il parco veicoli e apri i dettagli senza cambiare pagina.', 'gest-web-rent' ),
			'max_width' => isset( $attributes['max_width'] ) ? absint( $attributes['max_width'] ) : 1380,
		);

		return GWR_Frontend::catalog_shortcode( $atts );
	}
}
