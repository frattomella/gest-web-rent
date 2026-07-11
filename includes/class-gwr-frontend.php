<?php
/**
 * Frontend catalog component.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public catalog, AJAX filtering and modal data.
 */
class GWR_Frontend {
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'gwr_catalog', array( __CLASS__, 'catalog_shortcode' ) );
		add_shortcode( 'gest_web_rent_catalog', array( __CLASS__, 'catalog_shortcode' ) );
		add_action( 'wp_ajax_gwr_filter_catalog', array( __CLASS__, 'ajax_filter_catalog' ) );
		add_action( 'wp_ajax_nopriv_gwr_filter_catalog', array( __CLASS__, 'ajax_filter_catalog' ) );
	}

	public static function register_assets() {
		wp_register_style( 'gwr-public', GWR_PLUGIN_URL . 'public/assets/css/gwr-public.css', array(), gwr_asset_version( 'public/assets/css/gwr-public.css' ) );
		wp_register_script( 'gwr-public', GWR_PLUGIN_URL . 'public/assets/js/gwr-public.js', array(), gwr_asset_version( 'public/assets/js/gwr-public.js' ), true );
	}

	public static function catalog_shortcode( $atts ) {
		self::enqueue_public_assets();
		$atts = shortcode_atts( array( 'title' => __( 'Catalogo noleggio', 'gest-web-rent' ), 'subtitle' => __( 'Scegli le date, filtra il parco veicoli e apri i dettagli senza cambiare pagina.', 'gest-web-rent' ), 'max_width' => '1380' ), $atts, 'gwr_catalog' );
		$filters = self::filters_from_request( $_GET );
		$error = '';
		$vehicles = self::filtered_vehicles( $filters, $error );
		ob_start();
		echo '<section class="gwr-rent-catalog gwr-block" data-gwr-catalog style="' . esc_attr( self::style_vars( $atts['max_width'] ) ) . '">';
		echo self::filter_form( $filters, $error );
		echo '<div class="gwr-results-head"><strong data-gwr-count aria-live="polite">' . esc_html( sprintf( _n( '%d veicolo disponibile', '%d veicoli disponibili', count( $vehicles ), 'gest-web-rent' ), count( $vehicles ) ) ) . '</strong><span data-gwr-query-summary>' . esc_html( self::date_summary( $filters ) ) . '</span></div>';
		echo '<div class="gwr-catalog-error" data-gwr-error role="alert" aria-live="assertive" ' . ( $error ? '' : 'hidden' ) . '>' . esc_html( $error ) . '</div>';
		echo '<div class="gwr-catalog-grid" data-gwr-results>' . self::cards_html( $vehicles, $filters ) . '</div>';
		echo self::modal_markup();
		echo '</section>';
		return ob_get_clean();
	}

	public static function ajax_filter_catalog() {
		check_ajax_referer( 'gwr_catalog_nonce', 'nonce' );
		$filters = self::filters_from_request( $_POST );
		$error = '';
		$vehicles = self::filtered_vehicles( $filters, $error );
		wp_send_json_success( array( 'html' => self::cards_html( $vehicles, $filters ), 'count' => count( $vehicles ), 'error' => $error, 'summary' => self::date_summary( $filters ) ) );
	}

	private static function enqueue_public_assets() {
		wp_enqueue_style( 'gwr-public' );
		wp_enqueue_script( 'gwr-public' );
		$settings = GWR_Admin::get_settings();
		$whatsapp_number = preg_replace( '/\D+/', '', (string) $settings['whatsapp_number'] );
		$contact_email = sanitize_email( (string) $settings['contact_email'] );
		if ( ! is_email( $contact_email ) ) {
			$contact_email = '';
		}

		wp_localize_script(
			'gwr-public',
			'gwrCatalog',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gwr_catalog_nonce' ),
				'i18n'    => array(
					'available'          => __( 'Disponibile', 'gest-web-rent' ),
					'noVehicles'         => __( 'Nessun veicolo disponibile con questi filtri.', 'gest-web-rent' ),
					'countOne'           => __( '1 veicolo disponibile', 'gest-web-rent' ),
					'countMany'          => __( '%d veicoli disponibili', 'gest-web-rent' ),
					'dateError'          => __( 'La data fine %2$s non puo precedere la data inizio %1$s.', 'gest-web-rent' ),
					'invalidDate'        => __( 'Inserisci una data valida nel formato GG-MM-AAAA.', 'gest-web-rent' ),
					'datesGeneric'       => __( 'Date da definire', 'gest-web-rent' ),
					'searchLabel'        => __( 'Cerca veicoli', 'gest-web-rent' ),
					'searchingLabel'     => __( 'Ricerca in corso...', 'gest-web-rent' ),
					'contactsMissing'    => __( 'Contatti non configurati.', 'gest-web-rent' ),
					'close'              => __( 'Chiudi', 'gest-web-rent' ),
					'modalError'         => __( 'Impossibile aprire i dettagli del veicolo.', 'gest-web-rent' ),
				),
				'contact' => array(
					'dealerName'       => sanitize_text_field( (string) $settings['dealer_name'] ),
					'whatsappNumber'   => $whatsapp_number,
					'contactEmail'     => $contact_email,
					'whatsappTemplate' => sanitize_textarea_field( (string) $settings['whatsapp_message'] ),
					'emailSubject'     => sanitize_text_field( (string) $settings['email_subject'] ),
					'emailBody'        => sanitize_textarea_field( (string) $settings['email_body'] ),
					'privacyNote'      => sanitize_textarea_field( (string) $settings['privacy_note'] ),
					'siteUrl'          => home_url( '/' ),
				),
			)
		);
	}

	private static function filters_from_request( $source ) {
		$source = is_array( $source ) ? wp_unslash( $source ) : array();
		$start_date = $source['start_date'] ?? ( $source['start_date_display'] ?? ( $source['gwr_date_from'] ?? '' ) );
		$end_date = $source['end_date'] ?? ( $source['end_date_display'] ?? ( $source['gwr_date_to'] ?? '' ) );

		return array(
			'start_date'   => self::normalize_request_date( $start_date ),
			'end_date'     => self::normalize_request_date( $end_date ),
			'search'       => isset( $source['search'] ) ? sanitize_text_field( $source['search'] ) : '',
			'category'     => isset( $source['category'] ) ? sanitize_text_field( $source['category'] ) : '',
			'brand'        => isset( $source['brand'] ) ? sanitize_text_field( $source['brand'] ) : '',
			'fuel'         => isset( $source['fuel'] ) ? sanitize_text_field( $source['fuel'] ) : '',
			'transmission' => isset( $source['transmission'] ) ? sanitize_text_field( $source['transmission'] ) : '',
			'location'     => isset( $source['location'] ) ? sanitize_text_field( $source['location'] ) : '',
			'max_price'    => isset( $source['max_price'] ) ? (float) str_replace( ',', '.', sanitize_text_field( $source['max_price'] ) ) : 0,
			'seats'        => isset( $source['seats'] ) ? absint( $source['seats'] ) : 0,
		);
	}

	private static function filtered_vehicles( $filters, &$error = '' ) {
		$query_filters = $filters;
		$range = GWR_CPT::normalize_date_range( $filters['start_date'], $filters['end_date'] );
		if ( is_wp_error( $range ) ) { $error = $range->get_error_message(); $query_filters['start_date'] = ''; $query_filters['end_date'] = ''; } else { $query_filters['start_date'] = $range['start_date']; $query_filters['end_date'] = $range['end_date']; }
		return GWR_CPT::get_vehicles( array( 'frontend' => true, 'search' => $query_filters['search'], 'category' => $query_filters['category'], 'brand' => $query_filters['brand'], 'fuel' => $query_filters['fuel'], 'transmission' => $query_filters['transmission'], 'location' => $query_filters['location'], 'max_price' => $query_filters['max_price'], 'seats' => $query_filters['seats'], 'start_date' => $query_filters['start_date'], 'end_date' => $query_filters['end_date'] ) );
	}

	private static function normalize_request_date( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$iso_date = self::date_to_iso( $value );
		return $iso_date ?: $value;
	}

	private static function date_to_iso( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
			return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ? $value : '';
		}

		if ( preg_match( '/^(\d{2})-(\d{2})-(\d{4})$/', $value, $matches ) && checkdate( (int) $matches[2], (int) $matches[1], (int) $matches[3] ) ) {
			return sprintf( '%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1] );
		}

		return '';
	}

	private static function date_to_display( $value ) {
		$iso_date = self::date_to_iso( $value );
		if ( '' === $iso_date ) {
			return '';
		}

		$parts = explode( '-', $iso_date );
		return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
	}

	private static function date_summary( $filters ) {
		$start_date = self::date_to_display( $filters['start_date'] ?? '' );
		$end_date = self::date_to_display( $filters['end_date'] ?? '' );

		if ( $start_date && $end_date ) {
			return sprintf( __( 'Periodo selezionato: dal %1$s al %2$s.', 'gest-web-rent' ), $start_date, $end_date );
		}

		if ( $start_date || $end_date ) {
			return sprintf( __( 'Data selezionata: %s.', 'gest-web-rent' ), $start_date ?: $end_date );
		}

		return __( 'Seleziona le date e premi Cerca veicoli.', 'gest-web-rent' );
	}

	private static function filter_form( $filters, $error ) {
		$advanced_id = wp_unique_id( 'gwr-advanced-filters-' );
		$html = '<form class="gwr-filter-panel" method="get" data-gwr-filter-form novalidate>';
		$html .= '<div class="gwr-filter-intro">';
		$html .= '<span class="gwr-filter-eyebrow">' . esc_html__( 'Noleggio veicoli', 'gest-web-rent' ) . '</span>';
		$html .= '<h3>' . esc_html__( 'Trova il veicolo giusto per il tuo noleggio', 'gest-web-rent' ) . '</h3>';
		$html .= '<p>' . esc_html__( 'Seleziona il periodo e avvia la ricerca. Le date saranno riportate anche nei messaggi di contatto.', 'gest-web-rent' ) . '</p>';
		$html .= '</div>';
		$html .= '<div class="gwr-filter-primary"><div class="gwr-date-grid">';
		$html .= self::date_field( 'start_date', __( 'Data inizio', 'gest-web-rent' ), $filters['start_date'] );
		$html .= self::date_field( 'end_date', __( 'Data fine', 'gest-web-rent' ), $filters['end_date'] );
		$html .= '</div><button type="submit" class="gwr-search-button" data-gwr-search-submit><span data-gwr-search-label>' . esc_html__( 'Cerca veicoli', 'gest-web-rent' ) . '</span><span class="gwr-search-button__spinner" aria-hidden="true"></span></button></div>';
		$html .= '<button type="button" class="gwr-filter-toggle" data-gwr-filter-toggle aria-expanded="false" aria-controls="' . esc_attr( $advanced_id ) . '"><span>' . esc_html__( 'Altri filtri', 'gest-web-rent' ) . '</span><span class="gwr-filter-toggle__icon" aria-hidden="true"></span></button>';
		$html .= '<div id="' . esc_attr( $advanced_id ) . '" class="gwr-filter-advanced" data-gwr-filter-advanced hidden><div class="gwr-filter-grid">';
		$html .= self::text_field( 'search', __( 'Ricerca libera', 'gest-web-rent' ), $filters['search'], __( 'Marca, modello, titolo...', 'gest-web-rent' ) );
		$html .= self::select_field( 'category', __( 'Categoria', 'gest-web-rent' ), $filters['category'], GWR_CPT::filter_values( 'category' ) );
		$html .= self::select_field( 'brand', __( 'Marca', 'gest-web-rent' ), $filters['brand'], GWR_CPT::filter_values( 'brand' ) );
		$html .= self::select_field( 'fuel', __( 'Alimentazione', 'gest-web-rent' ), $filters['fuel'], GWR_CPT::filter_values( 'fuel' ) );
		$html .= self::select_field( 'transmission', __( 'Cambio', 'gest-web-rent' ), $filters['transmission'], GWR_CPT::filter_values( 'transmission' ) );
		$html .= self::select_field( 'location', __( 'Sede', 'gest-web-rent' ), $filters['location'], GWR_CPT::filter_values( 'location' ) );
		$html .= self::number_field( 'max_price', __( 'Prezzo massimo', 'gest-web-rent' ), $filters['max_price'], '100' );
		$html .= self::number_field( 'seats', __( 'Posti minimi', 'gest-web-rent' ), $filters['seats'], '5' );
		$html .= '</div><div class="gwr-filter-actions"><button type="button" class="gwr-button-secondary" data-gwr-reset-filters>' . esc_html__( 'Reset filtri', 'gest-web-rent' ) . '</button></div></div></form>';
		return $html;
	}

	private static function cards_html( $vehicles, $filters ) {
		if ( empty( $vehicles ) ) { return '<div class="gwr-empty-state"><h3>' . esc_html__( 'Nessun veicolo disponibile', 'gest-web-rent' ) . '</h3><p>' . esc_html__( 'Modifica date o filtri per vedere altre opzioni.', 'gest-web-rent' ) . '</p></div>'; }
		$html = '';
		foreach ( $vehicles as $vehicle ) { $html .= self::card_markup( $vehicle, $filters ); }
		return $html;
	}

	private static function card_markup( $vehicle, $filters ) {
		$payload = GWR_CPT::public_payload( $vehicle );
		$cover = $payload['images'] ? $payload['images'][0]['url'] : '';
		$title_line = trim( $vehicle['brand'] . ' ' . $vehicle['model'] . ' ' . $vehicle['version'] );
		$daily_price = GWR_CPT::format_price( $vehicle['daily_price'] );
		$payload_json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$payload_encoded = base64_encode( $payload_json );
		$html = '<article class="gwr-rent-card" data-gwr-card>';
		$html .= '<button type="button" class="gwr-rent-card__button" data-gwr-open-modal data-gwr-vehicle-b64="' . esc_attr( $payload_encoded ) . '" aria-label="' . esc_attr( sprintf( __( 'Apri dettagli e contatti per %s', 'gest-web-rent' ), $vehicle['title'] ) ) . '">';
		$html .= '<span class="gwr-rent-card__media">';
		$html .= $cover ? '<img src="' . esc_url( $cover ) . '" alt="' . esc_attr( $vehicle['title'] ) . '" loading="lazy" />' : '<span class="gwr-rent-card__placeholder">' . esc_html__( 'Foto veicolo', 'gest-web-rent' ) . '</span>';
		$html .= '<span class="gwr-rent-card__status">' . esc_html__( 'Disponibile', 'gest-web-rent' ) . '</span></span>';
		$html .= '<span class="gwr-rent-card__body"><span class="gwr-card-topline"><span class="gwr-card__badge">' . esc_html( $vehicle['category'] ?: __( 'Noleggio', 'gest-web-rent' ) ) . '</span>';
		if ( ! empty( $vehicle['featured'] ) ) { $html .= '<span class="gwr-card__featured">' . esc_html__( 'In evidenza', 'gest-web-rent' ) . '</span>'; }
		$html .= '</span><strong class="gwr-rent-card__title">' . esc_html( $vehicle['title'] ) . '</strong><span class="gwr-rent-card__model">' . esc_html( $title_line ) . '</span>';
		$html .= self::spec_pills( $vehicle );
		$html .= '<span class="gwr-rent-card__footer"><span class="gwr-rent-card__price"><strong>' . esc_html( $daily_price ?: __( 'Su richiesta', 'gest-web-rent' ) ) . '</strong>' . ( $daily_price ? '<small>' . esc_html__( '/ giorno', 'gest-web-rent' ) . '</small>' : '' ) . '</span><span class="gwr-button">' . esc_html__( 'Dettagli e contatto', 'gest-web-rent' ) . '</span></span>';
		$html .= '</span></button></article>';
		return $html;
	}

	private static function spec_pills( $vehicle ) {
		$items = array_filter( array( $vehicle['included_km_daily'] ? $vehicle['included_km_daily'] . ' km/giorno' : '', $vehicle['fuel'], $vehicle['transmission'], $vehicle['seats'] ? $vehicle['seats'] . ' posti' : '', $vehicle['location'] ) );
		$html = '<span class="gwr-spec-pill-list">';
		foreach ( $items as $item ) { $html .= '<span>' . esc_html( $item ) . '</span>'; }
		return $html . '</span>';
	}

	private static function modal_markup() {
		return '<div class="gwr-modal" data-gwr-modal aria-hidden="true" hidden><div class="gwr-modal__backdrop" data-gwr-close-modal></div><div class="gwr-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="gwr-modal-title" tabindex="-1"><button type="button" class="gwr-modal__close" data-gwr-close-modal aria-label="' . esc_attr__( 'Chiudi', 'gest-web-rent' ) . '">&times;</button><div class="gwr-modal__content" data-gwr-modal-content></div></div></div>';
	}

	private static function date_field( $name, $label, $value ) {
		$field_id = wp_unique_id( 'gwr-' . sanitize_key( $name ) . '-' );
		$iso_id = $field_id . '-iso';
		$help_id = $field_id . '-help';
		$iso_value = self::date_to_iso( $value );
		$display_value = self::date_to_display( $value );
		if ( '' === $display_value && '' !== (string) $value ) {
			$display_value = sanitize_text_field( (string) $value );
		}

		$html = '<label for="' . esc_attr( $field_id ) . '"><span>' . esc_html( $label ) . '</span>';
		$html .= '<input id="' . esc_attr( $field_id ) . '" type="text" name="' . esc_attr( $name ) . '_display" value="' . esc_attr( $display_value ) . '" placeholder="GG-MM-AAAA" inputmode="numeric" autocomplete="off" pattern="[0-9]{2}-[0-9]{2}-[0-9]{4}" aria-describedby="' . esc_attr( $help_id ) . '" data-gwr-date-display data-gwr-date-target="' . esc_attr( $iso_id ) . '" />';
		$html .= '<small id="' . esc_attr( $help_id ) . '">' . esc_html__( 'Formato GG-MM-AAAA', 'gest-web-rent' ) . '</small></label>';
		$html .= '<input id="' . esc_attr( $iso_id ) . '" type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $iso_value ) . '" data-gwr-date-iso />';
		return $html;
	}
	private static function text_field( $name, $label, $value, $placeholder ) { return '<label><span>' . esc_html( $label ) . '</span><input type="search" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" /></label>'; }
	private static function number_field( $name, $label, $value, $placeholder ) { return '<label><span>' . esc_html( $label ) . '</span><input type="number" min="0" step="any" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" /></label>'; }
	private static function select_field( $name, $label, $value, $options ) { $html = '<label><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'Tutti', 'gest-web-rent' ) . '</option>'; foreach ( $options as $option ) { $html .= '<option value="' . esc_attr( $option ) . '" ' . selected( $value, $option, false ) . '>' . esc_html( $option ) . '</option>'; } return $html . '</select></label>'; }
	private static function style_vars( $max_width ) { $settings = GWR_Admin::get_settings(); $max_width = max( 960, min( 1800, absint( $max_width ) ) ); return '--gwr-primary:' . esc_attr( $settings['primary_color'] ) . ';--gwr-shell-max:' . esc_attr( $max_width ) . 'px;'; }
}
