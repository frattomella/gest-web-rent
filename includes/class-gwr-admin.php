<?php
/**
 * Admin dashboard and settings.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gest Web Rent admin UI.
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
	}

	/**
	 * Default plugin settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'dealer_name'         => get_bloginfo( 'name' ),
			'whatsapp_number'     => '',
			'contact_email'       => get_option( 'admin_email' ),
			'catalog_page_id'     => 0,
			'primary_color'       => '#113a7d',
			'accent_color'        => '#37a83a',
			'button_label'        => __( 'Richiedi disponibilita', 'gest-web-rent' ),
			'whatsapp_message'    => 'Ciao, vorrei informazioni sul noleggio del veicolo {vehicle_title}. Date di interesse: {start_date} - {end_date}. Link: {vehicle_url}',
			'email_subject'       => 'Richiesta noleggio: {vehicle_title}',
			'email_body'          => "Ciao,\nvorrei informazioni sul noleggio del veicolo {vehicle_title}.\nDate di interesse: {start_date} - {end_date}.\nLink: {vehicle_url}",
			'privacy_note'        => __( 'La disponibilita mostrata e indicativa e deve essere confermata dal concessionario.', 'gest-web-rent' ),
			'github_access_token' => '',
		);
	}

	/**
	 * Read settings with defaults.
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
	 * Register admin menu.
	 *
	 * @return void
	 */
	public static function admin_menu() {
		add_menu_page( 'Gest Web Rent', 'Gest Web Rent', 'manage_options', 'gest-web-rent', array( __CLASS__, 'dashboard_page' ), 'dashicons-car', 26 );
		add_submenu_page( 'gest-web-rent', __( 'Dashboard', 'gest-web-rent' ), __( 'Dashboard', 'gest-web-rent' ), 'manage_options', 'gest-web-rent', array( __CLASS__, 'dashboard_page' ) );
		add_submenu_page( 'gest-web-rent', __( 'Veicoli', 'gest-web-rent' ), __( 'Veicoli', 'gest-web-rent' ), 'edit_posts', 'edit.php?post_type=' . GWR_CPT::POST_TYPE );
		add_submenu_page( 'gest-web-rent', __( 'Aggiungi veicolo', 'gest-web-rent' ), __( 'Aggiungi veicolo', 'gest-web-rent' ), 'edit_posts', 'post-new.php?post_type=' . GWR_CPT::POST_TYPE );
		add_submenu_page( 'gest-web-rent', __( 'Disponibilita', 'gest-web-rent' ), __( 'Disponibilita', 'gest-web-rent' ), 'edit_posts', 'gwr-availability', array( __CLASS__, 'availability_page' ) );
		add_submenu_page( 'gest-web-rent', __( 'Impostazioni', 'gest-web-rent' ), __( 'Impostazioni', 'gest-web-rent' ), 'manage_options', 'gwr-settings', array( __CLASS__, 'settings_page' ) );
		add_submenu_page( 'gest-web-rent', __( 'Guida rapida', 'gest-web-rent' ), __( 'Guida rapida', 'gest-web-rent' ), 'manage_options', 'gwr-guide', array( __CLASS__, 'guide_page' ) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook ) {
		$screen     = get_current_screen();
		$is_vehicle = $screen && GWR_CPT::POST_TYPE === $screen->post_type;
		$is_gwr     = false !== strpos( (string) $hook, 'gest-web-rent' ) || false !== strpos( (string) $hook, 'gwr-' );

		if ( ! $is_vehicle && ! $is_gwr ) {
			return;
		}

		wp_enqueue_style( 'gwr-admin', GWR_PLUGIN_URL . 'admin/assets/gwr-admin.css', array(), gwr_asset_version( 'admin/assets/gwr-admin.css' ) );
		wp_enqueue_script( 'gwr-admin', GWR_PLUGIN_URL . 'admin/assets/gwr-admin.js', array(), gwr_asset_version( 'admin/assets/gwr-admin.js' ), true );
		wp_localize_script(
			'gwr-admin',
			'gwrAdmin',
			array(
				'availabilityIndex' => time(),
				'i18n'              => array(
					'remove' => __( 'Rimuovi', 'gest-web-rent' ),
				),
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
	 * @param array $input Submitted values.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$existing = self::get_settings();

		$output = array(
			'dealer_name'         => isset( $input['dealer_name'] ) ? sanitize_text_field( wp_unslash( $input['dealer_name'] ) ) : '',
			'whatsapp_number'     => isset( $input['whatsapp_number'] ) ? preg_replace( '/[^0-9+]/', '', sanitize_text_field( wp_unslash( $input['whatsapp_number'] ) ) ) : '',
			'contact_email'       => isset( $input['contact_email'] ) ? sanitize_email( wp_unslash( $input['contact_email'] ) ) : '',
			'catalog_page_id'     => isset( $input['catalog_page_id'] ) ? absint( $input['catalog_page_id'] ) : 0,
			'primary_color'       => isset( $input['primary_color'] ) ? sanitize_hex_color( wp_unslash( $input['primary_color'] ) ) : '#113a7d',
			'accent_color'        => isset( $input['accent_color'] ) ? sanitize_hex_color( wp_unslash( $input['accent_color'] ) ) : '#37a83a',
			'button_label'        => isset( $input['button_label'] ) ? sanitize_text_field( wp_unslash( $input['button_label'] ) ) : '',
			'whatsapp_message'    => isset( $input['whatsapp_message'] ) ? sanitize_textarea_field( wp_unslash( $input['whatsapp_message'] ) ) : '',
			'email_subject'       => isset( $input['email_subject'] ) ? sanitize_text_field( wp_unslash( $input['email_subject'] ) ) : '',
			'email_body'          => isset( $input['email_body'] ) ? sanitize_textarea_field( wp_unslash( $input['email_body'] ) ) : '',
			'privacy_note'        => isset( $input['privacy_note'] ) ? sanitize_textarea_field( wp_unslash( $input['privacy_note'] ) ) : '',
			'github_access_token' => isset( $existing['github_access_token'] ) ? $existing['github_access_token'] : '',
		);

		if ( ! empty( $input['github_access_token_clear'] ) ) {
			$output['github_access_token'] = '';
		} elseif ( ! empty( $input['github_access_token'] ) ) {
			$output['github_access_token'] = sanitize_text_field( wp_unslash( $input['github_access_token'] ) );
		}

		delete_site_transient( 'gwr_github_release' );

		return array_replace_recursive( self::default_settings(), $output );
	}

	/**
	 * Render dashboard.
	 *
	 * @return void
	 */
	public static function dashboard_page() {
		$counts    = wp_count_posts( GWR_CPT::POST_TYPE );
		$published = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$drafts    = isset( $counts->draft ) ? (int) $counts->draft : 0;
		$upcoming  = GWR_CPT::upcoming_availability( null, 50 );
		$settings  = self::get_settings();

		self::render_page_start(
			'dashboard',
			__( 'Gest Web Rent dashboard', 'gest-web-rent' ),
			__( 'Workspace per gestire parco noleggio, disponibilita, componenti frontend e contatti commerciali con lo stesso stile operativo di GestPark Online.', 'gest-web-rent' ),
			array(
				array( 'label' => __( 'Aggiungi veicolo', 'gest-web-rent' ), 'url' => admin_url( 'post-new.php?post_type=' . GWR_CPT::POST_TYPE ), 'primary' => true ),
				array( 'label' => __( 'Configura contatti', 'gest-web-rent' ), 'url' => admin_url( 'admin.php?page=gwr-settings' ) ),
			),
			array(
				'GitHub Releases',
				'v' . GWR_VERSION,
			)
		);

		echo '<div class="gwr-kpi-grid">';
		self::metric_card( __( 'Veicoli pubblicati', 'gest-web-rent' ), $published, __( 'Visibili nel catalogo frontend', 'gest-web-rent' ) );
		self::metric_card( __( 'Bozze veicolo', 'gest-web-rent' ), $drafts, __( 'Schede ancora da completare', 'gest-web-rent' ) );
		self::metric_card( __( 'Impegni futuri', 'gest-web-rent' ), count( $upcoming ), __( 'Prenotazioni, blocchi o manutenzioni', 'gest-web-rent' ) );
		self::metric_card( __( 'Contatti configurati', 'gest-web-rent' ), ( ! empty( $settings['whatsapp_number'] ) || ! empty( $settings['contact_email'] ) ) ? __( 'Si', 'gest-web-rent' ) : __( 'No', 'gest-web-rent' ), __( 'WhatsApp Business ed email richieste', 'gest-web-rent' ) );
		echo '</div>';

		echo '<div class="gwr-surface-grid">';
		echo '<section class="gwr-surface gwr-surface--accent">';
		echo '<span class="gwr-surface__eyebrow">' . esc_html__( 'Flusso operativo', 'gest-web-rent' ) . '</span>';
		echo '<h2>' . esc_html__( 'Dalla scheda veicolo alla richiesta cliente', 'gest-web-rent' ) . '</h2>';
		echo '<ol class="gwr-flow-list">';
		echo '<li>' . esc_html__( 'Inserisci foto, dati tecnici e condizioni di noleggio.', 'gest-web-rent' ) . '</li>';
		echo '<li>' . esc_html__( 'Blocca i giorni impegnati nella metabox disponibilita.', 'gest-web-rent' ) . '</li>';
		echo '<li>' . esc_html__( 'Pubblica catalogo, calendario disponibilita e scheda veicolo con i blocchi Gutenberg.', 'gest-web-rent' ) . '</li>';
		echo '<li>' . esc_html__( 'Ricevi contatti su WhatsApp Business o email.', 'gest-web-rent' ) . '</li>';
		echo '</ol>';
		echo '<div class="gwr-inline-actions"><a class="button button-primary" href="' . esc_url( admin_url( 'edit.php?post_type=' . GWR_CPT::POST_TYPE ) ) . '">' . esc_html__( 'Gestisci veicoli', 'gest-web-rent' ) . '</a><a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=gwr-availability' ) ) . '">' . esc_html__( 'Vedi disponibilita', 'gest-web-rent' ) . '</a></div>';
		echo '</section>';

		echo '<section class="gwr-surface">';
		echo '<span class="gwr-surface__eyebrow">' . esc_html__( 'Componenti editor', 'gest-web-rent' ) . '</span>';
		echo '<h2>' . esc_html__( 'Blocchi pronti per pagine WordPress', 'gest-web-rent' ) . '</h2>';
		echo '<ul class="gwr-inline-code-list">';
		echo '<li><code>Gest Web Rent / Catalogo veicoli</code></li>';
		echo '<li><code>Gest Web Rent / Calendario disponibilita</code></li>';
		echo '<li><code>Gest Web Rent / Scheda veicolo</code></li>';
		echo '</ul>';
		echo '<p>' . esc_html__( 'Gli stessi componenti sono disponibili anche come shortcode per Elementor, builder o template custom.', 'gest-web-rent' ) . '</p>';
		echo '</section>';
		echo '</div>';

		self::render_dashboard_settings_form( $settings );
		self::render_upcoming_panel( $upcoming );
		self::render_page_end();
	}

	/**
	 * Render availability admin page.
	 *
	 * @return void
	 */
	public static function availability_page() {
		$items = GWR_CPT::upcoming_availability( null, 200 );

		self::render_page_start(
			'availability',
			__( 'Disponibilita veicoli', 'gest-web-rent' ),
			__( 'Vista globale dei periodi in cui ogni veicolo e impegnato, in manutenzione o non disponibile. Per inserire o modificare date apri la scheda del singolo veicolo.', 'gest-web-rent' ),
			array(
				array( 'label' => __( 'Nuovo veicolo', 'gest-web-rent' ), 'url' => admin_url( 'post-new.php?post_type=' . GWR_CPT::POST_TYPE ), 'primary' => true ),
				array( 'label' => __( 'Archivio veicoli', 'gest-web-rent' ), 'url' => admin_url( 'edit.php?post_type=' . GWR_CPT::POST_TYPE ) ),
			)
		);

		self::render_upcoming_panel( $items, true );
		self::render_page_end();
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public static function settings_page() {
		$settings = self::get_settings();

		self::render_page_start(
			'settings',
			__( 'Impostazioni Gest Web Rent', 'gest-web-rent' ),
			__( 'Imposta identita concessionario, box contatto, colori principali e aggiornamenti GitHub. Il token non viene mai mostrato nel frontend.', 'gest-web-rent' ),
			array()
		);

		echo '<form method="post" action="options.php" class="gwr-settings-form">';
		settings_fields( 'gwr_settings_group' );

		echo '<details class="gwr-admin-accordion" open>';
		echo '<summary class="gwr-admin-accordion__summary"><div class="gwr-admin-accordion__copy"><h2>' . esc_html__( 'Contatti e identita', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Dati usati nelle card, nella scheda veicolo e nel box richiesta.', 'gest-web-rent' ) . '</p></div></summary>';
		echo '<div class="gwr-admin-accordion__content"><div class="gwr-field-grid">';
		self::setting_field( 'dealer_name', __( 'Nome concessionario', 'gest-web-rent' ), $settings['dealer_name'], 'text', __( 'Gest Web Rent', 'gest-web-rent' ) );
		self::setting_field( 'whatsapp_number', __( 'Numero WhatsApp Business', 'gest-web-rent' ), $settings['whatsapp_number'], 'text', '+393331234567' );
		self::setting_field( 'contact_email', __( 'Email richieste', 'gest-web-rent' ), $settings['contact_email'], 'email', 'noleggio@example.com' );
		self::page_select_field( 'catalog_page_id', __( 'Pagina catalogo', 'gest-web-rent' ), (int) $settings['catalog_page_id'] );
		echo '</div></div></details>';

		echo '<details class="gwr-admin-accordion" open>';
		echo '<summary class="gwr-admin-accordion__summary"><div class="gwr-admin-accordion__copy"><h2>' . esc_html__( 'Stile componenti', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Palette coerente con GestPark Online, personalizzabile se il sito richiede piccoli aggiustamenti.', 'gest-web-rent' ) . '</p></div></summary>';
		echo '<div class="gwr-admin-accordion__content"><div class="gwr-field-grid">';
		self::setting_field( 'primary_color', __( 'Colore primario', 'gest-web-rent' ), $settings['primary_color'], 'color' );
		self::setting_field( 'accent_color', __( 'Colore accento', 'gest-web-rent' ), $settings['accent_color'], 'color' );
		self::setting_field( 'button_label', __( 'Etichetta CTA', 'gest-web-rent' ), $settings['button_label'], 'text', __( 'Richiedi disponibilita', 'gest-web-rent' ) );
		self::setting_textarea( 'privacy_note', __( 'Testo privacy / nota contatto', 'gest-web-rent' ), $settings['privacy_note'] );
		echo '</div></div></details>';

		echo '<details class="gwr-admin-accordion" open>';
		echo '<summary class="gwr-admin-accordion__summary"><div class="gwr-admin-accordion__copy"><h2>' . esc_html__( 'Messaggi predefiniti', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Placeholder disponibili: {vehicle_title}, {brand}, {model}, {version}, {daily_price}, {weekly_price}, {monthly_price}, {start_date}, {end_date}, {vehicle_url}, {site_name}.', 'gest-web-rent' ) . '</p></div></summary>';
		echo '<div class="gwr-admin-accordion__content"><div class="gwr-field-grid">';
		self::setting_textarea( 'whatsapp_message', __( 'Messaggio WhatsApp predefinito', 'gest-web-rent' ), $settings['whatsapp_message'] );
		self::setting_field( 'email_subject', __( 'Oggetto email predefinito', 'gest-web-rent' ), $settings['email_subject'], 'text' );
		self::setting_textarea( 'email_body', __( 'Corpo email predefinito', 'gest-web-rent' ), $settings['email_body'] );
		echo '</div></div></details>';

		echo '<details class="gwr-admin-accordion">';
		echo '<summary class="gwr-admin-accordion__summary"><div class="gwr-admin-accordion__copy"><h2>' . esc_html__( 'Aggiornamenti GitHub', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Per repository pubbliche non serve token. Per repository private inserisci un token con accesso in lettura alle release.', 'gest-web-rent' ) . '</p></div></summary>';
		echo '<div class="gwr-admin-accordion__content"><div class="gwr-field-grid">';
		self::github_token_field( $settings );
		echo '</div></div></details>';

		echo '<div class="gwr-form-submit">';
		submit_button( __( 'Salva configurazione', 'gest-web-rent' ), 'primary', 'submit', false );
		echo '</div>';
		echo '</form>';

		self::render_page_end();
	}

	/**
	 * Render guide page.
	 *
	 * @return void
	 */
	public static function guide_page() {
		self::render_page_start(
			'guide',
			__( 'Guida rapida', 'gest-web-rent' ),
			__( 'Shortcode, blocchi e regole operative per pubblicare il catalogo noleggio.', 'gest-web-rent' )
		);

		echo '<div class="gwr-surface-grid">';
		self::guide_card( __( 'Catalogo veicoli', 'gest-web-rent' ), '[gwr_catalog]', __( 'Mostra card veicoli con filtri per date, marca, posti e prezzo. Alias: [gest_web_rent_catalog].', 'gest-web-rent' ) );
		self::guide_card( __( 'Calendario disponibilita', 'gest-web-rent' ), '[gwr_availability_calendar]', __( 'Permette al visitatore di scegliere un periodo e vedere i veicoli disponibili.', 'gest-web-rent' ) );
		self::guide_card( __( 'Scheda veicolo', 'gest-web-rent' ), '[gwr_vehicle id="123"]', __( 'Mostra foto, dettagli noleggio, limiti chilometrici e box contatto. Alias: [gest_web_rent_vehicle].', 'gest-web-rent' ) );
		self::guide_card( __( 'Aggiornamenti', 'gest-web-rent' ), 'v1.0.1 -> v1.0.2', __( 'Aggiorna Version, GWR_VERSION, stable tag e changelog prima di creare un tag GitHub.', 'gest-web-rent' ) );
		echo '</div>';

		self::render_page_end();
	}

	/**
	 * Render common page start.
	 *
	 * @param string $current Current nav key.
	 * @param string $title Page title.
	 * @param string $description Page description.
	 * @param array  $actions Header actions.
	 * @param array  $badges Header badges.
	 * @return void
	 */
	private static function render_page_start( $current, $title, $description, $actions = array(), $badges = array() ) {
		echo '<div class="wrap gwr-admin-wrap">';
		echo '<div class="gwr-admin-shell">';
		self::render_nav( $current );
		echo '<section class="gwr-admin-stage">';
		echo '<header class="gwr-page-header">';
		echo '<div class="gwr-page-header__copy"><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $description ) . '</p>';

		if ( ! empty( $badges ) ) {
			echo '<div class="gwr-page-badges">';
			foreach ( $badges as $badge ) {
				echo '<span class="gwr-page-badge">' . esc_html( $badge ) . '</span>';
			}
			echo '</div>';
		}

		echo '</div>';

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
	 * Render common page end.
	 *
	 * @return void
	 */
	private static function render_page_end() {
		echo '</section></div></div>';
	}

	/**
	 * Render top navigation.
	 *
	 * @param string $current Current key.
	 * @return void
	 */
	private static function render_nav( $current ) {
		$items = array(
			'dashboard'    => array( 'label' => __( 'Dashboard', 'gest-web-rent' ), 'url' => admin_url( 'admin.php?page=gest-web-rent' ) ),
			'vehicles'     => array( 'label' => __( 'Veicoli', 'gest-web-rent' ), 'url' => admin_url( 'edit.php?post_type=' . GWR_CPT::POST_TYPE ) ),
			'availability' => array( 'label' => __( 'Disponibilita', 'gest-web-rent' ), 'url' => admin_url( 'admin.php?page=gwr-availability' ) ),
			'settings'     => array( 'label' => __( 'Impostazioni', 'gest-web-rent' ), 'url' => admin_url( 'admin.php?page=gwr-settings' ) ),
			'guide'        => array( 'label' => __( 'Guida', 'gest-web-rent' ), 'url' => admin_url( 'admin.php?page=gwr-guide' ) ),
		);

		echo '<nav class="gwr-admin-nav">';
		echo '<div class="gwr-admin-nav__brand"><div class="gwr-admin-nav__logo">Gest<span>Web</span>Rent</div><span class="gwr-admin-nav__caption">' . esc_html__( 'Rental workspace', 'gest-web-rent' ) . '</span></div>';
		echo '<div class="gwr-admin-nav__links">';

		foreach ( $items as $key => $item ) {
			$active = $key === $current ? ' is-active' : '';
			echo '<a class="gwr-admin-nav__link' . esc_attr( $active ) . '" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
		}

		echo '</div></nav>';
	}

	/**
	 * Metric card.
	 *
	 * @param string $title Title.
	 * @param mixed  $value Value.
	 * @param string $description Description.
	 * @return void
	 */
	private static function metric_card( $title, $value, $description ) {
		echo '<article class="gwr-kpi-card"><p class="gwr-kpi-card__title">' . esc_html( $title ) . '</p><p class="gwr-kpi-card__value">' . esc_html( (string) $value ) . '</p><p class="gwr-kpi-card__desc">' . esc_html( $description ) . '</p></article>';
	}

	/**
	 * Render upcoming availability panel.
	 *
	 * @param array $items Availability rows.
	 * @param bool  $full Whether page is full availability page.
	 * @return void
	 */
	private static function render_upcoming_panel( $items, $full = false ) {
		echo '<section class="gwr-data-grid-card">';
		echo '<div class="gwr-data-grid-card__head"><div><h2>' . esc_html__( 'Prossimi impegni', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Date usate anche dal filtro disponibilita frontend.', 'gest-web-rent' ) . '</p></div>';

		if ( ! $full ) {
			echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=gwr-availability' ) ) . '">' . esc_html__( 'Apri vista completa', 'gest-web-rent' ) . '</a>';
		}

		echo '</div>';

		if ( empty( $items ) ) {
			echo '<div class="gwr-empty-state"><strong>' . esc_html__( 'Nessun impegno futuro.', 'gest-web-rent' ) . '</strong><span>' . esc_html__( 'Apri un veicolo e aggiungi periodi nella metabox Disponibilita.', 'gest-web-rent' ) . '</span></div>';
		} else {
			echo '<div class="gwr-admin-table-wrap"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Veicolo', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Periodo', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Riferimento', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Azioni', 'gest-web-rent' ) . '</th></tr></thead><tbody>';

			foreach ( $items as $item ) {
				$edit_url = get_edit_post_link( $item['vehicle_id'] );
				echo '<tr>';
				echo '<td><strong>' . esc_html( $item['vehicle_title'] ) . '</strong></td>';
				echo '<td>' . esc_html( $item['date_from'] . ' -> ' . $item['date_to'] ) . '</td>';
				echo '<td><span class="gwr-inline-status is-' . esc_attr( $item['status'] ) . '">' . esc_html( GWR_CPT::availability_statuses()[ $item['status'] ] ?? $item['status'] ) . '</span></td>';
				echo '<td>' . esc_html( $item['label'] ?: $item['note'] ) . '</td>';
				echo '<td><a class="button button-small" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Modifica', 'gest-web-rent' ) . '</a></td>';
				echo '</tr>';
			}

			echo '</tbody></table></div>';
		}

		echo '</section>';
	}

	/**
	 * Render dashboard settings card.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function render_dashboard_settings_form( $settings ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<section class="gwr-data-grid-card">';
		echo '<div class="gwr-data-grid-card__head"><div><h2>' . esc_html__( 'Configurazione rapida contatti', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Gli stessi campi sono disponibili anche in Impostazioni. Salvati con nonce WordPress, capability manage_options e sanitizzazione dedicata.', 'gest-web-rent' ) . '</p></div></div>';
		echo '<form method="post" action="options.php" class="gwr-settings-form">';
		settings_fields( 'gwr_settings_group' );
		echo '<div class="gwr-field-grid">';
		self::setting_field( 'dealer_name', __( 'Nome concessionario', 'gest-web-rent' ), $settings['dealer_name'], 'text', __( 'Gest Web Rent', 'gest-web-rent' ) );
		self::setting_field( 'whatsapp_number', __( 'Numero WhatsApp Business', 'gest-web-rent' ), $settings['whatsapp_number'], 'text', '393331234567' );
		self::setting_field( 'contact_email', __( 'Email richieste noleggio', 'gest-web-rent' ), $settings['contact_email'], 'email', 'noleggio@example.com' );
		self::setting_textarea( 'whatsapp_message', __( 'Messaggio WhatsApp predefinito', 'gest-web-rent' ), $settings['whatsapp_message'] );
		self::setting_field( 'email_subject', __( 'Oggetto email predefinito', 'gest-web-rent' ), $settings['email_subject'], 'text' );
		self::setting_textarea( 'email_body', __( 'Corpo email predefinito', 'gest-web-rent' ), $settings['email_body'] );
		self::setting_field( 'primary_color', __( 'Colore primario', 'gest-web-rent' ), $settings['primary_color'], 'color' );
		self::setting_textarea( 'privacy_note', __( 'Testo privacy / nota contatto', 'gest-web-rent' ), $settings['privacy_note'] );
		self::github_token_field( $settings );
		echo '</div><div class="gwr-form-submit">';
		submit_button( __( 'Salva impostazioni', 'gest-web-rent' ), 'primary', 'submit', false );
		echo '</div></form></section>';
	}

	/**
	 * Render settings field.
	 *
	 * @param string $key Key.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $type Type.
	 * @param string $placeholder Placeholder.
	 * @return void
	 */
	private static function setting_field( $key, $label, $value, $type = 'text', $placeholder = '' ) {
		echo '<label class="gwr-field" for="gwr_setting_' . esc_attr( $key ) . '">';
		echo '<span class="gwr-field__label">' . esc_html( $label ) . '</span>';
		echo '<input type="' . esc_attr( $type ) . '" id="gwr_setting_' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
		echo '</label>';
	}

	/**
	 * Render settings textarea.
	 *
	 * @param string $key Key.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	private static function setting_textarea( $key, $label, $value ) {
		echo '<label class="gwr-field gwr-field--wide" for="gwr_setting_' . esc_attr( $key ) . '">';
		echo '<span class="gwr-field__label">' . esc_html( $label ) . '</span>';
		echo '<textarea id="gwr_setting_' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $key ) . ']" rows="4">' . esc_textarea( $value ) . '</textarea>';
		echo '</label>';
	}

	/**
	 * Render page select setting.
	 *
	 * @param string $key Key.
	 * @param string $label Label.
	 * @param int    $selected Selected page.
	 * @return void
	 */
	private static function page_select_field( $key, $label, $selected ) {
		echo '<label class="gwr-field" for="gwr_setting_' . esc_attr( $key ) . '">';
		echo '<span class="gwr-field__label">' . esc_html( $label ) . '</span>';
		wp_dropdown_pages(
			array(
				'name'              => self::OPTION_NAME . '[' . $key . ']',
				'id'                => 'gwr_setting_' . $key,
				'selected'          => $selected,
				'show_option_none'  => __( 'Seleziona una pagina', 'gest-web-rent' ),
				'option_none_value' => 0,
			)
		);
		echo '<span class="gwr-field__description">' . esc_html__( 'Usata per tornare al catalogo dalle schede e nei box contatto.', 'gest-web-rent' ) . '</span>';
		echo '</label>';
	}

	/**
	 * Render GitHub token field.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function github_token_field( $settings ) {
		$has_token = ! empty( $settings['github_access_token'] );
		echo '<label class="gwr-field" for="gwr_setting_github_access_token">';
		echo '<span class="gwr-field__label">' . esc_html__( 'GitHub Access Token', 'gest-web-rent' ) . '</span>';
		echo '<input type="password" id="gwr_setting_github_access_token" name="' . esc_attr( self::OPTION_NAME ) . '[github_access_token]" value="" autocomplete="new-password" placeholder="' . esc_attr( $has_token ? __( 'Lascia vuoto per mantenere il token salvato', 'gest-web-rent' ) : __( 'Token opzionale', 'gest-web-rent' ) ) . '" />';
		echo '<span class="gwr-field__description">' . esc_html__( 'Usato solo lato admin/server per repository private. Non viene mai esposto nel frontend.', 'gest-web-rent' ) . '</span>';
		echo '</label>';

		if ( $has_token ) {
			echo '<label class="gwr-field__toggle"><input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[github_access_token_clear]" value="1" /> <span>' . esc_html__( 'Rimuovi token salvato', 'gest-web-rent' ) . '</span></label>';
		}
	}

	/**
	 * Render guide card.
	 *
	 * @param string $title Title.
	 * @param string $code Code.
	 * @param string $description Description.
	 * @return void
	 */
	private static function guide_card( $title, $code, $description ) {
		echo '<section class="gwr-surface"><span class="gwr-surface__eyebrow">' . esc_html( $title ) . '</span><h2><code>' . esc_html( $code ) . '</code></h2><p>' . esc_html( $description ) . '</p></section>';
	}
}
