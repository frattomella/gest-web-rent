<?php
/**
 * Core plugin features for Gest Web Rent.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers vehicles, settings, metaboxes and frontend shortcodes.
 */
class GWR_Plugin {
	const POST_TYPE   = 'gwr_vehicle';
	const OPTION_NAME = 'gwr_settings';

	/**
	 * Singleton instance.
	 *
	 * @var GWR_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return GWR_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register plugin hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_vehicle_post_type' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_vehicle_metaboxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_vehicle_meta' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'clear_updater_cache' ) );

		add_shortcode( 'gest_web_rent_catalog', array( $this, 'render_catalog_shortcode' ) );
		add_shortcode( 'gest_web_rent_vehicle', array( $this, 'render_vehicle_shortcode' ) );
	}

	/**
	 * Activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		self::register_post_type();
		flush_rewrite_rules();
	}

	/**
	 * Register the vehicle custom post type.
	 *
	 * @return void
	 */
	public function register_vehicle_post_type() {
		self::register_post_type();
	}

	/**
	 * Shared post type registration used by runtime and activation.
	 *
	 * @return void
	 */
	private static function register_post_type() {
		$labels = array(
			'name'               => __( 'Veicoli', 'gest-web-rent' ),
			'singular_name'      => __( 'Veicolo', 'gest-web-rent' ),
			'add_new'            => __( 'Aggiungi nuovo', 'gest-web-rent' ),
			'add_new_item'       => __( 'Aggiungi veicolo', 'gest-web-rent' ),
			'edit_item'          => __( 'Modifica veicolo', 'gest-web-rent' ),
			'new_item'           => __( 'Nuovo veicolo', 'gest-web-rent' ),
			'view_item'          => __( 'Vedi veicolo', 'gest-web-rent' ),
			'search_items'       => __( 'Cerca veicoli', 'gest-web-rent' ),
			'not_found'          => __( 'Nessun veicolo trovato', 'gest-web-rent' ),
			'not_found_in_trash' => __( 'Nessun veicolo nel cestino', 'gest-web-rent' ),
			'menu_name'          => __( 'Gest Web Rent', 'gest-web-rent' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => $labels,
				'public'       => true,
				'has_archive'  => true,
				'menu_icon'    => 'dashicons-car',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
				'rewrite'      => array( 'slug' => 'veicoli-noleggio' ),
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Add the settings page.
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_options_page(
			__( 'Gest Web Rent', 'gest-web-rent' ),
			__( 'Gest Web Rent', 'gest-web-rent' ),
			'manage_options',
			'gest-web-rent',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'gwr_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::default_settings(),
			)
		);

		add_settings_section(
			'gwr_contacts_section',
			__( 'Contatti commerciali', 'gest-web-rent' ),
			'__return_false',
			'gest-web-rent'
		);

		add_settings_field(
			'whatsapp_number',
			__( 'Numero WhatsApp Business', 'gest-web-rent' ),
			array( $this, 'render_whatsapp_field' ),
			'gest-web-rent',
			'gwr_contacts_section'
		);

		add_settings_field(
			'contact_email',
			__( 'Email richieste', 'gest-web-rent' ),
			array( $this, 'render_email_field' ),
			'gest-web-rent',
			'gwr_contacts_section'
		);

		add_settings_section(
			'gwr_github_section',
			__( 'Aggiornamenti GitHub', 'gest-web-rent' ),
			array( $this, 'render_github_section' ),
			'gest-web-rent'
		);

		add_settings_field(
			'github_access_token',
			__( 'GitHub Access Token', 'gest-web-rent' ),
			array( $this, 'render_github_token_field' ),
			'gest-web-rent',
			'gwr_github_section'
		);
	}

	/**
	 * Default option values.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'whatsapp_number'     => '',
			'contact_email'       => get_option( 'admin_email' ),
			'github_access_token' => '',
		);
	}

	/**
	 * Read stored settings with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::default_settings() );
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Submitted values.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$existing = self::get_settings();

		$settings = array(
			'whatsapp_number'     => isset( $input['whatsapp_number'] ) ? preg_replace( '/[^0-9+]/', '', sanitize_text_field( wp_unslash( $input['whatsapp_number'] ) ) ) : '',
			'contact_email'       => isset( $input['contact_email'] ) ? sanitize_email( wp_unslash( $input['contact_email'] ) ) : '',
			'github_access_token' => isset( $existing['github_access_token'] ) ? $existing['github_access_token'] : '',
		);

		if ( ! empty( $input['github_access_token_clear'] ) ) {
			$settings['github_access_token'] = '';
		} elseif ( ! empty( $input['github_access_token'] ) ) {
			$settings['github_access_token'] = sanitize_text_field( wp_unslash( $input['github_access_token'] ) );
		}

		return wp_parse_args( $settings, self::default_settings() );
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gest Web Rent', 'gest-web-rent' ); ?></h1>
			<p><?php esc_html_e( 'Configura i contatti usati nel catalogo e, se necessario, il token GitHub per repository private.', 'gest-web-rent' ); ?></p>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'gwr_settings_group' );
				do_settings_sections( 'gest-web-rent' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render WhatsApp field.
	 *
	 * @return void
	 */
	public function render_whatsapp_field() {
		$settings = self::get_settings();
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[whatsapp_number]" value="<?php echo esc_attr( $settings['whatsapp_number'] ); ?>" placeholder="+393331234567">
		<p class="description"><?php esc_html_e( 'Usa il formato internazionale. Il link pubblico usera wa.me e non mostrera token o dati sensibili.', 'gest-web-rent' ); ?></p>
		<?php
	}

	/**
	 * Render email field.
	 *
	 * @return void
	 */
	public function render_email_field() {
		$settings = self::get_settings();
		?>
		<input type="email" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[contact_email]" value="<?php echo esc_attr( $settings['contact_email'] ); ?>" placeholder="noleggio@example.com">
		<?php
	}

	/**
	 * Render GitHub settings help.
	 *
	 * @return void
	 */
	public function render_github_section() {
		echo '<p>' . esc_html__( 'Per repository pubbliche non serve alcun token. Per repository private inserisci un token con permesso di lettura release; il valore viene usato solo lato admin/server.', 'gest-web-rent' ) . '</p>';
	}

	/**
	 * Render GitHub token field without exposing the stored value.
	 *
	 * @return void
	 */
	public function render_github_token_field() {
		$settings  = self::get_settings();
		$has_token = ! empty( $settings['github_access_token'] );
		?>
		<input type="password" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[github_access_token]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $has_token ? __( 'Lascia vuoto per mantenere il token salvato', 'gest-web-rent' ) : __( 'Token opzionale', 'gest-web-rent' ) ); ?>">
		<?php if ( $has_token ) : ?>
			<p class="description"><?php esc_html_e( 'Token salvato. Inserisci un nuovo valore solo se vuoi sostituirlo.', 'gest-web-rent' ); ?></p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[github_access_token_clear]" value="1">
				<?php esc_html_e( 'Rimuovi token salvato', 'gest-web-rent' ); ?>
			</label>
		<?php endif; ?>
		<?php
	}

	/**
	 * Register vehicle metadata boxes.
	 *
	 * @return void
	 */
	public function register_vehicle_metaboxes() {
		add_meta_box(
			'gwr_vehicle_details',
			__( 'Dettagli noleggio', 'gest-web-rent' ),
			array( $this, 'render_vehicle_details_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render vehicle details metabox.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_vehicle_details_metabox( $post ) {
		$meta = $this->get_vehicle_meta( $post->ID );
		wp_nonce_field( 'gwr_save_vehicle_meta', 'gwr_vehicle_meta_nonce' );
		?>
		<p>
			<label for="gwr_price"><strong><?php esc_html_e( 'Prezzo', 'gest-web-rent' ); ?></strong></label><br>
			<input type="text" class="regular-text" id="gwr_price" name="gwr_meta[price]" value="<?php echo esc_attr( $meta['price'] ); ?>" placeholder="Da 59 euro/giorno">
		</p>
		<p>
			<label for="gwr_seats"><strong><?php esc_html_e( 'Posti', 'gest-web-rent' ); ?></strong></label><br>
			<input type="number" min="0" step="1" id="gwr_seats" name="gwr_meta[seats]" value="<?php echo esc_attr( $meta['seats'] ); ?>">
		</p>
		<p>
			<label for="gwr_fuel"><strong><?php esc_html_e( 'Alimentazione', 'gest-web-rent' ); ?></strong></label><br>
			<input type="text" class="regular-text" id="gwr_fuel" name="gwr_meta[fuel]" value="<?php echo esc_attr( $meta['fuel'] ); ?>" placeholder="Diesel, benzina, elettrico">
		</p>
		<p>
			<label for="gwr_gearbox"><strong><?php esc_html_e( 'Cambio', 'gest-web-rent' ); ?></strong></label><br>
			<input type="text" class="regular-text" id="gwr_gearbox" name="gwr_meta[gearbox]" value="<?php echo esc_attr( $meta['gearbox'] ); ?>" placeholder="Manuale o automatico">
		</p>
		<p>
			<label for="gwr_availability"><strong><?php esc_html_e( 'Disponibilita', 'gest-web-rent' ); ?></strong></label><br>
			<textarea class="large-text" rows="4" id="gwr_availability" name="gwr_meta[availability]" placeholder="<?php esc_attr_e( 'Esempio: disponibile dal lunedi al sabato, chiedere conferma per ponti e festivita.', 'gest-web-rent' ); ?>"><?php echo esc_textarea( $meta['availability'] ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Save vehicle metadata.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_vehicle_meta( $post_id ) {
		if ( ! isset( $_POST['gwr_vehicle_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gwr_vehicle_meta_nonce'] ) ), 'gwr_save_vehicle_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$input = isset( $_POST['gwr_meta'] ) && is_array( $_POST['gwr_meta'] ) ? wp_unslash( $_POST['gwr_meta'] ) : array();
		$meta  = array(
			'price'        => isset( $input['price'] ) ? sanitize_text_field( $input['price'] ) : '',
			'seats'        => isset( $input['seats'] ) ? absint( $input['seats'] ) : '',
			'fuel'         => isset( $input['fuel'] ) ? sanitize_text_field( $input['fuel'] ) : '',
			'gearbox'      => isset( $input['gearbox'] ) ? sanitize_text_field( $input['gearbox'] ) : '',
			'availability' => isset( $input['availability'] ) ? sanitize_textarea_field( $input['availability'] ) : '',
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, '_gwr_' . $key, $value );
		}
	}

	/**
	 * Enqueue frontend CSS.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		wp_register_style(
			'gwr-frontend',
			GWR_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			GWR_VERSION
		);
	}

	/**
	 * Render the catalog shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_catalog_shortcode( $atts ) {
		wp_enqueue_style( 'gwr-frontend' );

		$atts = shortcode_atts(
			array(
				'limit' => -1,
			),
			$atts,
			'gest_web_rent_catalog'
		);

		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $atts['limit'],
				'orderby'        => 'menu_order date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		ob_start();
		?>
		<div class="gwr-catalog">
			<?php if ( $query->have_posts() ) : ?>
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					$this->render_vehicle_card( get_the_ID() );
				endwhile;
				wp_reset_postdata();
				?>
			<?php else : ?>
				<p class="gwr-empty"><?php esc_html_e( 'Nessun veicolo disponibile al momento.', 'gest-web-rent' ); ?></p>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render a single vehicle shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_vehicle_shortcode( $atts ) {
		wp_enqueue_style( 'gwr-frontend' );

		$atts = shortcode_atts(
			array(
				'id' => get_the_ID(),
			),
			$atts,
			'gest_web_rent_vehicle'
		);

		$vehicle_id = absint( $atts['id'] );
		$vehicle    = get_post( $vehicle_id );

		if ( ! $vehicle || self::POST_TYPE !== $vehicle->post_type || 'publish' !== $vehicle->post_status ) {
			return '<p class="gwr-empty">' . esc_html__( 'Veicolo non disponibile.', 'gest-web-rent' ) . '</p>';
		}

		$meta = $this->get_vehicle_meta( $vehicle_id );

		ob_start();
		?>
		<article class="gwr-vehicle-detail">
			<?php if ( has_post_thumbnail( $vehicle_id ) ) : ?>
				<div class="gwr-vehicle-detail__image"><?php echo get_the_post_thumbnail( $vehicle_id, 'large' ); ?></div>
			<?php endif; ?>
			<div class="gwr-vehicle-detail__body">
				<h2><?php echo esc_html( get_the_title( $vehicle_id ) ); ?></h2>
				<?php $this->render_vehicle_meta_list( $meta ); ?>
				<div class="gwr-vehicle-detail__content"><?php echo wp_kses_post( wpautop( $vehicle->post_content ) ); ?></div>
				<?php if ( ! empty( $meta['availability'] ) ) : ?>
					<div class="gwr-availability">
						<strong><?php esc_html_e( 'Disponibilita', 'gest-web-rent' ); ?></strong>
						<p><?php echo esc_html( $meta['availability'] ); ?></p>
					</div>
				<?php endif; ?>
				<?php $this->render_contact_links( get_the_title( $vehicle_id ) ); ?>
			</div>
		</article>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render a vehicle card for the catalog.
	 *
	 * @param int $vehicle_id Vehicle post ID.
	 * @return void
	 */
	private function render_vehicle_card( $vehicle_id ) {
		$meta = $this->get_vehicle_meta( $vehicle_id );
		?>
		<article class="gwr-card">
			<a class="gwr-card__media" href="<?php echo esc_url( get_permalink( $vehicle_id ) ); ?>">
				<?php
				if ( has_post_thumbnail( $vehicle_id ) ) {
					echo get_the_post_thumbnail( $vehicle_id, 'medium_large' );
				} else {
					echo '<span>' . esc_html__( 'Gest Web Rent', 'gest-web-rent' ) . '</span>';
				}
				?>
			</a>
			<div class="gwr-card__body">
				<h3><a href="<?php echo esc_url( get_permalink( $vehicle_id ) ); ?>"><?php echo esc_html( get_the_title( $vehicle_id ) ); ?></a></h3>
				<?php if ( has_excerpt( $vehicle_id ) ) : ?>
					<p class="gwr-card__excerpt"><?php echo esc_html( get_the_excerpt( $vehicle_id ) ); ?></p>
				<?php endif; ?>
				<?php $this->render_vehicle_meta_list( $meta ); ?>
				<div class="gwr-card__actions">
					<a class="gwr-button gwr-button--ghost" href="<?php echo esc_url( get_permalink( $vehicle_id ) ); ?>"><?php esc_html_e( 'Scheda veicolo', 'gest-web-rent' ); ?></a>
					<?php $this->render_contact_links( get_the_title( $vehicle_id ), true ); ?>
				</div>
			</div>
		</article>
		<?php
	}

	/**
	 * Render vehicle metadata list.
	 *
	 * @param array $meta Vehicle metadata.
	 * @return void
	 */
	private function render_vehicle_meta_list( $meta ) {
		$items = array();

		if ( ! empty( $meta['price'] ) ) {
			$items[] = $meta['price'];
		}
		if ( ! empty( $meta['seats'] ) ) {
			$items[] = sprintf(
				/* translators: %d: seats count. */
				_n( '%d posto', '%d posti', (int) $meta['seats'], 'gest-web-rent' ),
				(int) $meta['seats']
			);
		}
		if ( ! empty( $meta['fuel'] ) ) {
			$items[] = $meta['fuel'];
		}
		if ( ! empty( $meta['gearbox'] ) ) {
			$items[] = $meta['gearbox'];
		}

		if ( empty( $items ) ) {
			return;
		}
		?>
		<ul class="gwr-meta">
			<?php foreach ( $items as $item ) : ?>
				<li><?php echo esc_html( $item ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render WhatsApp/email contact links.
	 *
	 * @param string $vehicle_title Vehicle title.
	 * @param bool   $compact Whether to render compact labels.
	 * @return void
	 */
	private function render_contact_links( $vehicle_title, $compact = false ) {
		$settings = self::get_settings();
		$message  = rawurlencode( sprintf( __( 'Buongiorno, vorrei informazioni sul noleggio di: %s', 'gest-web-rent' ), $vehicle_title ) );
		$subject  = rawurlencode( sprintf( __( 'Richiesta noleggio: %s', 'gest-web-rent' ), $vehicle_title ) );
		$phone    = ! empty( $settings['whatsapp_number'] ) ? preg_replace( '/\D+/', '', $settings['whatsapp_number'] ) : '';
		$email    = ! empty( $settings['contact_email'] ) ? sanitize_email( $settings['contact_email'] ) : '';
		?>
		<div class="gwr-contact-links">
			<?php if ( $phone ) : ?>
				<a class="gwr-button gwr-button--whatsapp" href="<?php echo esc_url( 'https://wa.me/' . $phone . '?text=' . $message ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( $compact ? __( 'WhatsApp', 'gest-web-rent' ) : __( 'Contatta su WhatsApp', 'gest-web-rent' ) ); ?>
				</a>
			<?php endif; ?>
			<?php if ( $email ) : ?>
				<a class="gwr-button gwr-button--email" href="<?php echo esc_url( 'mailto:' . $email . '?subject=' . $subject ); ?>">
					<?php echo esc_html( $compact ? __( 'Email', 'gest-web-rent' ) : __( 'Invia email', 'gest-web-rent' ) ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Read vehicle metadata.
	 *
	 * @param int $vehicle_id Vehicle post ID.
	 * @return array
	 */
	private function get_vehicle_meta( $vehicle_id ) {
		return array(
			'price'        => get_post_meta( $vehicle_id, '_gwr_price', true ),
			'seats'        => get_post_meta( $vehicle_id, '_gwr_seats', true ),
			'fuel'         => get_post_meta( $vehicle_id, '_gwr_fuel', true ),
			'gearbox'      => get_post_meta( $vehicle_id, '_gwr_gearbox', true ),
			'availability' => get_post_meta( $vehicle_id, '_gwr_availability', true ),
		);
	}

	/**
	 * Clear cached GitHub release data after updater settings change.
	 *
	 * @return void
	 */
	public function clear_updater_cache() {
		delete_site_transient( 'gwr_github_release' );
	}
}
