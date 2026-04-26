<?php
/**
 * Frontend rendering, shortcodes and templates.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public catalog, availability and vehicle page renderer.
 */
class GWR_Frontend {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'gwr_catalog', array( __CLASS__, 'catalog_shortcode' ) );
		add_shortcode( 'gwr_availability_calendar', array( __CLASS__, 'availability_shortcode' ) );
		add_shortcode( 'gwr_vehicle', array( __CLASS__, 'vehicle_shortcode' ) );
		add_shortcode( 'gest_web_rent_catalog', array( __CLASS__, 'catalog_shortcode' ) );
		add_shortcode( 'gest_web_rent_availability', array( __CLASS__, 'availability_shortcode' ) );
		add_shortcode( 'gest_web_rent_vehicle', array( __CLASS__, 'vehicle_shortcode' ) );
		add_filter( 'template_include', array( __CLASS__, 'single_template' ) );
	}

	/**
	 * Register frontend assets.
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style( 'gwr-public', GWR_PLUGIN_URL . 'public/assets/css/gwr-public.css', array(), gwr_asset_version( 'public/assets/css/gwr-public.css' ) );
		wp_register_script( 'gwr-public', GWR_PLUGIN_URL . 'public/assets/js/gwr-public.js', array(), gwr_asset_version( 'public/assets/js/gwr-public.js' ), true );
	}

	/**
	 * Use plugin template for vehicle detail pages.
	 *
	 * @param string $template Current template.
	 * @return string
	 */
	public static function single_template( $template ) {
		if ( is_singular( GWR_CPT::POST_TYPE ) ) {
			$plugin_template = GWR_PLUGIN_DIR . 'public/templates/single-gwr_vehicle.php';

			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		return $template;
	}

	/**
	 * Render catalog shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function catalog_shortcode( $atts ) {
		self::enqueue_public_assets();

		$atts = shortcode_atts(
			array(
				'limit'          => -1,
				'columns'        => 3,
				'filters'        => 'yes',
				'title'          => __( 'Veicoli a noleggio', 'gest-web-rent' ),
				'subtitle'       => __( 'Filtra per date e scegli il mezzo giusto per il tuo noleggio.', 'gest-web-rent' ),
				'show_available' => 'yes',
				'hide_busy'      => 'yes',
			),
			$atts,
			'gest_web_rent_catalog'
		);

		$filters = self::current_filters();
		$ids     = self::filtered_vehicle_ids( $filters, 'yes' === $atts['hide_busy'] );
		$limit   = (int) $atts['limit'];

		if ( $limit > 0 ) {
			$ids = array_slice( $ids, 0, $limit );
		}

		ob_start();
		echo '<section class="gwr-catalog-shell gwr-block" style="' . esc_attr( self::style_vars() ) . '">';
		echo '<div class="gwr-section-head"><div><span class="gwr-kicker">Gest Web Rent</span><h2>' . esc_html( $atts['title'] ) . '</h2><p>' . esc_html( $atts['subtitle'] ) . '</p></div></div>';

		if ( 'yes' === $atts['filters'] ) {
			echo self::filter_form( $filters );
		}

		echo '<div class="gwr-results-head"><strong>' . esc_html( sprintf( _n( '%d veicolo trovato', '%d veicoli trovati', count( $ids ), 'gest-web-rent' ), count( $ids ) ) ) . '</strong></div>';

		if ( empty( $ids ) ) {
			echo '<div class="gwr-empty-state"><h3>' . esc_html__( 'Nessun veicolo disponibile per i filtri selezionati.', 'gest-web-rent' ) . '</h3><p>' . esc_html__( 'Prova ad allargare il periodo o a rimuovere qualche filtro.', 'gest-web-rent' ) . '</p></div>';
		} else {
			echo '<div class="gwr-grid columns-' . esc_attr( max( 1, min( 4, absint( $atts['columns'] ) ) ) ) . '">';
			foreach ( $ids as $vehicle_id ) {
				echo self::card_markup( $vehicle_id, $filters, 'yes' === $atts['show_available'] );
			}
			echo '</div>';
		}

		echo '</section>';

		return ob_get_clean();
	}

	/**
	 * Render availability search shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function availability_shortcode( $atts ) {
		self::enqueue_public_assets();

		$atts = shortcode_atts(
			array(
				'title'    => __( 'Verifica disponibilita', 'gest-web-rent' ),
				'subtitle' => __( 'Seleziona le date di noleggio e visualizza i veicoli liberi.', 'gest-web-rent' ),
				'columns'  => 3,
				'vehicle_id' => 0,
			),
			$atts,
			'gest_web_rent_availability'
		);

		$filters = self::current_filters();
		$ids     = ! empty( $atts['vehicle_id'] ) ? array( absint( $atts['vehicle_id'] ) ) : self::filtered_vehicle_ids( $filters, true );

		ob_start();
		echo '<section class="gwr-availability-shell gwr-block" style="' . esc_attr( self::style_vars() ) . '">';
		echo '<div class="gwr-availability-hero"><div><span class="gwr-kicker">' . esc_html__( 'Calendario', 'gest-web-rent' ) . '</span><h2>' . esc_html( $atts['title'] ) . '</h2><p>' . esc_html( $atts['subtitle'] ) . '</p></div></div>';
		echo self::date_filter_form( $filters );

		if ( empty( $filters['date_from'] ) && empty( $filters['date_to'] ) ) {
			echo '<div class="gwr-empty-state"><h3>' . esc_html__( 'Scegli un periodo per controllare la disponibilita.', 'gest-web-rent' ) . '</h3><p>' . esc_html__( 'Il sistema confronta le date richieste con gli impegni inseriti su ogni scheda veicolo.', 'gest-web-rent' ) . '</p></div>';
		} elseif ( empty( $ids ) || ( ! empty( $atts['vehicle_id'] ) && ! GWR_CPT::is_available( absint( $atts['vehicle_id'] ), $filters['date_from'], $filters['date_to'] ) ) ) {
			echo '<div class="gwr-empty-state"><h3>' . esc_html__( 'Nessun veicolo libero nel periodo selezionato.', 'gest-web-rent' ) . '</h3><p>' . esc_html__( 'Contatta il concessionario per alternative o rientri anticipati.', 'gest-web-rent' ) . '</p></div>';
		} else {
			echo '<div class="gwr-grid columns-' . esc_attr( max( 1, min( 4, absint( $atts['columns'] ) ) ) ) . '">';
			foreach ( $ids as $vehicle_id ) {
				echo self::card_markup( $vehicle_id, $filters, true );
			}
			echo '</div>';
		}

		echo '</section>';

		return ob_get_clean();
	}

	/**
	 * Render single vehicle shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function vehicle_shortcode( $atts ) {
		self::enqueue_public_assets();

		$atts = shortcode_atts(
			array(
				'id' => get_the_ID(),
			),
			$atts,
			'gest_web_rent_vehicle'
		);

		$vehicle_id = absint( $atts['id'] );

		if ( ! $vehicle_id || GWR_CPT::POST_TYPE !== get_post_type( $vehicle_id ) ) {
			return '<div class="gwr-empty-state">' . esc_html__( 'Veicolo non disponibile.', 'gest-web-rent' ) . '</div>';
		}

		return self::vehicle_detail_markup( $vehicle_id );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public static function enqueue_public_assets() {
		wp_enqueue_style( 'gwr-public' );
		wp_enqueue_script( 'gwr-public' );
	}

	/**
	 * Current filter values from query string.
	 *
	 * @return array
	 */
	public static function current_filters() {
		return array(
			'date_from' => isset( $_GET['gwr_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['gwr_date_from'] ) ) : '',
			'date_to'   => isset( $_GET['gwr_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['gwr_date_to'] ) ) : '',
			'brand'     => isset( $_GET['gwr_brand'] ) ? sanitize_text_field( wp_unslash( $_GET['gwr_brand'] ) ) : '',
			'category'  => isset( $_GET['gwr_category'] ) ? sanitize_text_field( wp_unslash( $_GET['gwr_category'] ) ) : '',
			'fuel'      => isset( $_GET['gwr_fuel'] ) ? sanitize_text_field( wp_unslash( $_GET['gwr_fuel'] ) ) : '',
			'transmission' => isset( $_GET['gwr_transmission'] ) ? sanitize_text_field( wp_unslash( $_GET['gwr_transmission'] ) ) : '',
			'location'  => isset( $_GET['gwr_location'] ) ? sanitize_text_field( wp_unslash( $_GET['gwr_location'] ) ) : '',
			'seats'     => isset( $_GET['gwr_seats'] ) ? absint( $_GET['gwr_seats'] ) : 0,
			'price_max' => isset( $_GET['gwr_price_max'] ) ? absint( $_GET['gwr_price_max'] ) : 0,
		);
	}

	/**
	 * Get vehicle IDs filtered by availability and basic search fields.
	 *
	 * @param array $filters Filters.
	 * @return array
	 */
	public static function filtered_vehicle_ids( $filters, $hide_busy = true ) {
		$query = new WP_Query(
			array(
				'post_type'      => GWR_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'menu_order date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$ids = array();

		foreach ( $query->posts as $vehicle_id ) {
			if ( $hide_busy && ( ! empty( $filters['date_from'] ) || ! empty( $filters['date_to'] ) ) ) {
				if ( ! GWR_CPT::is_available( $vehicle_id, $filters['date_from'], $filters['date_to'] ) ) {
					continue;
				}
			}

			if ( ! empty( $filters['brand'] ) ) {
				$haystack = get_post_meta( $vehicle_id, '_gwr_brand', true ) . ' ' . get_post_meta( $vehicle_id, '_gwr_model', true ) . ' ' . get_the_title( $vehicle_id );
				if ( false === stripos( $haystack, $filters['brand'] ) ) {
					continue;
				}
			}

			if ( ! empty( $filters['seats'] ) && (int) get_post_meta( $vehicle_id, '_gwr_seats', true ) < (int) $filters['seats'] ) {
				continue;
			}

			foreach ( array( 'category', 'fuel', 'transmission', 'location' ) as $field ) {
				if ( ! empty( $filters[ $field ] ) && false === stripos( (string) get_post_meta( $vehicle_id, '_gwr_' . $field, true ), $filters[ $field ] ) ) {
					continue 2;
				}
			}

			if ( ! empty( $filters['price_max'] ) ) {
				$price = (float) get_post_meta( $vehicle_id, '_gwr_price_day', true );
				if ( $price > (float) $filters['price_max'] ) {
					continue;
				}
			}

			$ids[] = $vehicle_id;
		}

		return $ids;
	}

	/**
	 * Render full filter form.
	 *
	 * @param array $filters Current filters.
	 * @return string
	 */
	private static function filter_form( $filters ) {
		$html  = '<form class="gwr-filter-panel" method="get" data-gwr-filter-form>';
		$html .= '<div class="gwr-filter-grid">';
		$html .= self::date_field( 'gwr_date_from', __( 'Ritiro', 'gest-web-rent' ), $filters['date_from'] );
		$html .= self::date_field( 'gwr_date_to', __( 'Riconsegna', 'gest-web-rent' ), $filters['date_to'] );
		$html .= '<label><span>' . esc_html__( 'Marca o modello', 'gest-web-rent' ) . '</span><input type="search" name="gwr_brand" value="' . esc_attr( $filters['brand'] ) . '" placeholder="' . esc_attr__( 'Fiat, BMW, Ducato...', 'gest-web-rent' ) . '" /></label>';
		$html .= '<label><span>' . esc_html__( 'Categoria', 'gest-web-rent' ) . '</span><input type="search" name="gwr_category" value="' . esc_attr( $filters['category'] ) . '" placeholder="' . esc_attr__( 'Auto, furgone...', 'gest-web-rent' ) . '" /></label>';
		$html .= '<label><span>' . esc_html__( 'Alimentazione', 'gest-web-rent' ) . '</span><input type="search" name="gwr_fuel" value="' . esc_attr( $filters['fuel'] ) . '" placeholder="' . esc_attr__( 'Diesel, elettrico...', 'gest-web-rent' ) . '" /></label>';
		$html .= '<label><span>' . esc_html__( 'Cambio', 'gest-web-rent' ) . '</span><input type="search" name="gwr_transmission" value="' . esc_attr( $filters['transmission'] ) . '" placeholder="' . esc_attr__( 'Manuale, automatico...', 'gest-web-rent' ) . '" /></label>';
		$html .= '<label><span>' . esc_html__( 'Posti minimi', 'gest-web-rent' ) . '</span><input type="number" min="0" name="gwr_seats" value="' . esc_attr( $filters['seats'] ) . '" placeholder="2" /></label>';
		$html .= '<label><span>' . esc_html__( 'Prezzo max/giorno', 'gest-web-rent' ) . '</span><input type="number" min="0" name="gwr_price_max" value="' . esc_attr( $filters['price_max'] ) . '" placeholder="100" /></label>';
		$html .= '<label><span>' . esc_html__( 'Sede', 'gest-web-rent' ) . '</span><input type="search" name="gwr_location" value="' . esc_attr( $filters['location'] ) . '" placeholder="' . esc_attr__( 'Roma, Milano...', 'gest-web-rent' ) . '" /></label>';
		$html .= '</div>';
		$html .= '<div class="gwr-filter-actions"><button class="gwr-button" type="submit">' . esc_html__( 'Filtra veicoli', 'gest-web-rent' ) . '</button><a class="gwr-button-secondary" href="' . esc_url( remove_query_arg( array( 'gwr_date_from', 'gwr_date_to', 'gwr_brand', 'gwr_category', 'gwr_fuel', 'gwr_transmission', 'gwr_seats', 'gwr_price_max', 'gwr_location' ) ) ) . '">' . esc_html__( 'Reset', 'gest-web-rent' ) . '</a></div>';
		$html .= '</form>';

		return $html;
	}

	/**
	 * Render date-only filter form.
	 *
	 * @param array $filters Current filters.
	 * @return string
	 */
	private static function date_filter_form( $filters ) {
		$html  = '<form class="gwr-filter-panel gwr-filter-panel--dates" method="get" data-gwr-filter-form>';
		$html .= '<div class="gwr-filter-grid gwr-filter-grid--dates">';
		$html .= self::date_field( 'gwr_date_from', __( 'Data ritiro', 'gest-web-rent' ), $filters['date_from'] );
		$html .= self::date_field( 'gwr_date_to', __( 'Data riconsegna', 'gest-web-rent' ), $filters['date_to'] );
		$html .= '</div>';
		$html .= '<div class="gwr-filter-actions"><button class="gwr-button" type="submit">' . esc_html__( 'Cerca disponibilita', 'gest-web-rent' ) . '</button></div>';
		$html .= '</form>';

		return $html;
	}

	/**
	 * Date input helper.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @param string $value Current value.
	 * @return string
	 */
	private static function date_field( $name, $label, $value ) {
		return '<label><span>' . esc_html( $label ) . '</span><input type="date" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" /></label>';
	}

	/**
	 * Render vehicle card.
	 *
	 * @param int   $vehicle_id Vehicle ID.
	 * @param array $filters Filters.
	 * @param bool  $show_available Show availability badge.
	 * @return string
	 */
	public static function card_markup( $vehicle_id, $filters = array(), $show_available = true ) {
		$brand     = get_post_meta( $vehicle_id, '_gwr_brand', true );
		$model     = get_post_meta( $vehicle_id, '_gwr_model', true );
		$price_day = get_post_meta( $vehicle_id, '_gwr_price_day', true );
		$badge     = get_post_meta( $vehicle_id, '_gwr_badge', true );
		$available = GWR_CPT::is_available( $vehicle_id, $filters['date_from'] ?? '', $filters['date_to'] ?? '' );

		ob_start();
		echo '<article class="gwr-card">';
		echo '<a class="gwr-card__media" href="' . esc_url( get_permalink( $vehicle_id ) ) . '">';
		if ( has_post_thumbnail( $vehicle_id ) ) {
			echo get_the_post_thumbnail( $vehicle_id, 'large' );
		} else {
			echo '<span>' . esc_html__( 'Foto veicolo', 'gest-web-rent' ) . '</span>';
		}
		echo '</a>';
		echo '<div class="gwr-card__body">';
		echo '<div class="gwr-card-topline">';
		echo '<span class="gwr-card__badge">' . esc_html( $badge ?: __( 'Noleggio', 'gest-web-rent' ) ) . '</span>';
		if ( $show_available ) {
			echo '<span class="gwr-card__status ' . ( $available ? 'is-available' : 'is-busy' ) . '">' . esc_html( $available ? __( 'Disponibile', 'gest-web-rent' ) : __( 'Impegnato', 'gest-web-rent' ) ) . '</span>';
		}
		echo '</div>';
		echo '<h3><a href="' . esc_url( get_permalink( $vehicle_id ) ) . '">' . esc_html( get_the_title( $vehicle_id ) ) . '</a></h3>';
		echo '<p class="gwr-card__model">' . esc_html( trim( $brand . ' ' . $model ) ) . '</p>';
		echo self::quick_specs_markup( $vehicle_id );
		if ( $price_day ) {
			echo '<p class="gwr-card__price"><strong>' . esc_html( self::format_price( $price_day ) ) . '</strong><span>' . esc_html__( '/ giorno', 'gest-web-rent' ) . '</span></p>';
		}
		echo '<div class="gwr-card-actions">';
		echo '<a class="gwr-button" href="' . esc_url( get_permalink( $vehicle_id ) ) . '">' . esc_html__( 'Scheda veicolo', 'gest-web-rent' ) . '</a>';
		echo self::whatsapp_link( $vehicle_id, __( 'WhatsApp', 'gest-web-rent' ), 'gwr-button-secondary' );
		echo self::email_link( $vehicle_id, __( 'Email', 'gest-web-rent' ), 'gwr-button-secondary' );
		echo '</div>';
		echo '</div></article>';

		return ob_get_clean();
	}

	/**
	 * Render vehicle detail.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return string
	 */
	public static function vehicle_detail_markup( $vehicle_id ) {
		self::enqueue_public_assets();

		ob_start();
		echo '<article class="gwr-single-wrap gwr-block" style="' . esc_attr( self::style_vars() ) . '">';
		echo '<div class="gwr-single-hero">';
		echo '<div class="gwr-single-gallery">' . self::gallery_markup( $vehicle_id ) . '</div>';
		echo '<aside class="gwr-contact-card">';
		echo '<span class="gwr-kicker">' . esc_html__( 'Scheda noleggio', 'gest-web-rent' ) . '</span>';
		echo '<h1>' . esc_html( get_the_title( $vehicle_id ) ) . '</h1>';
		echo '<p class="gwr-single-subtitle">' . esc_html( trim( get_post_meta( $vehicle_id, '_gwr_brand', true ) . ' ' . get_post_meta( $vehicle_id, '_gwr_model', true ) . ' ' . get_post_meta( $vehicle_id, '_gwr_version', true ) ) ) . '</p>';
		echo self::price_panel_markup( $vehicle_id );
		echo self::contact_box_markup( $vehicle_id );
		echo '</aside>';
		echo '</div>';
		echo '<div class="gwr-single-layout">';
		echo '<main class="gwr-single-main">';
		echo '<section class="gwr-content-card"><h2>' . esc_html__( 'Descrizione', 'gest-web-rent' ) . '</h2>' . wp_kses_post( wpautop( get_post_field( 'post_content', $vehicle_id ) ) ) . '</section>';
		echo self::rental_terms_markup( $vehicle_id );
		echo self::equipment_section_markup( $vehicle_id );
		echo self::list_section_markup( $vehicle_id, '_gwr_included_services', __( 'Servizi inclusi', 'gest-web-rent' ) );
		echo self::list_section_markup( $vehicle_id, '_gwr_accessories', __( 'Dotazioni e accessori', 'gest-web-rent' ) );
		echo '</main>';
		echo '<aside class="gwr-single-side">';
		echo '<section class="gwr-content-card"><h2>' . esc_html__( 'Dati tecnici', 'gest-web-rent' ) . '</h2>' . self::specs_grid_markup( $vehicle_id ) . '</section>';
		echo self::availability_panel_markup( $vehicle_id );
		echo '</aside>';
		echo '</div>';
		echo '</article>';

		return ob_get_clean();
	}

	/**
	 * Gallery markup.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return string
	 */
	private static function gallery_markup( $vehicle_id ) {
		$images = array();

		if ( has_post_thumbnail( $vehicle_id ) ) {
			$images[] = get_the_post_thumbnail_url( $vehicle_id, 'large' );
		}

		$urls = preg_split( '/\r\n|\r|\n/', (string) get_post_meta( $vehicle_id, '_gwr_gallery_urls', true ) );

		foreach ( $urls as $url ) {
			$url = esc_url_raw( trim( $url ) );
			if ( $url ) {
				$images[] = $url;
			}
		}

		if ( empty( $images ) ) {
			return '<div class="gwr-gallery-empty">' . esc_html__( 'Foto veicolo', 'gest-web-rent' ) . '</div>';
		}

		$html = '<div class="gwr-gallery-main"><img src="' . esc_url( $images[0] ) . '" alt="' . esc_attr( get_the_title( $vehicle_id ) ) . '" data-gwr-gallery-main /></div>';

		if ( count( $images ) > 1 ) {
			$html .= '<div class="gwr-gallery-thumbs">';
			foreach ( $images as $image ) {
				$html .= '<button type="button" data-gwr-gallery-thumb="' . esc_url( $image ) . '"><img src="' . esc_url( $image ) . '" alt="" /></button>';
			}
			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Quick card specs.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return string
	 */
	private static function quick_specs_markup( $vehicle_id ) {
		$items = array_filter(
			array(
				get_post_meta( $vehicle_id, '_gwr_fuel', true ),
				get_post_meta( $vehicle_id, '_gwr_transmission', true ),
				get_post_meta( $vehicle_id, '_gwr_seats', true ) ? sprintf( __( '%d posti', 'gest-web-rent' ), (int) get_post_meta( $vehicle_id, '_gwr_seats', true ) ) : '',
				get_post_meta( $vehicle_id, '_gwr_max_km_day', true ) ? sprintf( __( '%s km/giorno', 'gest-web-rent' ), get_post_meta( $vehicle_id, '_gwr_max_km_day', true ) ) : '',
			)
		);

		if ( empty( $items ) ) {
			return '';
		}

		$html = '<ul class="gwr-spec-pill-list">';
		foreach ( $items as $item ) {
			$html .= '<li>' . esc_html( $item ) . '</li>';
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Specs grid.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return string
	 */
	public static function specs_grid_markup( $vehicle_id ) {
		$items = array(
			__( 'Anno', 'gest-web-rent' )          => get_post_meta( $vehicle_id, '_gwr_year', true ),
			__( 'Km', 'gest-web-rent' )            => get_post_meta( $vehicle_id, '_gwr_mileage', true ),
			__( 'Alimentazione', 'gest-web-rent' ) => get_post_meta( $vehicle_id, '_gwr_fuel', true ),
			__( 'Cambio', 'gest-web-rent' )        => get_post_meta( $vehicle_id, '_gwr_transmission', true ),
			__( 'Posti', 'gest-web-rent' )         => get_post_meta( $vehicle_id, '_gwr_seats', true ),
			__( 'Porte', 'gest-web-rent' )         => get_post_meta( $vehicle_id, '_gwr_doors', true ),
			__( 'Sede', 'gest-web-rent' )          => get_post_meta( $vehicle_id, '_gwr_location', true ),
			__( 'Patente', 'gest-web-rent' )       => get_post_meta( $vehicle_id, '_gwr_license_required', true ),
		);

		$html = '<div class="gwr-spec-grid">';
		foreach ( $items as $label => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			$html .= '<div class="gwr-spec"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Price panel.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return string
	 */
	private static function price_panel_markup( $vehicle_id ) {
		$prices = array(
			__( 'Giorno', 'gest-web-rent' )    => get_post_meta( $vehicle_id, '_gwr_price_day', true ),
			__( 'Weekend', 'gest-web-rent' )   => get_post_meta( $vehicle_id, '_gwr_price_weekend', true ),
			__( 'Settimana', 'gest-web-rent' ) => get_post_meta( $vehicle_id, '_gwr_price_week', true ),
			__( 'Mese', 'gest-web-rent' )      => get_post_meta( $vehicle_id, '_gwr_price_month', true ),
			__( 'Cauzione', 'gest-web-rent' )  => get_post_meta( $vehicle_id, '_gwr_deposit', true ),
			__( 'Franchigia', 'gest-web-rent' ) => get_post_meta( $vehicle_id, '_gwr_deductible', true ),
		);

		$html = '<div class="gwr-price-panel">';
		foreach ( $prices as $label => $price ) {
			if ( '' === (string) $price ) {
				continue;
			}
			$html .= '<div><span>' . esc_html( $label ) . '</span><strong>' . esc_html( self::format_price( $price ) ) . '</strong></div>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Rental terms section.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return string
	 */
	private static function rental_terms_markup( $vehicle_id ) {
		$items = array(
			__( 'Km inclusi/giorno', 'gest-web-rent' ) => get_post_meta( $vehicle_id, '_gwr_max_km_day', true ),
			__( 'Km inclusi/settimana', 'gest-web-rent' ) => get_post_meta( $vehicle_id, '_gwr_max_km_week', true ),
			__( 'Km inclusi/mese', 'gest-web-rent' )   => get_post_meta( $vehicle_id, '_gwr_max_km_month', true ),
			__( 'Costo km extra', 'gest-web-rent' )    => get_post_meta( $vehicle_id, '_gwr_extra_km_price', true ),
			__( 'Eta minima', 'gest-web-rent' )        => get_post_meta( $vehicle_id, '_gwr_min_age', true ),
			__( 'Anni minimi patente', 'gest-web-rent' ) => get_post_meta( $vehicle_id, '_gwr_min_license_years', true ),
			__( 'Assicurazione', 'gest-web-rent' )     => get_post_meta( $vehicle_id, '_gwr_insurance', true ),
			__( 'Assicurazione inclusa', 'gest-web-rent' ) => '1' === get_post_meta( $vehicle_id, '_gwr_insurance_included', true ) ? __( 'Si', 'gest-web-rent' ) : '',
			__( 'Secondo conducente', 'gest-web-rent' ) => '1' === get_post_meta( $vehicle_id, '_gwr_second_driver', true ) ? __( 'Si', 'gest-web-rent' ) : '',
			__( 'Consegna a domicilio', 'gest-web-rent' ) => '1' === get_post_meta( $vehicle_id, '_gwr_home_delivery', true ) ? __( 'Si', 'gest-web-rent' ) : '',
			__( 'Durata minima', 'gest-web-rent' )     => get_post_meta( $vehicle_id, '_gwr_min_rental_days', true ),
			__( 'Durata massima', 'gest-web-rent' )    => get_post_meta( $vehicle_id, '_gwr_max_rental_days', true ),
			__( 'Note noleggio', 'gest-web-rent' )     => get_post_meta( $vehicle_id, '_gwr_rental_notes', true ),
			__( 'Ritiro/consegna', 'gest-web-rent' )   => get_post_meta( $vehicle_id, '_gwr_pickup_notes', true ),
		);

		$html = '<section class="gwr-content-card"><h2>' . esc_html__( 'Condizioni noleggio', 'gest-web-rent' ) . '</h2><div class="gwr-terms-list">';
		foreach ( $items as $label => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			$html .= '<div><span>' . esc_html( $label ) . '</span><strong>' . nl2br( esc_html( $value ) ) . '</strong></div>';
		}
		$html .= '</div></section>';

		return $html;
	}

	/**
	 * Render list section from newline values.
	 *
	 * @param int    $vehicle_id Vehicle ID.
	 * @param string $meta_key Meta key.
	 * @param string $title Section title.
	 * @return string
	 */
	private static function list_section_markup( $vehicle_id, $meta_key, $title ) {
		$items = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) get_post_meta( $vehicle_id, $meta_key, true ) ) ) );

		if ( empty( $items ) ) {
			return '';
		}

		$html = '<section class="gwr-content-card"><h2>' . esc_html( $title ) . '</h2><ul class="gwr-icon-list">';
		foreach ( $items as $item ) {
			$html .= '<li>' . esc_html( $item ) . '</li>';
		}
		$html .= '</ul></section>';

		return $html;
	}

	/**
	 * Render checkbox equipment section.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return string
	 */
	private static function equipment_section_markup( $vehicle_id ) {
		$labels = array(
			'eq_air_conditioning' => __( 'Climatizzatore', 'gest-web-rent' ),
			'eq_navigation'       => __( 'Navigatore', 'gest-web-rent' ),
			'eq_bluetooth'        => __( 'Bluetooth', 'gest-web-rent' ),
			'eq_apple_carplay'    => __( 'Apple CarPlay', 'gest-web-rent' ),
			'eq_android_auto'     => __( 'Android Auto', 'gest-web-rent' ),
			'eq_parking_sensors'  => __( 'Sensori parcheggio', 'gest-web-rent' ),
			'eq_camera'           => __( 'Telecamera', 'gest-web-rent' ),
			'eq_cruise_control'   => __( 'Cruise control', 'gest-web-rent' ),
			'eq_abs'              => __( 'ABS', 'gest-web-rent' ),
			'eq_esp'              => __( 'ESP', 'gest-web-rent' ),
			'eq_airbag'           => __( 'Airbag', 'gest-web-rent' ),
			'eq_winter_tires'     => __( 'Pneumatici invernali', 'gest-web-rent' ),
			'eq_child_seat'       => __( 'Seggiolino', 'gest-web-rent' ),
			'eq_snow_chains'      => __( 'Catene neve', 'gest-web-rent' ),
		);
		$items  = array();

		foreach ( $labels as $key => $label ) {
			if ( '1' === get_post_meta( $vehicle_id, '_gwr_' . $key, true ) ) {
				$items[] = $label;
			}
		}

		if ( empty( $items ) ) {
			return '';
		}

		$html = '<section class="gwr-content-card"><h2>' . esc_html__( 'Dotazioni', 'gest-web-rent' ) . '</h2><ul class="gwr-icon-list">';
		foreach ( $items as $item ) {
			$html .= '<li>' . esc_html( $item ) . '</li>';
		}
		$html .= '</ul></section>';

		return $html;
	}

	/**
	 * Availability panel for a vehicle.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return string
	 */
	private static function availability_panel_markup( $vehicle_id ) {
		$rows = GWR_CPT::upcoming_availability( $vehicle_id, 8 );
		$html = '<section class="gwr-content-card gwr-availability-card"><h2>' . esc_html__( 'Calendario impegni', 'gest-web-rent' ) . '</h2>';

		if ( empty( $rows ) ) {
			$html .= '<p>' . esc_html__( 'Nessun blocco futuro registrato. Richiedi conferma per il periodo desiderato.', 'gest-web-rent' ) . '</p>';
		} else {
			$html .= '<div class="gwr-availability-list">';
			foreach ( $rows as $row ) {
				$html .= '<div><strong>' . esc_html( $row['date_from'] . ' -> ' . $row['date_to'] ) . '</strong><span>' . esc_html( GWR_CPT::availability_statuses()[ $row['status'] ] ?? $row['status'] ) . '</span></div>';
			}
			$html .= '</div>';
		}

		$html .= '</section>';

		return $html;
	}

	/**
	 * Contact box.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return string
	 */
	private static function contact_box_markup( $vehicle_id ) {
		$settings = GWR_Admin::get_settings();
		$filters  = self::current_filters();
		$html     = '<div class="gwr-contact-box"><h2>' . esc_html__( 'Contatta il concessionario', 'gest-web-rent' ) . '</h2><p>' . esc_html( $settings['privacy_note'] ) . '</p>';
		$html    .= '<form class="gwr-contact-dates" method="get"><label><span>' . esc_html__( 'Data inizio', 'gest-web-rent' ) . '</span><input type="date" name="gwr_date_from" value="' . esc_attr( $filters['date_from'] ) . '" /></label><label><span>' . esc_html__( 'Data fine', 'gest-web-rent' ) . '</span><input type="date" name="gwr_date_to" value="' . esc_attr( $filters['date_to'] ) . '" /></label><button class="gwr-button-secondary" type="submit">' . esc_html__( 'Aggiorna messaggio', 'gest-web-rent' ) . '</button></form>';
		$html    .= '<div class="gwr-contact-actions">';
		$html    .= self::whatsapp_link( $vehicle_id, __( 'Scrivi su WhatsApp', 'gest-web-rent' ), 'gwr-button' );
		$html    .= self::email_link( $vehicle_id, __( 'Invia email', 'gest-web-rent' ), 'gwr-button-secondary' );

		$html .= '</div></div>';

		return $html;
	}

	/**
	 * WhatsApp link helper.
	 *
	 * @param int    $vehicle_id Vehicle ID.
	 * @param string $label Link label.
	 * @param string $class CSS class.
	 * @return string
	 */
	private static function whatsapp_link( $vehicle_id, $label, $class ) {
		$settings = GWR_Admin::get_settings();

		if ( empty( $settings['whatsapp_number'] ) ) {
			return '';
		}

		$phone   = preg_replace( '/\D+/', '', $settings['whatsapp_number'] );
		$message = rawurlencode( self::apply_placeholders( $settings['whatsapp_message'], $vehicle_id ) );

		return '<a class="' . esc_attr( $class ) . '" target="_blank" rel="noopener noreferrer" href="' . esc_url( 'https://wa.me/' . $phone . '?text=' . $message ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Email link helper.
	 *
	 * @param int    $vehicle_id Vehicle ID.
	 * @param string $label Link label.
	 * @param string $class CSS class.
	 * @return string
	 */
	private static function email_link( $vehicle_id, $label, $class ) {
		$settings = GWR_Admin::get_settings();

		if ( empty( $settings['contact_email'] ) ) {
			return '';
		}

		$subject = rawurlencode( self::apply_placeholders( $settings['email_subject'], $vehicle_id ) );
		$body    = rawurlencode( self::apply_placeholders( $settings['email_body'], $vehicle_id ) );

		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( 'mailto:' . sanitize_email( $settings['contact_email'] ) . '?subject=' . $subject . '&body=' . $body ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Replace message placeholders.
	 *
	 * @param string $template Template.
	 * @param int    $vehicle_id Vehicle ID.
	 * @return string
	 */
	private static function apply_placeholders( $template, $vehicle_id ) {
		$filters = self::current_filters();
		$map     = array(
			'{vehicle_title}' => get_the_title( $vehicle_id ),
			'{brand}'         => get_post_meta( $vehicle_id, '_gwr_brand', true ),
			'{model}'         => get_post_meta( $vehicle_id, '_gwr_model', true ),
			'{version}'       => get_post_meta( $vehicle_id, '_gwr_version', true ),
			'{daily_price}'   => self::format_price( get_post_meta( $vehicle_id, '_gwr_price_day', true ) ),
			'{weekly_price}'  => self::format_price( get_post_meta( $vehicle_id, '_gwr_price_week', true ) ),
			'{monthly_price}' => self::format_price( get_post_meta( $vehicle_id, '_gwr_price_month', true ) ),
			'{start_date}'    => $filters['date_from'] ?: '-',
			'{end_date}'      => $filters['date_to'] ?: '-',
			'{vehicle_url}'   => get_permalink( $vehicle_id ),
			'{site_name}'     => get_bloginfo( 'name' ),
		);

		return strtr( (string) $template, $map );
	}

	/**
	 * Format price.
	 *
	 * @param mixed $price Price.
	 * @return string
	 */
	public static function format_price( $price ) {
		if ( '' === (string) $price ) {
			return '';
		}

		return 'EUR ' . number_format_i18n( (float) $price, ( floor( (float) $price ) === (float) $price ) ? 0 : 2 );
	}

	/**
	 * CSS variables from settings.
	 *
	 * @return string
	 */
	private static function style_vars() {
		$settings = GWR_Admin::get_settings();

		return '--gwr-primary:' . esc_attr( $settings['primary_color'] ) . ';--gwr-accent:' . esc_attr( $settings['accent_color'] ) . ';';
	}
}
