<?php
/**
 * Custom admin UI for Gest Web Rent.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gest Web Rent admin dashboard and CRUD.
 */
class GWR_Admin {
	const OPTION_NAME = 'gwr_settings';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_gwr_save_vehicle', array( __CLASS__, 'handle_save_vehicle' ) );
		add_action( 'admin_post_gwr_delete_vehicle', array( __CLASS__, 'handle_delete_vehicle' ) );
		add_action( 'admin_post_gwr_duplicate_vehicle', array( __CLASS__, 'handle_duplicate_vehicle' ) );
		add_action( 'admin_post_gwr_save_availability', array( __CLASS__, 'handle_save_availability' ) );
		add_action( 'admin_post_gwr_delete_availability', array( __CLASS__, 'handle_delete_availability' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'dealer_name'         => get_bloginfo( 'name' ),
			'whatsapp_number'     => '',
			'contact_email'       => get_option( 'admin_email' ),
			'primary_color'       => '#173787',
			'whatsapp_message'    => 'Ciao, vorrei informazioni sul noleggio del veicolo {vehicle_title}. Date di interesse: {start_date} - {end_date}. Link: {site_url}',
			'email_subject'       => 'Richiesta noleggio {vehicle_title}',
			'email_body'          => "Buongiorno,\nvorrei ricevere informazioni sul noleggio del veicolo {vehicle_title}.\n\nDate di interesse:\nDal {start_date} al {end_date}\n\nGrazie.",
			'privacy_note'        => __( 'La disponibilita e indicativa e deve essere confermata dal concessionario.', 'gest-web-rent' ),
			'github_access_token' => '',
		);
	}

	/**
	 * Get settings with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return array_replace_recursive( self::default_settings(), $settings );
	}

	/**
	 * Register custom admin menu.
	 *
	 * @return void
	 */
	public static function admin_menu() {
		add_menu_page( 'Gest Web Rent', 'Gest Web Rent', 'manage_options', 'gest-web-rent', array( __CLASS__, 'dashboard_page' ), 'dashicons-car', 26 );
		add_submenu_page( 'gest-web-rent', __( 'Dashboard', 'gest-web-rent' ), __( 'Dashboard', 'gest-web-rent' ), 'manage_options', 'gest-web-rent', array( __CLASS__, 'dashboard_page' ) );
		add_submenu_page( 'gest-web-rent', __( 'Veicoli', 'gest-web-rent' ), __( 'Veicoli', 'gest-web-rent' ), 'manage_options', 'gwr-vehicles', array( __CLASS__, 'vehicles_page' ) );
		add_submenu_page( 'gest-web-rent', __( 'Aggiungi veicolo', 'gest-web-rent' ), __( 'Aggiungi veicolo', 'gest-web-rent' ), 'manage_options', 'gwr-vehicle-edit', array( __CLASS__, 'vehicle_edit_page' ) );
		add_submenu_page( 'gest-web-rent', __( 'Disponibilita', 'gest-web-rent' ), __( 'Disponibilita', 'gest-web-rent' ), 'manage_options', 'gwr-availability', array( __CLASS__, 'availability_page' ) );
		add_submenu_page( 'gest-web-rent', __( 'Impostazioni', 'gest-web-rent' ), __( 'Impostazioni', 'gest-web-rent' ), 'manage_options', 'gwr-settings', array( __CLASS__, 'settings_page' ) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook ) {
		$is_gwr = false !== strpos( (string) $hook, 'gest-web-rent' ) || false !== strpos( (string) $hook, 'gwr-' );
		if ( ! $is_gwr ) {
			return;
		}

		wp_enqueue_style( 'gwr-admin', GWR_PLUGIN_URL . 'admin/assets/gwr-admin.css', array(), gwr_asset_version( 'admin/assets/gwr-admin.css' ) );
		wp_enqueue_script( 'gwr-admin', GWR_PLUGIN_URL . 'admin/assets/gwr-admin.js', array( 'jquery' ), gwr_asset_version( 'admin/assets/gwr-admin.js' ), true );
		wp_enqueue_media();
		wp_localize_script(
			'gwr-admin',
			'gwrAdmin',
			array(
				'mediaTitle' => __( 'Seleziona foto veicolo', 'gest-web-rent' ),
				'mediaButton' => __( 'Usa foto selezionate', 'gest-web-rent' ),
				'remove' => __( 'Rimuovi', 'gest-web-rent' ),
				'cover' => __( 'Copertina', 'gest-web-rent' ),
				'moveUp' => __( 'Su', 'gest-web-rent' ),
				'moveDown' => __( 'Giu', 'gest-web-rent' ),
			)
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'gwr_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::default_settings(),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? wp_unslash( $input ) : array();
		$existing = self::get_settings();
		$output = array(
			'dealer_name'         => isset( $input['dealer_name'] ) ? sanitize_text_field( $input['dealer_name'] ) : '',
			'whatsapp_number'     => isset( $input['whatsapp_number'] ) ? preg_replace( '/\D+/', '', sanitize_text_field( $input['whatsapp_number'] ) ) : '',
			'contact_email'       => isset( $input['contact_email'] ) ? sanitize_email( $input['contact_email'] ) : '',
			'primary_color'       => isset( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : '#173787',
			'whatsapp_message'    => isset( $input['whatsapp_message'] ) ? sanitize_textarea_field( $input['whatsapp_message'] ) : '',
			'email_subject'       => isset( $input['email_subject'] ) ? sanitize_text_field( $input['email_subject'] ) : '',
			'email_body'          => isset( $input['email_body'] ) ? sanitize_textarea_field( $input['email_body'] ) : '',
			'privacy_note'        => isset( $input['privacy_note'] ) ? sanitize_textarea_field( $input['privacy_note'] ) : '',
			'github_access_token' => $existing['github_access_token'] ?? '',
		);

		if ( ! empty( $input['github_access_token_clear'] ) ) {
			$output['github_access_token'] = '';
		} elseif ( ! empty( $input['github_access_token'] ) ) {
			$output['github_access_token'] = sanitize_text_field( $input['github_access_token'] );
		}

		delete_site_transient( 'gwr_github_release' );

		return array_replace_recursive( self::default_settings(), $output );
	}

	/**
	 * Dashboard page.
	 *
	 * @return void
	 */
	public static function dashboard_page() {
		$counts = GWR_CPT::counts();
		$settings = self::get_settings();

		self::page_start(
			'dashboard',
			__( 'Dashboard Gest Web Rent', 'gest-web-rent' ),
			__( 'Pannello vetrina per noleggio veicoli: catalogo, contatti, disponibilita e schede in overlay.', 'gest-web-rent' ),
			array(
				array( 'label' => __( 'Aggiungi veicolo', 'gest-web-rent' ), 'url' => self::vehicle_edit_url(), 'primary' => true ),
				array( 'label' => __( 'Gestisci veicoli', 'gest-web-rent' ), 'url' => self::vehicles_url() ),
				array( 'label' => __( 'Impostazioni', 'gest-web-rent' ), 'url' => admin_url( 'admin.php?page=gwr-settings' ) ),
			)
		);

		echo '<div class="gwr-kpi-grid">';
		self::metric_card( __( 'Veicoli totali', 'gest-web-rent' ), $counts['total'], __( 'Archivio noleggio interno', 'gest-web-rent' ) );
		self::metric_card( __( 'Veicoli attivi', 'gest-web-rent' ), $counts['active'], __( 'Visibili nel catalogo', 'gest-web-rent' ) );
		self::metric_card( __( 'In evidenza', 'gest-web-rent' ), $counts['featured'], __( 'Spinti in alto nella griglia', 'gest-web-rent' ) );
		self::metric_card( __( 'Blocchi futuri', 'gest-web-rent' ), $counts['availability'], __( 'Indisponibilita inserite', 'gest-web-rent' ) );
		echo '</div>';

		echo '<div class="gwr-surface-grid">';
		echo '<section class="gwr-surface gwr-surface--accent"><span class="gwr-surface__eyebrow">' . esc_html__( 'Catalogo frontend', 'gest-web-rent' ) . '</span><h2>' . esc_html__( 'Un solo componente, tutto integrato', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Usa lo shortcode qui sotto: include filtri date automatici, card responsive, modal veicolo, gallery e contatti.', 'gest-web-rent' ) . '</p><code class="gwr-code-block">[gwr_catalog]</code></section>';
		echo '<section class="gwr-surface"><span class="gwr-surface__eyebrow">' . esc_html__( 'Stato contatti', 'gest-web-rent' ) . '</span><h2>' . esc_html__( 'WhatsApp ed email', 'gest-web-rent' ) . '</h2>';
		echo '<div class="gwr-status-stack">';
		self::status_line( __( 'WhatsApp Business', 'gest-web-rent' ), ! empty( $settings['whatsapp_number'] ) );
		self::status_line( __( 'Email concessionario', 'gest-web-rent' ), ! empty( $settings['contact_email'] ) );
		echo '</div><div class="gwr-inline-actions"><a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=gwr-settings' ) ) . '">' . esc_html__( 'Configura contatti', 'gest-web-rent' ) . '</a></div></section>';
		echo '</div>';

		self::render_quick_settings( $settings );
		self::render_upcoming_panel( GWR_CPT::upcoming_availability( null, 8 ) );
		self::page_end();
	}

	/**
	 * Vehicles page.
	 *
	 * @return void
	 */
	public static function vehicles_page() {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$vehicles = GWR_CPT::get_vehicles( array( 'search' => $search, 'status' => $status ) );

		self::page_start(
			'vehicles',
			__( 'Veicoli', 'gest-web-rent' ),
			__( 'Gestione custom del parco noleggio. Non usa la lista post WordPress.', 'gest-web-rent' ),
			array(
				array( 'label' => __( 'Aggiungi veicolo', 'gest-web-rent' ), 'url' => self::vehicle_edit_url(), 'primary' => true ),
			)
		);
		self::admin_notice();

		echo '<form method="get" class="gwr-toolbar"><input type="hidden" name="page" value="gwr-vehicles" />';
		echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Cerca veicolo, marca, modello...', 'gest-web-rent' ) . '" />';
		echo '<select name="status"><option value="">' . esc_html__( 'Tutti gli stati', 'gest-web-rent' ) . '</option>';
		foreach ( GWR_CPT::vehicle_statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><button class="button button-secondary">' . esc_html__( 'Filtra', 'gest-web-rent' ) . '</button></form>';

		echo '<section class="gwr-data-grid-card"><div class="gwr-table-wrap"><table class="widefat gwr-vehicles-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Foto', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Titolo', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Marca/modello', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Prezzo/giorno', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Km inclusi', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Prossima indisponibilita', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Azioni', 'gest-web-rent' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $vehicles ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'Nessun veicolo trovato.', 'gest-web-rent' ) . '</td></tr>';
		} else {
			foreach ( $vehicles as $vehicle ) {
				self::vehicle_row( $vehicle );
			}
		}
		echo '</tbody></table></div></section>';
		self::page_end();
	}

	/**
	 * Add/edit vehicle page.
	 *
	 * @return void
	 */
	public static function vehicle_edit_page() {
		$vehicle_id = isset( $_GET['vehicle_id'] ) ? absint( $_GET['vehicle_id'] ) : 0;
		$vehicle = $vehicle_id ? GWR_CPT::get_vehicle( $vehicle_id ) : GWR_CPT::empty_vehicle();
		if ( $vehicle_id && ! $vehicle ) {
			wp_die( esc_html__( 'Veicolo non trovato.', 'gest-web-rent' ) );
		}

		$title = $vehicle_id ? __( 'Modifica veicolo', 'gest-web-rent' ) : __( 'Aggiungi veicolo', 'gest-web-rent' );
		self::page_start(
			'vehicles',
			$title,
			__( 'Form custom dedicato al noleggio: dati, regole, foto e indisponibilita in una sola schermata.', 'gest-web-rent' ),
			array(
				array( 'label' => __( 'Torna ai veicoli', 'gest-web-rent' ), 'url' => self::vehicles_url() ),
			)
		);
		self::admin_notice();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-vehicle-form">';
		echo '<input type="hidden" name="action" value="gwr_save_vehicle" />';
		echo '<input type="hidden" name="vehicle_id" value="' . esc_attr( $vehicle_id ) . '" />';
		wp_nonce_field( 'gwr_save_vehicle', 'gwr_vehicle_nonce' );

		self::vehicle_form_sections( $vehicle );
		self::vehicle_images_section( $vehicle_id );
		self::vehicle_availability_section( $vehicle_id );

		echo '<div class="gwr-sticky-submit"><button type="submit" class="button button-primary button-hero">' . esc_html__( 'Salva veicolo', 'gest-web-rent' ) . '</button></div>';
		echo '</form>';
		self::page_end();
	}

	/**
	 * Availability global page.
	 *
	 * @return void
	 */
	public static function availability_page() {
		$vehicles = GWR_CPT::get_vehicles();
		$items = GWR_CPT::upcoming_availability( null, 200 );

		self::page_start(
			'availability',
			__( 'Disponibilita', 'gest-web-rent' ),
			__( 'Gestisci i periodi in cui i veicoli non devono apparire disponibili nel catalogo.', 'gest-web-rent' ),
			array(
				array( 'label' => __( 'Aggiungi veicolo', 'gest-web-rent' ), 'url' => self::vehicle_edit_url(), 'primary' => true ),
			)
		);
		self::admin_notice();

		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><div><h2>' . esc_html__( 'Aggiungi indisponibilita', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Tutti gli stati bloccano il veicolo nel filtro date frontend.', 'gest-web-rent' ) . '</p></div></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-inline-availability-form">';
		echo '<input type="hidden" name="action" value="gwr_save_availability" />';
		wp_nonce_field( 'gwr_save_availability', 'gwr_availability_nonce' );
		echo '<div class="gwr-field-grid gwr-field-grid--availability">';
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Veicolo', 'gest-web-rent' ) . '</span><select name="vehicle_id" required><option value="">' . esc_html__( 'Seleziona veicolo', 'gest-web-rent' ) . '</option>';
		foreach ( $vehicles as $vehicle ) {
			echo '<option value="' . esc_attr( $vehicle['id'] ) . '">' . esc_html( $vehicle['title'] ) . '</option>';
		}
		echo '</select></label>';
		self::availability_inputs();
		echo '</div><div class="gwr-form-submit"><button class="button button-primary">' . esc_html__( 'Salva indisponibilita', 'gest-web-rent' ) . '</button></div></form></section>';

		self::render_upcoming_panel( $items, true );
		self::page_end();
	}

	/**
	 * Settings page.
	 *
	 * @return void
	 */
	public static function settings_page() {
		$settings = self::get_settings();

		self::page_start(
			'settings',
			__( 'Impostazioni', 'gest-web-rent' ),
			__( 'Contatti concessionario, messaggi predefiniti, colore primario e token GitHub opzionale.', 'gest-web-rent' )
		);
		self::admin_notice();

		echo '<form method="post" action="options.php" class="gwr-settings-form">';
		settings_fields( 'gwr_settings_group' );
		self::settings_sections( $settings );
		echo '<div class="gwr-form-submit"><button class="button button-primary button-hero">' . esc_html__( 'Salva impostazioni', 'gest-web-rent' ) . '</button></div></form>';
		self::page_end();
	}

	/**
	 * Handle vehicle save.
	 *
	 * @return void
	 */
	public static function handle_save_vehicle() {
		self::require_admin_capability();
		check_admin_referer( 'gwr_save_vehicle', 'gwr_vehicle_nonce' );

		$vehicle_id = isset( $_POST['vehicle_id'] ) ? absint( $_POST['vehicle_id'] ) : 0;
		$result = GWR_CPT::save_vehicle( $_POST['gwr_vehicle'] ?? array(), $vehicle_id );
		if ( is_wp_error( $result ) ) {
			self::redirect_with_message( self::vehicle_edit_url( $vehicle_id ), 'error', $result->get_error_message() );
		}

		$image_ids = isset( $_POST['gwr_images'] ) && is_array( $_POST['gwr_images'] ) ? wp_unslash( $_POST['gwr_images'] ) : array();
		$cover_id = isset( $_POST['gwr_cover_id'] ) ? absint( $_POST['gwr_cover_id'] ) : 0;
		GWR_CPT::save_vehicle_images( $result, $image_ids, $cover_id );

		$availability = isset( $_POST['gwr_availability'] ) && is_array( $_POST['gwr_availability'] ) ? wp_unslash( $_POST['gwr_availability'] ) : array();
		GWR_CPT::save_availability_rows( $result, $availability );

		self::redirect_with_message( self::vehicle_edit_url( $result ), 'updated', __( 'Veicolo salvato.', 'gest-web-rent' ) );
	}

	/**
	 * Handle delete vehicle.
	 *
	 * @return void
	 */
	public static function handle_delete_vehicle() {
		self::require_admin_capability();
		$vehicle_id = isset( $_GET['vehicle_id'] ) ? absint( $_GET['vehicle_id'] ) : 0;
		check_admin_referer( 'gwr_delete_vehicle_' . $vehicle_id );
		GWR_CPT::delete_vehicle( $vehicle_id );
		self::redirect_with_message( self::vehicles_url(), 'updated', __( 'Veicolo eliminato.', 'gest-web-rent' ) );
	}

	/**
	 * Handle duplicate vehicle.
	 *
	 * @return void
	 */
	public static function handle_duplicate_vehicle() {
		self::require_admin_capability();
		$vehicle_id = isset( $_GET['vehicle_id'] ) ? absint( $_GET['vehicle_id'] ) : 0;
		check_admin_referer( 'gwr_duplicate_vehicle_' . $vehicle_id );
		$new_id = GWR_CPT::duplicate_vehicle( $vehicle_id );
		self::redirect_with_message( self::vehicle_edit_url( $new_id ), 'updated', __( 'Veicolo duplicato in bozza.', 'gest-web-rent' ) );
	}

	/**
	 * Handle save availability from global page.
	 *
	 * @return void
	 */
	public static function handle_save_availability() {
		self::require_admin_capability();
		check_admin_referer( 'gwr_save_availability', 'gwr_availability_nonce' );
		$vehicle_id = isset( $_POST['vehicle_id'] ) ? absint( $_POST['vehicle_id'] ) : 0;
		$result = GWR_CPT::save_availability( $vehicle_id, $_POST['gwr_availability'] ?? array() );
		if ( is_wp_error( $result ) ) {
			self::redirect_with_message( admin_url( 'admin.php?page=gwr-availability' ), 'error', $result->get_error_message() );
		}
		self::redirect_with_message( admin_url( 'admin.php?page=gwr-availability' ), 'updated', __( 'Indisponibilita salvata.', 'gest-web-rent' ) );
	}

	/**
	 * Handle delete availability.
	 *
	 * @return void
	 */
	public static function handle_delete_availability() {
		self::require_admin_capability();
		$row_id = isset( $_GET['availability_id'] ) ? absint( $_GET['availability_id'] ) : 0;
		check_admin_referer( 'gwr_delete_availability_' . $row_id );
		GWR_CPT::delete_availability( $row_id );
		$redirect = isset( $_GET['redirect'] ) ? esc_url_raw( wp_unslash( $_GET['redirect'] ) ) : admin_url( 'admin.php?page=gwr-availability' );
		self::redirect_with_message( $redirect, 'updated', __( 'Indisponibilita eliminata.', 'gest-web-rent' ) );
	}

	/**
	 * Render vehicle form sections.
	 *
	 * @param array $vehicle Vehicle.
	 * @return void
	 */
	private static function vehicle_form_sections( $vehicle ) {
		echo '<div class="gwr-form-layout">';
		echo '<main class="gwr-form-main">';
		self::section_open( __( 'Dati principali', 'gest-web-rent' ), __( 'Identita pubblica del veicolo nel catalogo.', 'gest-web-rent' ) );
		echo '<div class="gwr-field-grid gwr-field-grid--triple">';
		self::field( 'title', __( 'Titolo veicolo', 'gest-web-rent' ), $vehicle['title'], 'text', 'BMW X1 xDrive' );
		self::field( 'brand', __( 'Marca', 'gest-web-rent' ), $vehicle['brand'], 'text', 'BMW' );
		self::field( 'model', __( 'Modello', 'gest-web-rent' ), $vehicle['model'], 'text', 'X1' );
		self::field( 'version', __( 'Versione', 'gest-web-rent' ), $vehicle['version'], 'text', 'xDrive 20d' );
		self::field( 'category', __( 'Categoria', 'gest-web-rent' ), $vehicle['category'], 'text', 'SUV' );
		self::field( 'year', __( 'Anno', 'gest-web-rent' ), $vehicle['year'], 'number', '2025' );
		self::field( 'fuel', __( 'Alimentazione', 'gest-web-rent' ), $vehicle['fuel'], 'text', 'Diesel' );
		self::field( 'transmission', __( 'Cambio', 'gest-web-rent' ), $vehicle['transmission'], 'text', 'Automatico' );
		self::field( 'seats', __( 'Posti', 'gest-web-rent' ), $vehicle['seats'], 'number', '5' );
		self::field( 'doors', __( 'Porte', 'gest-web-rent' ), $vehicle['doors'], 'number', '5' );
		self::field( 'color', __( 'Colore', 'gest-web-rent' ), $vehicle['color'], 'text', 'Grigio' );
		self::field( 'location', __( 'Sede', 'gest-web-rent' ), $vehicle['location'], 'text', 'Roma' );
		echo '</div>';
		self::section_close();

		self::section_open( __( 'Prezzi', 'gest-web-rent' ), __( 'Tariffe e cauzione mostrate nella card e nel modal.', 'gest-web-rent' ) );
		echo '<div class="gwr-field-grid gwr-field-grid--triple">';
		self::field( 'daily_price', __( 'Prezzo giornaliero', 'gest-web-rent' ), $vehicle['daily_price'], 'number', '79' );
		self::field( 'weekend_price', __( 'Prezzo weekend', 'gest-web-rent' ), $vehicle['weekend_price'], 'number', '199' );
		self::field( 'weekly_price', __( 'Prezzo settimanale', 'gest-web-rent' ), $vehicle['weekly_price'], 'number', '490' );
		self::field( 'monthly_price', __( 'Prezzo mensile', 'gest-web-rent' ), $vehicle['monthly_price'], 'number', '1490' );
		self::field( 'deposit', __( 'Cauzione', 'gest-web-rent' ), $vehicle['deposit'], 'number', '500' );
		echo '</div>';
		self::section_close();

		self::section_open( __( 'Regole noleggio', 'gest-web-rent' ), __( 'Limiti, patente e condizioni operative.', 'gest-web-rent' ) );
		echo '<div class="gwr-field-grid gwr-field-grid--triple">';
		self::field( 'included_km_daily', __( 'Km inclusi giornalieri', 'gest-web-rent' ), $vehicle['included_km_daily'], 'number', '150' );
		self::field( 'included_km_weekly', __( 'Km inclusi settimanali', 'gest-web-rent' ), $vehicle['included_km_weekly'], 'number', '900' );
		self::field( 'included_km_monthly', __( 'Km inclusi mensili', 'gest-web-rent' ), $vehicle['included_km_monthly'], 'number', '3000' );
		self::field( 'extra_km_price', __( 'Costo km extra', 'gest-web-rent' ), $vehicle['extra_km_price'], 'number', '0.25' );
		self::field( 'deductible', __( 'Franchigia', 'gest-web-rent' ), $vehicle['deductible'], 'text', '1000 euro' );
		self::field( 'min_driver_age', __( 'Eta minima conducente', 'gest-web-rent' ), $vehicle['min_driver_age'], 'number', '21' );
		self::field( 'min_license_years', __( 'Anni minimi patente', 'gest-web-rent' ), $vehicle['min_license_years'], 'number', '1' );
		self::field( 'required_license', __( 'Patente richiesta', 'gest-web-rent' ), $vehicle['required_license'], 'text', 'B' );
		self::field( 'min_rental_days', __( 'Durata minima noleggio', 'gest-web-rent' ), $vehicle['min_rental_days'], 'number', '1' );
		self::field( 'max_rental_days', __( 'Durata massima noleggio', 'gest-web-rent' ), $vehicle['max_rental_days'], 'number', '30' );
		echo '</div>';
		self::section_close();

		self::section_open( __( 'Servizi inclusi', 'gest-web-rent' ), __( 'Servizi evidenziati nel modal dettaglio.', 'gest-web-rent' ) );
		echo '<div class="gwr-check-grid">';
		self::checkbox( 'insurance_included', __( 'Assicurazione inclusa', 'gest-web-rent' ), $vehicle['insurance_included'] );
		self::checkbox( 'second_driver_included', __( 'Secondo conducente incluso', 'gest-web-rent' ), $vehicle['second_driver_included'] );
		self::checkbox( 'home_delivery', __( 'Consegna a domicilio', 'gest-web-rent' ), $vehicle['home_delivery'] );
		echo '</div>';
		self::section_close();

		self::section_open( __( 'Dotazioni', 'gest-web-rent' ), __( 'Seleziona le dotazioni mostrate con icone nel modal.', 'gest-web-rent' ) );
		$selected_features = GWR_CPT::vehicle_features( $vehicle );
		echo '<div class="gwr-check-grid">';
		foreach ( GWR_CPT::feature_options() as $key => $label ) {
			echo '<label class="gwr-check-card"><input type="checkbox" name="gwr_vehicle[features][]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $selected_features, true ), true, false ) . ' /> <span>' . esc_html( $label ) . '</span></label>';
		}
		echo '</div>';
		self::section_close();

		self::section_open( __( 'Descrizione', 'gest-web-rent' ), __( 'Testi pubblici della scheda in overlay.', 'gest-web-rent' ) );
		echo '<div class="gwr-field-grid">';
		self::textarea( 'description', __( 'Descrizione pubblica', 'gest-web-rent' ), $vehicle['description'] );
		self::textarea( 'rental_notes', __( 'Note noleggio', 'gest-web-rent' ), $vehicle['rental_notes'] );
		echo '</div>';
		self::section_close();
		echo '</main>';

		echo '<aside class="gwr-form-side">';
		self::section_open( __( 'Stato', 'gest-web-rent' ), __( 'Controlla la visibilita nel catalogo.', 'gest-web-rent' ) );
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Stato veicolo', 'gest-web-rent' ) . '</span><select name="gwr_vehicle[status]">';
		foreach ( GWR_CPT::vehicle_statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $vehicle['status'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
		self::checkbox( 'featured', __( 'Veicolo in evidenza', 'gest-web-rent' ), $vehicle['featured'] );
		self::field( 'sort_order', __( 'Ordinamento', 'gest-web-rent' ), $vehicle['sort_order'], 'number', '0' );
		self::section_close();
		echo '</aside>';
		echo '</div>';
	}

	/**
	 * Vehicle images section.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return void
	 */
	private static function vehicle_images_section( $vehicle_id ) {
		$images = $vehicle_id ? GWR_CPT::get_vehicle_images( $vehicle_id ) : array();
		self::section_open( __( 'Foto veicolo', 'gest-web-rent' ), __( 'Usa la Media Library WordPress. Puoi scegliere copertina, riordinare e rimuovere immagini.', 'gest-web-rent' ) );
		echo '<div class="gwr-media-manager" data-gwr-media-manager>';
		echo '<div class="gwr-media-list" data-gwr-media-list>';
		foreach ( $images as $image ) {
			$url = wp_get_attachment_image_url( absint( $image['attachment_id'] ), 'medium' );
			if ( ! $url ) {
				continue;
			}
			self::image_item( absint( $image['attachment_id'] ), $url, ! empty( $image['is_cover'] ) );
		}
		echo '</div>';
		echo '<button type="button" class="button button-secondary" data-gwr-add-images>' . esc_html__( 'Aggiungi foto', 'gest-web-rent' ) . '</button>';
		echo '</div>';
		self::section_close();
	}

	/**
	 * Vehicle availability section.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return void
	 */
	private static function vehicle_availability_section( $vehicle_id ) {
		$rows = $vehicle_id ? GWR_CPT::availability_rows( $vehicle_id ) : array();
		self::section_open( __( 'Indisponibilita', 'gest-web-rent' ), __( 'Aggiungi i periodi occupati, riservati, in manutenzione o non disponibili.', 'gest-web-rent' ) );
		echo '<div class="gwr-availability-editor" data-gwr-availability-editor><div class="gwr-availability-editor__rows" data-gwr-availability-rows>';
		if ( empty( $rows ) ) {
			self::availability_row( 0, array() );
		} else {
			foreach ( $rows as $index => $row ) {
				self::availability_row( $index, $row );
			}
		}
		echo '</div><button type="button" class="button button-secondary" data-gwr-add-availability>' . esc_html__( 'Aggiungi periodo', 'gest-web-rent' ) . '</button></div>';
		self::section_close();
	}

	/**
	 * Settings sections.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function settings_sections( $settings ) {
		self::section_open( __( 'Contatti concessionario', 'gest-web-rent' ), __( 'Usati dal catalogo e dal modal veicolo.', 'gest-web-rent' ) );
		echo '<div class="gwr-field-grid">';
		self::setting_field( 'dealer_name', __( 'Nome concessionario', 'gest-web-rent' ), $settings['dealer_name'] );
		self::setting_field( 'whatsapp_number', __( 'Numero WhatsApp Business', 'gest-web-rent' ), $settings['whatsapp_number'], 'text', '393331234567' );
		self::setting_field( 'contact_email', __( 'Email concessionario', 'gest-web-rent' ), $settings['contact_email'], 'email', 'noleggio@example.com' );
		self::setting_field( 'primary_color', __( 'Colore primario', 'gest-web-rent' ), $settings['primary_color'], 'color' );
		echo '</div>';
		self::section_close();

		self::section_open( __( 'Messaggi predefiniti', 'gest-web-rent' ), __( 'Placeholder: {vehicle_title}, {start_date}, {end_date}, {site_url}, {dealer_name}.', 'gest-web-rent' ) );
		echo '<div class="gwr-field-grid">';
		self::setting_textarea( 'whatsapp_message', __( 'Messaggio WhatsApp predefinito', 'gest-web-rent' ), $settings['whatsapp_message'] );
		self::setting_field( 'email_subject', __( 'Oggetto email predefinito', 'gest-web-rent' ), $settings['email_subject'] );
		self::setting_textarea( 'email_body', __( 'Corpo email predefinito', 'gest-web-rent' ), $settings['email_body'] );
		self::setting_textarea( 'privacy_note', __( 'Testo privacy / nota contatto', 'gest-web-rent' ), $settings['privacy_note'] );
		echo '</div>';
		self::section_close();

		self::section_open( __( 'Aggiornamenti GitHub', 'gest-web-rent' ), __( 'Per repository pubbliche non serve token. Per repository private usa un token lato server.', 'gest-web-rent' ) );
		self::github_token_field( $settings );
		self::section_close();
	}

	/**
	 * Shared page start.
	 *
	 * @param string $current Current item.
	 * @param string $title Title.
	 * @param string $description Description.
	 * @param array  $actions Actions.
	 * @return void
	 */
	private static function page_start( $current, $title, $description, $actions = array() ) {
		echo '<div class="wrap gwr-admin-wrap"><div class="gwr-admin-shell">';
		self::nav( $current );
		echo '<section class="gwr-admin-stage"><header class="gwr-page-header"><div class="gwr-page-header__copy"><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $description ) . '</p><div class="gwr-page-badges"><span class="gwr-page-badge">v' . esc_html( GWR_VERSION ) . '</span><span class="gwr-page-badge">' . esc_html__( 'Catalogo unico', 'gest-web-rent' ) . '</span></div></div>';
		if ( ! empty( $actions ) ) {
			echo '<div class="gwr-page-actions">';
			foreach ( $actions as $action ) {
				$class = ! empty( $action['primary'] ) ? 'button button-primary' : 'button button-secondary';
				echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $action['url'] ) . '">' . esc_html( $action['label'] ) . '</a>';
			}
			echo '</div>';
		}
		echo '</header>';
	}

	/**
	 * Page end.
	 *
	 * @return void
	 */
	private static function page_end() {
		echo '</section></div></div>';
	}

	/**
	 * Admin nav.
	 *
	 * @param string $current Current.
	 * @return void
	 */
	private static function nav( $current ) {
		$items = array(
			'dashboard'    => array( 'label' => __( 'Dashboard', 'gest-web-rent' ), 'url' => admin_url( 'admin.php?page=gest-web-rent' ) ),
			'vehicles'     => array( 'label' => __( 'Veicoli', 'gest-web-rent' ), 'url' => self::vehicles_url() ),
			'add'          => array( 'label' => __( 'Aggiungi veicolo', 'gest-web-rent' ), 'url' => self::vehicle_edit_url() ),
			'availability' => array( 'label' => __( 'Disponibilita', 'gest-web-rent' ), 'url' => admin_url( 'admin.php?page=gwr-availability' ) ),
			'settings'     => array( 'label' => __( 'Impostazioni', 'gest-web-rent' ), 'url' => admin_url( 'admin.php?page=gwr-settings' ) ),
		);

		echo '<nav class="gwr-admin-nav"><div class="gwr-admin-nav__brand"><div class="gwr-admin-nav__logo">Gest<span>Web</span>Rent</div><span class="gwr-admin-nav__caption">' . esc_html__( 'Rental suite', 'gest-web-rent' ) . '</span></div><div class="gwr-admin-nav__links">';
		foreach ( $items as $key => $item ) {
			echo '<a class="gwr-admin-nav__link' . esc_attr( $key === $current ? ' is-active' : '' ) . '" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
		}
		echo '</div></nav>';
	}

	/**
	 * Render a vehicle table row.
	 *
	 * @param array $vehicle Vehicle.
	 * @return void
	 */
	private static function vehicle_row( $vehicle ) {
		$cover = GWR_CPT::cover_image_url( $vehicle['id'] );
		$availability = GWR_CPT::upcoming_availability( $vehicle['id'], 1 );
		$edit_url = self::vehicle_edit_url( $vehicle['id'] );
		$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=gwr_delete_vehicle&vehicle_id=' . absint( $vehicle['id'] ) ), 'gwr_delete_vehicle_' . absint( $vehicle['id'] ) );
		$duplicate_url = wp_nonce_url( admin_url( 'admin-post.php?action=gwr_duplicate_vehicle&vehicle_id=' . absint( $vehicle['id'] ) ), 'gwr_duplicate_vehicle_' . absint( $vehicle['id'] ) );

		echo '<tr>';
		echo '<td><div class="gwr-table-cover">' . ( $cover ? '<img src="' . esc_url( $cover ) . '" alt="" />' : '<span>' . esc_html__( 'Foto', 'gest-web-rent' ) . '</span>' ) . '</div></td>';
		echo '<td><strong>' . esc_html( $vehicle['title'] ) . '</strong>' . ( ! empty( $vehicle['featured'] ) ? '<span class="gwr-mini-badge">' . esc_html__( 'In evidenza', 'gest-web-rent' ) . '</span>' : '' ) . '</td>';
		echo '<td>' . esc_html( trim( $vehicle['brand'] . ' ' . $vehicle['model'] ) ) . '</td>';
		echo '<td>' . esc_html( GWR_CPT::format_price( $vehicle['daily_price'] ) ) . '</td>';
		echo '<td>' . esc_html( $vehicle['included_km_daily'] ? $vehicle['included_km_daily'] . ' km/giorno' : '-' ) . '</td>';
		echo '<td><span class="gwr-inline-status is-' . esc_attr( $vehicle['status'] ) . '">' . esc_html( GWR_CPT::vehicle_statuses()[ $vehicle['status'] ] ?? $vehicle['status'] ) . '</span></td>';
		echo '<td>' . ( $availability ? esc_html( $availability[0]['start_date'] . ' -> ' . $availability[0]['end_date'] ) : esc_html__( 'Nessun blocco futuro', 'gest-web-rent' ) ) . '</td>';
		echo '<td><div class="gwr-row-actions"><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Modifica', 'gest-web-rent' ) . '</a><a href="' . esc_url( $duplicate_url ) . '">' . esc_html__( 'Duplica', 'gest-web-rent' ) . '</a><a href="' . esc_url( $edit_url . '#gwr-availability-section' ) . '">' . esc_html__( 'Disponibilita', 'gest-web-rent' ) . '</a><a class="is-danger" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Eliminare questo veicolo?', 'gest-web-rent' ) ) . '\')">' . esc_html__( 'Elimina', 'gest-web-rent' ) . '</a></div></td>';
		echo '</tr>';
	}

	/**
	 * Open admin section.
	 *
	 * @param string $title Title.
	 * @param string $description Description.
	 * @return void
	 */
	private static function section_open( $title, $description = '' ) {
		$id = 'Indisponibilita' === $title ? ' id="gwr-availability-section"' : '';
		echo '<section' . $id . ' class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><div><h2>' . esc_html( $title ) . '</h2>';
		if ( $description ) {
			echo '<p>' . esc_html( $description ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Close admin section.
	 *
	 * @return void
	 */
	private static function section_close() {
		echo '</section>';
	}

	/**
	 * Render field.
	 *
	 * @param string $key Key.
	 * @param string $label Label.
	 * @param mixed  $value Value.
	 * @param string $type Type.
	 * @param string $placeholder Placeholder.
	 * @return void
	 */
	private static function field( $key, $label, $value, $type = 'text', $placeholder = '' ) {
		$step = 'number' === $type ? ' step="any"' : '';
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="gwr_vehicle[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $step . ' /></label>';
	}

	/**
	 * Render textarea.
	 *
	 * @param string $key Key.
	 * @param string $label Label.
	 * @param mixed  $value Value.
	 * @return void
	 */
	private static function textarea( $key, $label, $value ) {
		echo '<label class="gwr-field gwr-field--wide"><span class="gwr-field__label">' . esc_html( $label ) . '</span><textarea name="gwr_vehicle[' . esc_attr( $key ) . ']" rows="6">' . esc_textarea( $value ) . '</textarea></label>';
	}

	/**
	 * Render checkbox.
	 *
	 * @param string $key Key.
	 * @param string $label Label.
	 * @param mixed  $checked Checked.
	 * @return void
	 */
	private static function checkbox( $key, $label, $checked ) {
		echo '<label class="gwr-check-card"><input type="checkbox" name="gwr_vehicle[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $checked ), true, false ) . ' /> <span>' . esc_html( $label ) . '</span></label>';
	}

	/**
	 * Render image item.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $url Image URL.
	 * @param bool   $is_cover Is cover.
	 * @return void
	 */
	private static function image_item( $attachment_id, $url, $is_cover = false ) {
		echo '<div class="gwr-media-item" data-gwr-image-id="' . esc_attr( $attachment_id ) . '"><img src="' . esc_url( $url ) . '" alt="" /><input type="hidden" name="gwr_images[]" value="' . esc_attr( $attachment_id ) . '" /><label><input type="radio" name="gwr_cover_id" value="' . esc_attr( $attachment_id ) . '" ' . checked( $is_cover, true, false ) . ' /> ' . esc_html__( 'Copertina', 'gest-web-rent' ) . '</label><div class="gwr-media-item__actions"><button type="button" class="button-link" data-gwr-move-image="up">' . esc_html__( 'Su', 'gest-web-rent' ) . '</button><button type="button" class="button-link" data-gwr-move-image="down">' . esc_html__( 'Giu', 'gest-web-rent' ) . '</button><button type="button" class="button-link-delete" data-gwr-remove-image>' . esc_html__( 'Rimuovi', 'gest-web-rent' ) . '</button></div></div>';
	}

	/**
	 * Render one availability row.
	 *
	 * @param int   $index Index.
	 * @param array $row Row.
	 * @return void
	 */
	private static function availability_row( $index, $row ) {
		$row = wp_parse_args(
			(array) $row,
			array(
				'start_date'         => '',
				'end_date'           => '',
				'status'             => 'busy',
				'internal_note'      => '',
				'external_reference' => '',
			)
		);

		echo '<div class="gwr-availability-row" data-gwr-availability-row>';
		echo '<label><span>' . esc_html__( 'Data inizio', 'gest-web-rent' ) . '</span><input type="date" name="gwr_availability[' . esc_attr( $index ) . '][start_date]" value="' . esc_attr( $row['start_date'] ) . '" /></label>';
		echo '<label><span>' . esc_html__( 'Data fine', 'gest-web-rent' ) . '</span><input type="date" name="gwr_availability[' . esc_attr( $index ) . '][end_date]" value="' . esc_attr( $row['end_date'] ) . '" /></label>';
		echo '<label><span>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</span><select name="gwr_availability[' . esc_attr( $index ) . '][status]">';
		foreach ( GWR_CPT::availability_statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $row['status'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
		echo '<label><span>' . esc_html__( 'Riferimento esterno', 'gest-web-rent' ) . '</span><input type="text" name="gwr_availability[' . esc_attr( $index ) . '][external_reference]" value="' . esc_attr( $row['external_reference'] ) . '" /></label>';
		echo '<label class="gwr-availability-row__note"><span>' . esc_html__( 'Nota interna', 'gest-web-rent' ) . '</span><input type="text" name="gwr_availability[' . esc_attr( $index ) . '][internal_note]" value="' . esc_attr( $row['internal_note'] ) . '" /></label>';
		echo '<button type="button" class="button-link-delete" data-gwr-remove-availability>' . esc_html__( 'Rimuovi', 'gest-web-rent' ) . '</button></div>';
	}

	/**
	 * Render availability inputs for global form.
	 *
	 * @return void
	 */
	private static function availability_inputs() {
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Data inizio', 'gest-web-rent' ) . '</span><input type="date" name="gwr_availability[start_date]" required /></label>';
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Data fine', 'gest-web-rent' ) . '</span><input type="date" name="gwr_availability[end_date]" required /></label>';
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Stato', 'gest-web-rent' ) . '</span><select name="gwr_availability[status]">';
		foreach ( GWR_CPT::availability_statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Riferimento esterno', 'gest-web-rent' ) . '</span><input type="text" name="gwr_availability[external_reference]" /></label>';
		echo '<label class="gwr-field gwr-field--wide"><span class="gwr-field__label">' . esc_html__( 'Nota interna', 'gest-web-rent' ) . '</span><input type="text" name="gwr_availability[internal_note]" /></label>';
	}

	/**
	 * Render upcoming panel.
	 *
	 * @param array $items Rows.
	 * @param bool  $full Full table.
	 * @return void
	 */
	private static function render_upcoming_panel( $items, $full = false ) {
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><div><h2>' . esc_html__( 'Indisponibilita future', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Questi periodi vengono esclusi dal catalogo quando il cliente filtra le date.', 'gest-web-rent' ) . '</p></div></div>';
		echo '<div class="gwr-table-wrap"><table class="widefat"><thead><tr><th>' . esc_html__( 'Veicolo', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Periodo', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Riferimento', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Azioni', 'gest-web-rent' ) . '</th></tr></thead><tbody>';
		if ( empty( $items ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Nessuna indisponibilita futura.', 'gest-web-rent' ) . '</td></tr>';
		} else {
			foreach ( $items as $item ) {
				$edit_url = self::vehicle_edit_url( $item['vehicle_id'] ) . '#gwr-availability-section';
				$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=gwr_delete_availability&availability_id=' . absint( $item['id'] ) . '&redirect=' . rawurlencode( $full ? admin_url( 'admin.php?page=gwr-availability' ) : admin_url( 'admin.php?page=gest-web-rent' ) ) ), 'gwr_delete_availability_' . absint( $item['id'] ) );
				echo '<tr><td><strong>' . esc_html( $item['vehicle_title'] ) . '</strong></td><td>' . esc_html( $item['start_date'] . ' -> ' . $item['end_date'] ) . '</td><td><span class="gwr-inline-status is-' . esc_attr( $item['status'] ) . '">' . esc_html( GWR_CPT::availability_statuses()[ $item['status'] ] ?? $item['status'] ) . '</span></td><td>' . esc_html( $item['external_reference'] ?: $item['internal_note'] ) . '</td><td><div class="gwr-row-actions"><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Modifica', 'gest-web-rent' ) . '</a><a class="is-danger" href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Elimina', 'gest-web-rent' ) . '</a></div></td></tr>';
			}
		}
		echo '</tbody></table></div></section>';
	}

	/**
	 * Quick dashboard settings.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function render_quick_settings( $settings ) {
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><div><h2>' . esc_html__( 'Configurazione rapida', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Gli stessi campi sono disponibili anche in Impostazioni.', 'gest-web-rent' ) . '</p></div></div>';
		echo '<form method="post" action="options.php" class="gwr-settings-form">';
		settings_fields( 'gwr_settings_group' );
		echo '<div class="gwr-field-grid">';
		self::setting_field( 'dealer_name', __( 'Nome concessionario', 'gest-web-rent' ), $settings['dealer_name'] );
		self::setting_field( 'whatsapp_number', __( 'WhatsApp Business', 'gest-web-rent' ), $settings['whatsapp_number'] );
		self::setting_field( 'contact_email', __( 'Email concessionario', 'gest-web-rent' ), $settings['contact_email'], 'email' );
		self::setting_field( 'primary_color', __( 'Colore primario', 'gest-web-rent' ), $settings['primary_color'], 'color' );
		echo '</div><div class="gwr-form-submit"><button class="button button-primary">' . esc_html__( 'Salva configurazione', 'gest-web-rent' ) . '</button></div></form></section>';
	}

	/**
	 * Metric card.
	 *
	 * @param string $title Title.
	 * @param mixed  $value Value.
	 * @param string $desc Description.
	 * @return void
	 */
	private static function metric_card( $title, $value, $desc ) {
		echo '<article class="gwr-kpi-card"><p class="gwr-kpi-card__title">' . esc_html( $title ) . '</p><p class="gwr-kpi-card__value">' . esc_html( (string) $value ) . '</p><p class="gwr-kpi-card__desc">' . esc_html( $desc ) . '</p></article>';
	}

	/**
	 * Status line.
	 *
	 * @param string $label Label.
	 * @param bool   $ok OK.
	 * @return void
	 */
	private static function status_line( $label, $ok ) {
		echo '<div class="gwr-status-line"><span>' . esc_html( $label ) . '</span><strong class="' . esc_attr( $ok ? 'is-ok' : 'is-missing' ) . '">' . esc_html( $ok ? __( 'Configurato', 'gest-web-rent' ) : __( 'Da configurare', 'gest-web-rent' ) ) . '</strong></div>';
	}

	/**
	 * Setting field.
	 *
	 * @param string $key Key.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $type Type.
	 * @param string $placeholder Placeholder.
	 * @return void
	 */
	private static function setting_field( $key, $label, $value, $type = 'text', $placeholder = '' ) {
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" /></label>';
	}

	/**
	 * Setting textarea.
	 *
	 * @param string $key Key.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	private static function setting_textarea( $key, $label, $value ) {
		echo '<label class="gwr-field gwr-field--wide"><span class="gwr-field__label">' . esc_html( $label ) . '</span><textarea name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $key ) . ']" rows="5">' . esc_textarea( $value ) . '</textarea></label>';
	}

	/**
	 * GitHub token field.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function github_token_field( $settings ) {
		$has_token = ! empty( $settings['github_access_token'] );
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'GitHub Access Token', 'gest-web-rent' ) . '</span><input type="password" name="' . esc_attr( self::OPTION_NAME ) . '[github_access_token]" value="" autocomplete="new-password" placeholder="' . esc_attr( $has_token ? __( 'Lascia vuoto per mantenere il token', 'gest-web-rent' ) : __( 'Token opzionale', 'gest-web-rent' ) ) . '" /><span class="gwr-field__description">' . esc_html__( 'Usato solo lato server per repository private. Non appare mai nel frontend.', 'gest-web-rent' ) . '</span></label>';
		if ( $has_token ) {
			echo '<label class="gwr-check-card"><input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[github_access_token_clear]" value="1" /> <span>' . esc_html__( 'Rimuovi token salvato', 'gest-web-rent' ) . '</span></label>';
		}
	}

	/**
	 * Admin notice from redirects.
	 *
	 * @return void
	 */
	private static function admin_notice() {
		if ( empty( $_GET['gwr_message'] ) ) {
			return;
		}

		$type = isset( $_GET['gwr_notice'] ) && 'error' === $_GET['gwr_notice'] ? 'notice-error' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $type ) . ' inline"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['gwr_message'] ) ) ) . '</p></div>';
	}

	/**
	 * Redirect with message.
	 *
	 * @param string $url URL.
	 * @param string $notice Notice type.
	 * @param string $message Message.
	 * @return void
	 */
	private static function redirect_with_message( $url, $notice, $message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'gwr_notice'  => sanitize_key( $notice ),
					'gwr_message' => rawurlencode( $message ),
				),
				$url
			)
		);
		exit;
	}

	/**
	 * Require capability.
	 *
	 * @return void
	 */
	private static function require_admin_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) );
		}
	}

	/**
	 * Vehicles URL.
	 *
	 * @return string
	 */
	private static function vehicles_url() {
		return admin_url( 'admin.php?page=gwr-vehicles' );
	}

	/**
	 * Vehicle edit URL.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return string
	 */
	private static function vehicle_edit_url( $vehicle_id = 0 ) {
		$url = admin_url( 'admin.php?page=gwr-vehicle-edit' );
		if ( $vehicle_id ) {
			$url = add_query_arg( 'vehicle_id', absint( $vehicle_id ), $url );
		}

		return $url;
	}
}
