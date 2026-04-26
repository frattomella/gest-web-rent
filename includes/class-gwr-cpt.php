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
	const DB_VERSION = '1.0.1';

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
	 * Availability table name.
	 *
	 * @return string
	 */
	public static function availability_table() {
		global $wpdb;

		return $wpdb->prefix . 'gwr_availability';
	}

	/**
	 * Ensure storage exists after install/update.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_storage() {
		if ( get_option( 'gwr_db_version' ) === self::DB_VERSION ) {
			return;
		}

		self::create_availability_table();
		self::maybe_migrate_availability_meta();
		update_option( 'gwr_db_version', self::DB_VERSION, false );
	}

	/**
	 * Create custom availability table.
	 *
	 * @return void
	 */
	public static function create_availability_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::availability_table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			vehicle_id bigint(20) unsigned NOT NULL,
			start_date date NOT NULL,
			end_date date NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'occupied',
			internal_note text NULL,
			external_reference varchar(191) NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY vehicle_id (vehicle_id),
			KEY date_range (start_date,end_date),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Migrate old post meta availability rows, when present.
	 *
	 * @return void
	 */
	public static function maybe_migrate_availability_meta() {
		if ( get_option( 'gwr_availability_meta_migrated_101' ) ) {
			return;
		}

		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_gwr_availability',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $query->posts as $vehicle_id ) {
			$rows = get_post_meta( $vehicle_id, '_gwr_availability', true );
			if ( is_array( $rows ) && ! empty( $rows ) && empty( self::availability_rows( $vehicle_id ) ) ) {
				self::save_availability_rows( $vehicle_id, $rows );
			}
		}

		update_option( 'gwr_availability_meta_migrated_101', current_time( 'mysql' ), false );
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
					'price_weekend'  => array( 'label' => __( 'Prezzo weekend', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '149' ),
					'price_week'     => array( 'label' => __( 'Prezzo settimanale', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '350' ),
					'price_month'    => array( 'label' => __( 'Prezzo mensile', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '1200' ),
					'deposit'        => array( 'label' => __( 'Cauzione', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '500' ),
					'deductible'     => array( 'label' => __( 'Franchigia', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '1000' ),
					'extra_km_price' => array( 'label' => __( 'Costo km extra', 'gest-web-rent' ), 'type' => 'text', 'placeholder' => '0,25 euro/km' ),
				),
			),
			'limits'     => array(
				'label'  => __( 'Limiti e requisiti noleggio', 'gest-web-rent' ),
				'fields' => array(
					'max_km_day'      => array( 'label' => __( 'Km inclusi/giorno', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '150' ),
					'max_km_week'     => array( 'label' => __( 'Km inclusi/settimana', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '900' ),
					'max_km_month'    => array( 'label' => __( 'Km inclusi/mese', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '3000' ),
					'min_age'         => array( 'label' => __( 'Eta minima conducente', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '21' ),
					'min_license_years' => array( 'label' => __( 'Anni minimi patente', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '1' ),
					'license_required'=> array( 'label' => __( 'Patente richiesta', 'gest-web-rent' ), 'placeholder' => 'B' ),
					'min_rental_days' => array( 'label' => __( 'Durata minima noleggio', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '1' ),
					'max_rental_days' => array( 'label' => __( 'Durata massima noleggio', 'gest-web-rent' ), 'type' => 'number', 'placeholder' => '30' ),
					'insurance_included' => array( 'label' => __( 'Assicurazione inclusa', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'second_driver' => array( 'label' => __( 'Secondo conducente incluso', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'home_delivery' => array( 'label' => __( 'Consegna a domicilio', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'insurance'       => array( 'label' => __( 'Copertura assicurativa', 'gest-web-rent' ), 'type' => 'textarea', 'placeholder' => 'RCA inclusa, furto/incendio opzionale...' ),
					'rental_notes'    => array( 'label' => __( 'Note noleggio', 'gest-web-rent' ), 'type' => 'textarea', 'placeholder' => 'Condizioni speciali, esclusioni, deposito...' ),
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
					'color'        => array( 'label' => __( 'Colore', 'gest-web-rent' ), 'placeholder' => 'Bianco' ),
					'plate'        => array( 'label' => __( 'Targa', 'gest-web-rent' ), 'placeholder' => 'AB123CD' ),
				),
			),
			'equipment'  => array(
				'label'  => __( 'Dotazioni', 'gest-web-rent' ),
				'fields' => array(
					'eq_air_conditioning' => array( 'label' => __( 'Climatizzatore', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'eq_navigation'       => array( 'label' => __( 'Navigatore', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'eq_bluetooth'        => array( 'label' => __( 'Bluetooth', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'eq_apple_carplay'    => array( 'label' => __( 'Apple CarPlay', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'eq_android_auto'     => array( 'label' => __( 'Android Auto', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'eq_parking_sensors'  => array( 'label' => __( 'Sensori parcheggio', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'eq_camera'           => array( 'label' => __( 'Telecamera', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'eq_cruise_control'   => array( 'label' => __( 'Cruise control', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'eq_abs'              => array( 'label' => __( 'ABS', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'eq_esp'              => array( 'label' => __( 'ESP', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'eq_airbag'           => array( 'label' => __( 'Airbag', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'eq_winter_tires'     => array( 'label' => __( 'Pneumatici invernali', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'eq_child_seat'       => array( 'label' => __( 'Seggiolino', 'gest-web-rent' ), 'type' => 'checkbox' ),
					'eq_snow_chains'      => array( 'label' => __( 'Catene neve', 'gest-web-rent' ), 'type' => 'checkbox' ),
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

		if ( 'checkbox' === $type ) {
			echo '<span class="gwr-field__toggle-inline"><input type="checkbox" id="gwr_' . esc_attr( $key ) . '" name="gwr_meta[' . esc_attr( $key ) . ']" value="1" ' . checked( $value, '1', false ) . ' /> ' . esc_html__( 'Si', 'gest-web-rent' ) . '</span>';
		} elseif ( 'textarea' === $type ) {
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
				'id'                 => 0,
				'start_date'         => '',
				'end_date'           => '',
				'status'             => 'occupied',
				'internal_note'      => '',
				'external_reference' => '',
			)
		);

		if ( empty( $row['start_date'] ) && ! empty( $row['date_from'] ) ) {
			$row['start_date'] = $row['date_from'];
		}

		if ( empty( $row['end_date'] ) && ! empty( $row['date_to'] ) ) {
			$row['end_date'] = $row['date_to'];
		}

		echo '<div class="gwr-availability-row" data-gwr-availability-row>';
		echo '<input type="hidden" name="gwr_availability[' . esc_attr( $index ) . '][id]" value="' . esc_attr( $row['id'] ) . '" />';
		echo '<label><span>' . esc_html__( 'Data inizio', 'gest-web-rent' ) . '</span><input type="date" name="gwr_availability[' . esc_attr( $index ) . '][start_date]" value="' . esc_attr( $row['start_date'] ) . '" /></label>';
		echo '<label><span>' . esc_html__( 'Data fine', 'gest-web-rent' ) . '</span><input type="date" name="gwr_availability[' . esc_attr( $index ) . '][end_date]" value="' . esc_attr( $row['end_date'] ) . '" /></label>';
		echo '<label><span>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</span><select name="gwr_availability[' . esc_attr( $index ) . '][status]">';

		foreach ( self::availability_statuses() as $status => $label ) {
			echo '<option value="' . esc_attr( $status ) . '" ' . selected( $row['status'], $status, false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select></label>';
		echo '<label><span>' . esc_html__( 'Riferimento esterno', 'gest-web-rent' ) . '</span><input type="text" name="gwr_availability[' . esc_attr( $index ) . '][external_reference]" value="' . esc_attr( $row['external_reference'] ) . '" placeholder="' . esc_attr__( 'Pratica, contratto, cliente', 'gest-web-rent' ) . '" /></label>';
		echo '<label class="gwr-availability-row__note"><span>' . esc_html__( 'Nota interna', 'gest-web-rent' ) . '</span><input type="text" name="gwr_availability[' . esc_attr( $index ) . '][internal_note]" value="' . esc_attr( $row['internal_note'] ) . '" /></label>';
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

			if ( 'checkbox' === ( $field['type'] ?? '' ) ) {
				$value = ! empty( $value ) ? '1' : '0';
			} elseif ( 'number' === ( $field['type'] ?? '' ) ) {
				$value = '' === $value ? '' : (float) str_replace( ',', '.', (string) $value );
			} else {
				$value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_textarea_field( $value );
			}

			update_post_meta( $post_id, '_gwr_' . $key, $value );
		}

		update_post_meta( $post_id, '_gwr_featured', isset( $_POST['gwr_featured'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_gwr_badge', isset( $_POST['gwr_badge'] ) ? sanitize_text_field( wp_unslash( $_POST['gwr_badge'] ) ) : '' );
		self::save_availability_rows( $post_id, isset( $_POST['gwr_availability'] ) ? wp_unslash( $_POST['gwr_availability'] ) : array() );

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

			$date_from = isset( $row['start_date'] ) ? sanitize_text_field( $row['start_date'] ) : ( isset( $row['date_from'] ) ? sanitize_text_field( $row['date_from'] ) : '' );
			$date_to   = isset( $row['end_date'] ) ? sanitize_text_field( $row['end_date'] ) : ( isset( $row['date_to'] ) ? sanitize_text_field( $row['date_to'] ) : '' );

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

			if ( ! self::is_valid_date( $date_from ) || ! self::is_valid_date( $date_to ) ) {
				continue;
			}

			$status = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'occupied';
			$status = array_key_exists( $status, self::availability_statuses() ) ? $status : 'occupied';

			$clean[] = array(
				'id'                 => isset( $row['id'] ) ? absint( $row['id'] ) : 0,
				'start_date'         => $date_from,
				'end_date'           => $date_to,
				'date_from'          => $date_from,
				'date_to'            => $date_to,
				'status'             => $status,
				'internal_note'      => isset( $row['internal_note'] ) ? sanitize_text_field( $row['internal_note'] ) : ( isset( $row['note'] ) ? sanitize_text_field( $row['note'] ) : '' ),
				'external_reference' => isset( $row['external_reference'] ) ? sanitize_text_field( $row['external_reference'] ) : ( isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '' ),
			);
		}

		usort(
			$clean,
			function ( $a, $b ) {
				return strcmp( $a['start_date'], $b['start_date'] );
			}
		);

		return $clean;
	}

	/**
	 * Validate date in Y-m-d format.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	private static function is_valid_date( $date ) {
		$parsed = date_create_from_format( 'Y-m-d', (string) $date );

		return $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Availability statuses.
	 *
	 * @return array
	 */
	public static function availability_statuses() {
		return array(
			'occupied'    => __( 'Occupato', 'gest-web-rent' ),
			'maintenance' => __( 'Manutenzione', 'gest-web-rent' ),
			'reserved'    => __( 'Riservato', 'gest-web-rent' ),
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
		global $wpdb;

		$table = self::availability_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, vehicle_id, start_date, end_date, status, internal_note, external_reference, created_at, updated_at FROM {$table} WHERE vehicle_id = %d ORDER BY start_date ASC, end_date ASC",
				absint( $post_id )
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$row['date_from'] = $row['start_date'];
			$row['date_to']   = $row['end_date'];
			$row['label']     = $row['external_reference'];
			$row['note']      = $row['internal_note'];
		}

		return $rows;
	}

	/**
	 * Persist availability rows in custom table.
	 *
	 * @param int   $post_id Vehicle ID.
	 * @param mixed $rows Raw rows.
	 * @return void
	 */
	public static function save_availability_rows( $post_id, $rows ) {
		global $wpdb;

		$post_id = absint( $post_id );
		$table   = self::availability_table();
		$clean   = self::sanitize_availability_rows( $rows );
		$now     = current_time( 'mysql' );

		$wpdb->delete( $table, array( 'vehicle_id' => $post_id ), array( '%d' ) );

		foreach ( $clean as $row ) {
			$wpdb->insert(
				$table,
				array(
					'vehicle_id'          => $post_id,
					'start_date'          => $row['start_date'],
					'end_date'            => $row['end_date'],
					'status'              => $row['status'],
					'internal_note'       => $row['internal_note'],
					'external_reference'  => $row['external_reference'],
					'created_at'          => $now,
					'updated_at'          => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
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

		global $wpdb;

		$table = self::availability_table();
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE vehicle_id = %d AND start_date <= %s AND end_date >= %s",
				absint( $post_id ),
				$date_to,
				$date_from
			)
		);

		return 0 === $count;
	}

	/**
	 * Upcoming availability rows.
	 *
	 * @param int|null $post_id Optional vehicle ID.
	 * @param int      $limit Limit.
	 * @return array
	 */
	public static function upcoming_availability( $post_id = null, $limit = 20 ) {
		global $wpdb;

		$items = array();
		$today = current_time( 'Y-m-d' );
		$table = self::availability_table();

		if ( $post_id ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, vehicle_id, start_date, end_date, status, internal_note, external_reference FROM {$table} WHERE vehicle_id = %d AND end_date >= %s ORDER BY start_date ASC LIMIT %d",
					absint( $post_id ),
					$today,
					absint( $limit )
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, vehicle_id, start_date, end_date, status, internal_note, external_reference FROM {$table} WHERE end_date >= %s ORDER BY start_date ASC LIMIT %d",
					$today,
					absint( $limit )
				),
				ARRAY_A
			);
		}

		foreach ( (array) $rows as $row ) {
			$row['date_from']     = $row['start_date'];
			$row['date_to']       = $row['end_date'];
			$row['label']         = $row['external_reference'];
			$row['note']          = $row['internal_note'];
			$row['vehicle_title'] = get_the_title( (int) $row['vehicle_id'] );
			$items[]              = $row;
		}

		return $items;
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
