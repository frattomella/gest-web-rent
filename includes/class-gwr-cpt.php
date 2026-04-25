<?php
/**
 * Custom post types and vehicle metadata.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Vehicle CPT, metadata and availability storage.
 */
class GWR_CPT {
	const POST_TYPE = 'gwr_vehicle';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_vehicle_meta' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	/**
	 * Register post types.
	 *
	 * @return void
	 */
	public static function register_post_types() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'               => __( 'Veicoli', 'gest-web-rent' ),
					'singular_name'      => __( 'Veicolo', 'gest-web-rent' ),
					'add_new'            => __( 'Aggiungi nuovo', 'gest-web-rent' ),
					'add_new_item'       => __( 'Aggiungi veicolo', 'gest-web-rent' ),
					'edit_item'          => __( 'Modifica veicolo', 'gest-web-rent' ),
					'new_item'           => __( 'Nuovo veicolo', 'gest-web-rent' ),
					'view_item'          => __( 'Vedi scheda veicolo', 'gest-web-rent' ),
					'search_items'       => __( 'Cerca veicoli', 'gest-web-rent' ),
					'not_found'          => __( 'Nessun veicolo trovato', 'gest-web-rent' ),
					'not_found_in_trash' => __( 'Nessun veicolo nel cestino', 'gest-web-rent' ),
					'menu_name'          => __( 'Veicoli', 'gest-web-rent' ),
				),
				'public'       => true,
				'show_in_menu' => false,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'noleggio-veicoli' ),
				'menu_icon'    => 'dashicons-car',
			)
		);
	}

	/**
	 * Register useful taxonomies for filtering and future growth.
	 *
	 * @return void
	 */
	public static function register_taxonomies() {
		$taxonomies = array(
			'gwr_brand'        => __( 'Marca', 'gest-web-rent' ),
			'gwr_fuel'         => __( 'Alimentazione', 'gest-web-rent' ),
			'gwr_vehicle_type' => __( 'Categoria veicolo', 'gest-web-rent' ),
		);

		foreach ( $taxonomies as $slug => $label ) {
			register_taxonomy(
				$slug,
				self::POST_TYPE,
				array(
					'labels'            => array(
						'name'          => $label,
						'singular_name' => $label,
					),
					'public'            => true,
					'show_in_rest'      => true,
					'hierarchical'      => false,
					'show_admin_column' => true,
				)
			);
		}
	}

	/**
	 * Register vehicle metaboxes.
	 *
	 * @return void
	 */
	public static function register_meta_boxes() {
		add_meta_box( 'gwr_vehicle_rental', __( 'Scheda noleggio', 'gest-web-rent' ), array( __CLASS__, 'render_rental_metabox' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'gwr_vehicle_availability', __( 'Disponibilita e impegni', 'gest-web-rent' ), array( __CLASS__, 'render_availability_metabox' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'gwr_vehicle_marketing', __( 'Frontend e contatto', 'gest-web-rent' ), array( __CLASS__, 'render_marketing_metabox' ), self::POST_TYPE, 'side', 'high' );
	}

	/**
	 * Vehicle fields grouped for admin and frontend rendering.
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'identity'   => array(
				'label'  => __( 'Identita veicolo', 'gest-web-rent' ),
				'fields' => array(
					'brand'    => array( 'label' => __( 'Marca', 'gest-web-rent' ), 'placeholder' => 'Fiat' ),
					'model'    => array( 'label' => __( 'Modello', 'gest-web-rent' ), 'placeholder' => 'Ducato' ),
					'version'  => array( 'label' => __( 'Versione / allestimento', 'gest-web-rent' ), 'placeholder' => 'Maxi 35 L3H2' ),
					'category' => array(
						'label'   => __( 'Categoria', 'gest-web-rent' ),
						'type'    => 'select',
						'options' => array(
							'auto'       => __( 'Auto', 'gest-web-rent' ),
							'furgone'    => __( 'Furgone', 'gest-web-rent' ),
							'scooter'    => __( 'Scooter', 'gest-web-rent' ),
							'moto'       => __( 'Moto', 'gest-web-rent' ),
							'minibus'    => __( 'Minibus', 'gest-web-rent' ),
							'altro'      => __( 'Altro', 'gest-web-rent' ),
						),
					),
					'location' => array( 'label' => __( 'Sede ritiro', 'gest-web-rent' ), 'placeholder' => 'Roma Nord' ),
				),
			),
			'pricing'    => array(
				'label'  => __( 'Condizioni economiche', 'gest-web-rent' ),
				'fields' => array(
					'price_day'      => array( 'label' => __( 'Prezzo giornaliero', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '59' ),
					'price_week'     => array( 'label' => __( 'Prezzo settimanale', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '350' ),
					'price_month'    => array( 'label' => __( 'Prezzo mensile', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '1200' ),
					'deposit'        => array( 'label' => __( 'Cauzione', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '500' ),
					'extra_km_price' => array( 'label' => __( 'Costo km extra', 'gest-web-rent' ), 'type' => 'text', 'placeholder' => '0,25 euro/km' ),
				),
			),
			'limits'     => array(
				'label'  => __( 'Limiti e requisiti noleggio', 'gest-web-rent' ),
				'fields' => array(
					'max_km_day'      => array( 'label' => __( 'Km inclusi/giorno', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '150' ),
					'max_km_month'    => array( 'label' => __( 'Km inclusi/mese', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '3000' ),
					'min_age'         => array( 'label' => __( 'Eta minima conducente', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '21' ),
					'license_required'=> array( 'label' => __( 'Patente richiesta', 'gest-web-rent' ), 'placeholder' => 'B' ),
					'insurance'       => array( 'label' => __( 'Copertura assicurativa', 'gest-web-rent' ), 'type' => 'textarea', 'placeholder' => 'RCA inclusa, furto/incendio opzionale...' ),
				),
			),
			'technical'  => array(
				'label'  => __( 'Dati tecnici', 'gest-web-rent' ),
				'fields' => array(
					'year'         => array( 'label' => __( 'Anno', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '2024' ),
					'mileage'      => array( 'label' => __( 'Chilometraggio', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '12500' ),
					'fuel'         => array( 'label' => __( 'Alimentazione', 'gest-web-rent' ), 'placeholder' => 'Diesel' ),
					'transmission' => array( 'label' => __( 'Cambio', 'gest-web-rent' ), 'placeholder' => 'Manuale' ),
					'seats'        => array( 'label' => __( 'Posti', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '3' ),
					'doors'        => array( 'label' => __( 'Porte', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '4' ),
					'plate'        => array( 'label' => __( 'Targa', 'gest-web-rent' ), 'placeholder' => 'AB123CD' ),
				),
			),
			'notes'      => array(
				'label'  => __( 'Informazioni commerciali', 'gest-web-rent' ),
				'fields' => array(
					'included_services' => array( 'label' => __( 'Servizi inclusi', 'gest-web-rent' ), 'type' => 'textarea', 'placeholder' => "Assistenza stradale\nSecondo conducente\nSanificazione" ),
					'accessories'       => array( 'label' => __( 'Dotazioni e accessori', 'gest-web-rent' ), 'type' => 'textarea', 'placeholder' => "Bluetooth\nSensori parcheggio\nPortapacchi" ),
					'pickup_notes'      => array( 'label' => __( 'Note ritiro/consegna', 'gest-web-rent' ), 'type' => 'textarea', 'placeholder' => 'Ritiro in sede dalle 9:00 alle 18:00.' ),
					'gallery_urls'      => array( 'label' => __( 'URL galleria', 'gest-web-rent' ), 'type' => 'textarea', 'placeholder' => "https://...\nhttps://..." ),
				),
			),
		);
	}

	/**
	 * Flat list of all field definitions.
	 *
	 * @return array
	 */
	public static function flat_fields() {
		$flat = array();

		foreach ( self::fields() as $group ) {
			foreach ( $group['fields'] as $key => $field ) {
				$flat[ $key ] = $field;
			}
		}

		return $flat;
	}

	/**
	 * Render rental metabox.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_rental_metabox( $post ) {
		wp_nonce_field( 'gwr_save_vehicle', 'gwr_vehicle_nonce' );

		echo '<div class="gwr-admin-metabox">';

		foreach ( self::fields() as $group_key => $group ) {
			echo '<section class="gwr-admin-metabox__section gwr-admin-metabox__section--' . esc_attr( $group_key ) . '">';
			echo '<h3>' . esc_html( $group['label'] ) . '</h3>';
			echo '<div class="gwr-field-grid">';

			foreach ( $group['fields'] as $key => $field ) {
				$value = get_post_meta( $post->ID, '_gwr_' . $key, true );
				self::render_field( $key, $field, $value );
			}

			echo '</div>';
			echo '</section>';
		}

		echo '</div>';
	}

	/**
	 * Render a single field.
	 *
	 * @param string $key Field key.
	 * @param array  $field Field definition.
	 * @param mixed  $value Current value.
	 * @return void
	 */
	private static function render_field( $key, $field, $value ) {
		$type        = isset( $field['type'] ) ? $field['type'] : 'text';
		$placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
		$classes     = 'gwr-field';

		if ( 'textarea' === $type ) {
			$classes .= ' gwr-field--wide';
		}

		echo '<label class="' . esc_attr( $classes ) . '" for="gwr_' . esc_attr( $key ) . '">';
		echo '<span class="gwr-field__label">' . esc_html( $field['label'] ) . '</span>';

		if ( 'textarea' === $type ) {
			echo '<textarea id="gwr_' . esc_attr( $key ) . '" name="gwr_meta[' . esc_attr( $key ) . ']" rows="4" placeholder="' . esc_attr( $placeholder ) . '">' . esc_textarea( $value ) . '</textarea>';
		} elseif ( 'select' === $type ) {
			echo '<select id="gwr_' . esc_attr( $key ) . '" name="gwr_meta[' . esc_attr( $key ) . ']">';
			echo '<option value="">' . esc_html__( 'Seleziona', 'gest-web-rent' ) . '</option>';

			foreach ( $field['options'] as $option_value => $option_label ) {
				echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
			}

			echo '</select>';
		} else {
			echo '<input type="' . esc_attr( $type ) . '" id="gwr_' . esc_attr( $key ) . '" name="gwr_meta[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
		}

		echo '</label>';
	}

	/**
	 * Render availability rows metabox.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_availability_metabox( $post ) {
		$rows = self::availability_rows( $post->ID );

		echo '<div class="gwr-availability-editor" data-gwr-availability-editor>';
		echo '<p class="description">' . esc_html__( 'Inserisci i periodi in cui il veicolo e impegnato, in manutenzione o non disponibile. Il catalogo filtrera automaticamente le date richieste.', 'gest-web-rent' ) . '</p>';
		echo '<div class="gwr-availability-editor__rows" data-gwr-availability-rows>';

		if ( empty( $rows ) ) {
			self::render_availability_row( 0, array() );
		} else {
			foreach ( $rows as $index => $row ) {
				self::render_availability_row( $index, $row );
			}
		}

		echo '</div>';
		echo '<button type="button" class="button button-secondary" data-gwr-add-availability>' . esc_html__( 'Aggiungi periodo', 'gest-web-rent' ) . '</button>';
		echo '</div>';
	}

	/**
	 * Render a single availability row.
	 *
	 * @param int   $index Row index.
	 * @param array $row Row data.
	 * @return void
	 */
	private static function render_availability_row( $index, $row ) {
		$row = wp_parse_args(
			(array) $row,
			array(
				'date_from' => '',
				'date_to'   => '',
				'status'    => 'booked',
				'label'     => '',
				'note'      => '',
			)
		);

		echo '<div class="gwr-availability-row" data-gwr-availability-row>';
		echo '<label><span>' . esc_html__( 'Da', 'gest-web-rent' ) . '</span><input type="date" name="gwr_availability[' . esc_attr( $index ) . '][date_from]" value="' . esc_attr( $row['date_from'] ) . '" /></label>';
		echo '<label><span>' . esc_html__( 'A', 'gest-web-rent' ) . '</span><input type="date" name="gwr_availability[' . esc_attr( $index ) . '][date_to]" value="' . esc_attr( $row['date_to'] ) . '" /></label>';
		echo '<label><span>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</span><select name="gwr_availability[' . esc_attr( $index ) . '][status]">';

		foreach ( self::availability_statuses() as $status => $label ) {
			echo '<option value="' . esc_attr( $status ) . '" ' . selected( $row['status'], $status, false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select></label>';
		echo '<label><span>' . esc_html__( 'Riferimento', 'gest-web-rent' ) . '</span><input type="text" name="gwr_availability[' . esc_attr( $index ) . '][label]" value="' . esc_attr( $row['label'] ) . '" placeholder="' . esc_attr__( 'Cliente, pratica, motivo', 'gest-web-rent' ) . '" /></label>';
		echo '<label class="gwr-availability-row__note"><span>' . esc_html__( 'Note', 'gest-web-rent' ) . '</span><input type="text" name="gwr_availability[' . esc_attr( $index ) . '][note]" value="' . esc_attr( $row['note'] ) . '" /></label>';
		echo '<button type="button" class="button-link-delete" data-gwr-remove-availability>' . esc_html__( 'Rimuovi', 'gest-web-rent' ) . '</button>';
		echo '</div>';
	}

	/**
	 * Render marketing metabox.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_marketing_metabox( $post ) {
		$featured = get_post_meta( $post->ID, '_gwr_featured', true );
		$badge    = get_post_meta( $post->ID, '_gwr_badge', true );

		echo '<p><label><input type="checkbox" name="gwr_featured" value="1" ' . checked( $featured, '1', false ) . ' /> ' . esc_html__( 'Mostra in evidenza', 'gest-web-rent' ) . '</label></p>';
		echo '<p><label><strong>' . esc_html__( 'Badge card', 'gest-web-rent' ) . '</strong><br><input type="text" class="widefat" name="gwr_badge" value="' . esc_attr( $badge ) . '" placeholder="' . esc_attr__( 'Pronta consegna', 'gest-web-rent' ) . '" /></label></p>';
		echo '<p class="description">' . esc_html__( 'La card usa immagine in evidenza, descrizione breve, dati noleggio e box contatto globale configurato in dashboard.', 'gest-web-rent' ) . '</p>';
	}

	/**
	 * Save metadata.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function save_vehicle_meta( $post_id ) {
		if ( ! isset( $_POST['gwr_vehicle_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gwr_vehicle_nonce'] ) ), 'gwr_save_vehicle' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$values = isset( $_POST['gwr_meta'] ) && is_array( $_POST['gwr_meta'] ) ? wp_unslash( $_POST['gwr_meta'] ) : array();

		foreach ( self::flat_fields() as $key => $field ) {
			$value = isset( $values[ $key ] ) ? $values[ $key ] : '';

			if ( 'number' === ( $field['type'] ?? '' ) ) {
				$value = '' === $value ? '' : (float) str_replace( ',', '.', (string) $value );
			} else {
				$value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_textarea_field( $value );
			}

			update_post_meta( $post_id, '_gwr_' . $key, $value );
		}

		update_post_meta( $post_id, '_gwr_featured', isset( $_POST['gwr_featured'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_gwr_badge', isset( $_POST['gwr_badge'] ) ? sanitize_text_field( wp_unslash( $_POST['gwr_badge'] ) ) : '' );
		update_post_meta( $post_id, '_gwr_availability', self::sanitize_availability_rows( isset( $_POST['gwr_availability'] ) ? wp_unslash( $_POST['gwr_availability'] ) : array() ) );

		self::sync_taxonomies( $post_id );
	}

	/**
	 * Sync meta values to taxonomies for native filters.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function sync_taxonomies( $post_id ) {
		$map = array(
			'gwr_brand'        => get_post_meta( $post_id, '_gwr_brand', true ),
			'gwr_fuel'         => get_post_meta( $post_id, '_gwr_fuel', true ),
			'gwr_vehicle_type' => get_post_meta( $post_id, '_gwr_category', true ),
		);

		foreach ( $map as $taxonomy => $value ) {
			$value = trim( (string) $value );
			wp_set_object_terms( $post_id, $value ? array( $value ) : array(), $taxonomy, false );
		}
	}

	/**
	 * Sanitize availability rows.
	 *
	 * @param mixed $rows Raw rows.
	 * @return array
	 */
	public static function sanitize_availability_rows( $rows ) {
		$clean = array();

		if ( ! is_array( $rows ) ) {
			return $clean;
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$date_from = isset( $row['date_from'] ) ? sanitize_text_field( $row['date_from'] ) : '';
			$date_to   = isset( $row['date_to'] ) ? sanitize_text_field( $row['date_to'] ) : '';

			if ( ! $date_from && ! $date_to ) {
				continue;
			}

			if ( ! $date_from ) {
				$date_from = $date_to;
			}

			if ( ! $date_to ) {
				$date_to = $date_from;
			}

			if ( $date_from > $date_to ) {
				$tmp       = $date_from;
				$date_from = $date_to;
				$date_to   = $tmp;
			}

			$status = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'booked';
			$status = array_key_exists( $status, self::availability_statuses() ) ? $status : 'booked';

			$clean[] = array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'status'    => $status,
				'label'     => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '',
				'note'      => isset( $row['note'] ) ? sanitize_text_field( $row['note'] ) : '',
			);
		}

		usort(
			$clean,
			function ( $a, $b ) {
				return strcmp( $a['date_from'], $b['date_from'] );
			}
		);

		return $clean;
	}

	/**
	 * Availability statuses.
	 *
	 * @return array
	 */
	public static function availability_statuses() {
		return array(
			'booked'      => __( 'Impegnato', 'gest-web-rent' ),
			'maintenance' => __( 'Manutenzione', 'gest-web-rent' ),
			'unavailable' => __( 'Non disponibile', 'gest-web-rent' ),
		);
	}

	/**
	 * Get availability rows.
	 *
	 * @param int $post_id Vehicle ID.
	 * @return array
	 */
	public static function availability_rows( $post_id ) {
		$rows = get_post_meta( $post_id, '_gwr_availability', true );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Check if a vehicle is available in the requested range.
	 *
	 * @param int    $post_id Vehicle ID.
	 * @param string $date_from Start date Y-m-d.
	 * @param string $date_to End date Y-m-d.
	 * @return bool
	 */
	public static function is_available( $post_id, $date_from = '', $date_to = '' ) {
		if ( ! $date_from && ! $date_to ) {
			return true;
		}

		if ( ! $date_from ) {
			$date_from = $date_to;
		}

		if ( ! $date_to ) {
			$date_to = $date_from;
		}

		if ( $date_from > $date_to ) {
			$tmp       = $date_from;
			$date_from = $date_to;
			$date_to   = $tmp;
		}

		foreach ( self::availability_rows( $post_id ) as $row ) {
			if ( empty( $row['date_from'] ) || empty( $row['date_to'] ) ) {
				continue;
			}

			if ( $row['date_from'] <= $date_to && $row['date_to'] >= $date_from ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Upcoming availability rows.
	 *
	 * @param int|null $post_id Optional vehicle ID.
	 * @param int      $limit Limit.
	 * @return array
	 */
	public static function upcoming_availability( $post_id = null, $limit = 20 ) {
		$items = array();
		$today = current_time( 'Y-m-d' );
		$args  = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		if ( $post_id ) {
			$args['post__in'] = array( absint( $post_id ) );
		}

		$query = new WP_Query( $args );

		foreach ( $query->posts as $vehicle_id ) {
			foreach ( self::availability_rows( $vehicle_id ) as $row ) {
				if ( ! empty( $row['date_to'] ) && $row['date_to'] < $today ) {
					continue;
				}

				$row['vehicle_id']    = $vehicle_id;
				$row['vehicle_title'] = get_the_title( $vehicle_id );
				$items[]              = $row;
			}
		}

		usort(
			$items,
			function ( $a, $b ) {
				return strcmp( $a['date_from'], $b['date_from'] );
			}
		);

		return array_slice( $items, 0, $limit );
	}

	/**
	 * Admin list columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		return array(
			'cb'           => $columns['cb'],
			'title'        => __( 'Veicolo', 'gest-web-rent' ),
			'brand_model'  => __( 'Marca / modello', 'gest-web-rent' ),
			'price'        => __( 'Noleggio', 'gest-web-rent' ),
			'availability' => __( 'Disponibilita', 'gest-web-rent' ),
			'location'     => __( 'Sede', 'gest-web-rent' ),
			'date'         => $columns['date'],
		);
	}

	/**
	 * Admin list column content.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'brand_model':
				echo esc_html( trim( get_post_meta( $post_id, '_gwr_brand', true ) . ' ' . get_post_meta( $post_id, '_gwr_model', true ) ) );
				break;
			case 'price':
				$price = get_post_meta( $post_id, '_gwr_price_day', true );
				echo $price ? esc_html( GWR_Frontend::format_price( $price ) . ' / giorno' ) : esc_html__( 'Non indicato', 'gest-web-rent' );
				break;
			case 'availability':
				$available = self::is_available( $post_id, current_time( 'Y-m-d' ), current_time( 'Y-m-d' ) );
				echo '<span class="gwr-list-pill ' . ( $available ? 'is-ok' : 'is-busy' ) . '">' . esc_html( $available ? __( 'Disponibile oggi', 'gest-web-rent' ) : __( 'Impegnato oggi', 'gest-web-rent' ) ) . '</span>';
				break;
			case 'location':
				echo esc_html( get_post_meta( $post_id, '_gwr_location', true ) );
				break;
		}
	}
}
