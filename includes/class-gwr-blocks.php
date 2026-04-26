<?php
/**
 * Gutenberg dynamic blocks.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers Gest Web Rent editor components.
 */
class GWR_Blocks {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register block scripts, styles and block types.
	 *
	 * @return void
	 */
	public static function register_blocks() {
		wp_register_script(
			'gwr-blocks',
			GWR_PLUGIN_URL . 'blocks/gwr-blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
			gwr_asset_version( 'blocks/gwr-blocks.js' ),
			true
		);

		wp_register_style( 'gwr-public', GWR_PLUGIN_URL . 'public/assets/css/gwr-public.css', array(), gwr_asset_version( 'public/assets/css/gwr-public.css' ) );
		wp_register_style( 'gwr-editor', GWR_PLUGIN_URL . 'blocks/gwr-editor.css', array( 'wp-edit-blocks', 'gwr-public' ), gwr_asset_version( 'blocks/gwr-editor.css' ) );

		register_block_type(
			'gest-web-rent/catalog',
			array(
				'editor_script'   => 'gwr-blocks',
				'editor_style'    => 'gwr-editor',
				'style'           => 'gwr-public',
				'attributes'      => array(
					'limit'   => array( 'type' => 'number', 'default' => -1 ),
					'columns' => array( 'type' => 'number', 'default' => 3 ),
					'filters' => array( 'type' => 'boolean', 'default' => true ),
					'title'   => array( 'type' => 'string', 'default' => __( 'Veicoli a noleggio', 'gest-web-rent' ) ),
				),
				'render_callback' => array( __CLASS__, 'render_catalog_block' ),
			)
		);

		register_block_type(
			'gest-web-rent/availability',
			array(
				'editor_script'   => 'gwr-blocks',
				'editor_style'    => 'gwr-editor',
				'style'           => 'gwr-public',
				'attributes'      => array(
					'columns' => array( 'type' => 'number', 'default' => 3 ),
					'title'   => array( 'type' => 'string', 'default' => __( 'Verifica disponibilita', 'gest-web-rent' ) ),
				),
				'render_callback' => array( __CLASS__, 'render_availability_block' ),
			)
		);

		register_block_type(
			'gest-web-rent/vehicle',
			array(
				'editor_script'   => 'gwr-blocks',
				'editor_style'    => 'gwr-editor',
				'style'           => 'gwr-public',
				'attributes'      => array(
					'vehicleId' => array( 'type' => 'number', 'default' => 0 ),
				),
				'render_callback' => array( __CLASS__, 'render_vehicle_block' ),
			)
		);
	}

	/**
	 * Enqueue editor data.
	 *
	 * @return void
	 */
	public static function enqueue_editor_assets() {
		wp_enqueue_script( 'gwr-blocks' );
		wp_enqueue_style( 'gwr-editor' );

		wp_localize_script(
			'gwr-blocks',
			'gwrBlockData',
			array(
				'vehicles' => self::vehicle_options(),
			)
		);
	}

	/**
	 * Vehicle options for editor controls.
	 *
	 * @return array
	 */
	private static function vehicle_options() {
		$options = array(
			array(
				'label' => __( 'Veicolo corrente / automatico', 'gest-web-rent' ),
				'value' => 0,
			),
		);

		$query = new WP_Query(
			array(
				'post_type'      => GWR_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $query->posts as $vehicle_id ) {
			$options[] = array(
				'label' => get_the_title( $vehicle_id ),
				'value' => $vehicle_id,
			);
		}

		return $options;
	}

	/**
	 * Render catalog block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_catalog_block( $attributes ) {
		$limit   = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : -1;
		$columns = isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 3;
		$filters = ! empty( $attributes['filters'] ) ? 'yes' : 'no';
		$title   = isset( $attributes['title'] ) ? sanitize_text_field( $attributes['title'] ) : __( 'Veicoli a noleggio', 'gest-web-rent' );

		return do_shortcode( '[gwr_catalog limit="' . $limit . '" columns="' . $columns . '" filters="' . esc_attr( $filters ) . '" title="' . esc_attr( $title ) . '"]' );
	}

	/**
	 * Render availability block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_availability_block( $attributes ) {
		$columns = isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 3;
		$title   = isset( $attributes['title'] ) ? sanitize_text_field( $attributes['title'] ) : __( 'Verifica disponibilita', 'gest-web-rent' );

		return do_shortcode( '[gwr_availability_calendar columns="' . $columns . '" title="' . esc_attr( $title ) . '"]' );
	}

	/**
	 * Render vehicle block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_vehicle_block( $attributes ) {
		$vehicle_id = isset( $attributes['vehicleId'] ) ? absint( $attributes['vehicleId'] ) : 0;

		if ( ! $vehicle_id && GWR_CPT::POST_TYPE === get_post_type( get_the_ID() ) ) {
			$vehicle_id = get_the_ID();
		}

		if ( ! $vehicle_id ) {
			$query = new WP_Query(
				array(
					'post_type'      => GWR_CPT::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			$vehicle_id = ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
		}

		return $vehicle_id ? do_shortcode( '[gwr_vehicle id="' . absint( $vehicle_id ) . '"]' ) : '<div class="gwr-empty-state">' . esc_html__( 'Aggiungi almeno un veicolo per vedere l anteprima.', 'gest-web-rent' ) . '</div>';
	}
}
