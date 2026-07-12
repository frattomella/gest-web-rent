<?php
/**
 * Admin UI for pricing, coupons and payment settings.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pricing admin screens.
 */
class GWR_Pricing_Admin {
	/** Register hooks. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 20 );
		add_action( 'admin_post_gwr_save_economy_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_gwr_save_price_list', array( __CLASS__, 'handle_save_price_list' ) );
		add_action( 'admin_post_gwr_save_pricing_rule', array( __CLASS__, 'handle_save_rule' ) );
		add_action( 'admin_post_gwr_save_coupon', array( __CLASS__, 'handle_save_coupon' ) );
		add_action( 'admin_post_gwr_delete_pricing_row', array( __CLASS__, 'handle_delete_row' ) );
		add_action( 'admin_post_gwr_test_stripe_connection', array( __CLASS__, 'handle_test_stripe' ) );
	}

	/** Add submenu. */
	public static function admin_menu() {
		add_submenu_page( 'gest-web-rent', __( 'Tariffe e pagamenti', 'gest-web-rent' ), __( 'Tariffe e pagamenti', 'gest-web-rent' ), 'manage_options', 'gwr-pricing', array( __CLASS__, 'page' ) );
	}

	/** Main page. */
	public static function page() {
		$tab = sanitize_key( $_GET['tab'] ?? 'general' );
		$tabs = self::tabs();
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}
		GWR_Admin::page_start( 'pricing', __( 'Tariffe e pagamenti', 'gest-web-rent' ), __( 'Motore tariffario, regole economiche, coupon e gateway online.', 'gest-web-rent' ) );
		self::notice();
		self::tab_nav( $tab, $tabs );
		if ( 'general' === $tab ) {
			self::general_tab();
		} elseif ( 'taxes' === $tab ) {
			self::taxes_tab();
		} elseif ( 'stripe' === $tab ) {
			self::stripe_tab();
		} elseif ( 'bank' === $tab ) {
			self::bank_tab();
		} elseif ( 'refunds' === $tab ) {
			self::refunds_tab();
		} elseif ( 'lists' === $tab ) {
			self::price_lists_tab();
		} elseif ( 'coupons' === $tab ) {
			self::coupons_tab();
		} elseif ( 'logs' === $tab ) {
			self::logs_tab();
		} else {
			self::rules_tab( $tab );
		}
		GWR_Admin::page_end();
	}

	private static function tabs() {
		return array(
			'general'     => __( 'Impostazioni generali', 'gest-web-rent' ),
			'lists'       => __( 'Listini', 'gest-web-rent' ),
			'seasonal'    => __( 'Stagionalita', 'gest-web-rent' ),
			'duration'    => __( 'Tariffe durata', 'gest-web-rent' ),
			'weekend'     => __( 'Weekend e giorni speciali', 'gest-web-rent' ),
			'special'     => __( 'Giorni speciali', 'gest-web-rent' ),
			'supplement'  => __( 'Supplementi', 'gest-web-rent' ),
			'discount'    => __( 'Sconti automatici', 'gest-web-rent' ),
			'coupons'     => __( 'Coupon', 'gest-web-rent' ),
			'taxes'       => __( 'IVA e tasse', 'gest-web-rent' ),
			'stripe'      => __( 'Stripe', 'gest-web-rent' ),
			'bank'        => __( 'Bonifico', 'gest-web-rent' ),
			'refunds'     => __( 'Rimborsi', 'gest-web-rent' ),
			'logs'        => __( 'Log pagamenti', 'gest-web-rent' ),
		);
	}

	private static function tab_nav( $current, $tabs ) {
		echo '<nav class="gwr-terms-tabs" aria-label="' . esc_attr__( 'Sezioni tariffe e pagamenti', 'gest-web-rent' ) . '">';
		foreach ( $tabs as $key => $label ) {
			$url = add_query_arg( array( 'page' => 'gwr-pricing', 'tab' => $key ), admin_url( 'admin.php' ) );
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $key === $current ? 'is-active' : '' ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	private static function general_tab() {
		$s = GWR_Pricing_Service::get_settings();
		self::settings_form_open( 'general' );
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><h2>' . esc_html__( 'Economia generale', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Valuta, durata, arrotondamenti, acconti e metodi disponibili.', 'gest-web-rent' ) . '</p></div><div class="gwr-field-grid gwr-field-grid--triple">';
		self::field( 'currency', __( 'Valuta principale', 'gest-web-rent' ), $s['currency'] );
		self::field( 'decimals', __( 'Decimali', 'gest-web-rent' ), $s['decimals'], 'number' );
		self::select( 'duration_mode', __( 'Conteggio durata', 'gest-web-rent' ), $s['duration_mode'], array( 'daily_with_tolerance' => 'Giornaliera con tolleranza', 'twenty_four_hours' => 'Giorni da 24 ore', 'calendar_days' => 'Giorni calendario', 'rounded_fraction' => 'Frazione arrotondata' ) );
		self::field( 'return_tolerance_minutes', __( 'Tolleranza riconsegna minuti', 'gest-web-rent' ), $s['return_tolerance_minutes'], 'number' );
		self::field( 'minimum_duration_days', __( 'Durata minima', 'gest-web-rent' ), $s['minimum_duration_days'], 'number' );
		self::field( 'maximum_duration_days', __( 'Durata massima', 'gest-web-rent' ), $s['maximum_duration_days'], 'number' );
		self::select( 'rounding_mode', __( 'Arrotondamento', 'gest-web-rent' ), $s['rounding_mode'], array( 'nearest' => 'Piu vicino', 'up' => 'Per eccesso', 'down' => 'Per difetto' ) );
		self::field( 'rounding_increment', __( 'Incremento arrotondamento', 'gest-web-rent' ), GWR_Money::minor_to_decimal( $s['rounding_increment_minor'] ), 'number' );
		self::field( 'minimum_booking', __( 'Prezzo minimo prenotazione', 'gest-web-rent' ), GWR_Money::minor_to_decimal( $s['minimum_booking_minor'] ), 'number' );
		self::field( 'days_per_week', __( 'Giorni per settimana tariffaria', 'gest-web-rent' ), $s['days_per_week'], 'number' );
		self::field( 'days_per_month', __( 'Giorni per mese commerciale', 'gest-web-rent' ), $s['days_per_month'], 'number' );
		self::select( 'weekly_monthly_mode', __( 'Settimanale/mensile', 'gest-web-rent' ), $s['weekly_monthly_mode'], array( 'off' => 'Disattivo', 'best_price' => 'Miglior prezzo', 'always_package' => 'Sempre pacchetto' ) );
		self::field( 'weekend_days', __( 'Giorni weekend ISO', 'gest-web-rent' ), $s['weekend_days'], 'text', '6,7' );
		self::select( 'payment_mode', __( 'Modalita pagamento', 'gest-web-rent' ), $s['payment_mode'], array( 'request_only' => 'Solo richiesta', 'full_online' => 'Totale online', 'deposit_percent' => 'Acconto percentuale', 'deposit_fixed' => 'Acconto fisso', 'first_day' => 'Prima giornata', 'pickup' => 'Al ritiro', 'bank_transfer' => 'Bonifico', 'client_choice' => 'Scelta cliente' ) );
		self::field( 'deposit_percent', __( 'Acconto percentuale', 'gest-web-rent' ), $s['deposit_percent'], 'number' );
		self::field( 'deposit_fixed', __( 'Acconto fisso', 'gest-web-rent' ), GWR_Money::minor_to_decimal( $s['deposit_fixed_minor'] ), 'number' );
		self::field( 'deposit_min', __( 'Acconto minimo', 'gest-web-rent' ), GWR_Money::minor_to_decimal( $s['deposit_min_minor'] ), 'number' );
		self::field( 'deposit_max', __( 'Acconto massimo', 'gest-web-rent' ), GWR_Money::minor_to_decimal( $s['deposit_max_minor'] ), 'number' );
		self::field( 'active_payment_methods', __( 'Metodi attivi', 'gest-web-rent' ), $s['active_payment_methods'], 'text', 'request,pickup,stripe,bank_transfer' );
		self::field( 'default_payment_method', __( 'Metodo predefinito', 'gest-web-rent' ), $s['default_payment_method'] );
		echo '</div></section>';
		self::settings_form_close();
	}

	private static function taxes_tab() {
		$s = GWR_Pricing_Service::get_settings();
		self::settings_form_open( 'taxes' );
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><h2>' . esc_html__( 'IVA e tasse', 'gest-web-rent' ) . '</h2></div><div class="gwr-field-grid">';
		self::checkbox( 'prices_include_tax', __( 'Prezzi IVA inclusa', 'gest-web-rent' ), $s['prices_include_tax'] );
		self::field( 'default_tax_rate', __( 'Aliquota IVA predefinita %', 'gest-web-rent' ), $s['default_tax_rate'], 'number' );
		self::field( 'extra_tax_rate', __( 'Aliquota extra %', 'gest-web-rent' ), $s['extra_tax_rate'], 'number' );
		self::field( 'coverage_tax_rate', __( 'Aliquota coperture %', 'gest-web-rent' ), $s['coverage_tax_rate'], 'number' );
		echo '</div></section>';
		self::settings_form_close();
	}

	private static function stripe_tab() {
		$s = GWR_Pricing_Service::get_settings();
		$webhook_url = rest_url( 'gest-web-rent/v1/stripe/webhook' );
		self::settings_form_open( 'stripe' );
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><h2>Stripe</h2><p>' . esc_html__( 'Checkout hosted: nessun dato carta viene salvato nel plugin.', 'gest-web-rent' ) . '</p></div><div class="gwr-field-grid">';
		self::checkbox( 'stripe_enabled', __( 'Abilita Stripe', 'gest-web-rent' ), $s['stripe_enabled'] );
		self::select( 'stripe_mode', __( 'Ambiente', 'gest-web-rent' ), $s['stripe_mode'], array( 'test' => 'TEST', 'live' => 'LIVE' ) );
		self::field( 'stripe_test_publishable_key', __( 'Publishable key test', 'gest-web-rent' ), $s['stripe_test_publishable_key'] );
		self::secret( 'stripe_test_secret_key', __( 'Secret key test', 'gest-web-rent' ), $s['stripe_test_secret_key'] );
		self::secret( 'stripe_test_webhook_secret', __( 'Webhook secret test', 'gest-web-rent' ), $s['stripe_test_webhook_secret'] );
		self::field( 'stripe_live_publishable_key', __( 'Publishable key live', 'gest-web-rent' ), $s['stripe_live_publishable_key'] );
		self::secret( 'stripe_live_secret_key', __( 'Secret key live', 'gest-web-rent' ), $s['stripe_live_secret_key'] );
		self::secret( 'stripe_live_webhook_secret', __( 'Webhook secret live', 'gest-web-rent' ), $s['stripe_live_webhook_secret'] );
		self::field( 'stripe_currency', __( 'Valuta Stripe', 'gest-web-rent' ), $s['stripe_currency'] );
		self::field( 'stripe_description', __( 'Descrizione pagamento', 'gest-web-rent' ), $s['stripe_description'] );
		self::field( 'stripe_session_expiry_minutes', __( 'Scadenza sessione minuti', 'gest-web-rent' ), $s['stripe_session_expiry_minutes'], 'number' );
		self::field( 'stripe_success_url', __( 'Success URL', 'gest-web-rent' ), $s['stripe_success_url'], 'url' );
		self::field( 'stripe_cancel_url', __( 'Cancel URL', 'gest-web-rent' ), $s['stripe_cancel_url'], 'url' );
		self::checkbox( 'stripe_cards_enabled', __( 'Abilita carte', 'gest-web-rent' ), $s['stripe_cards_enabled'] );
		self::checkbox( 'stripe_wallets_enabled', __( 'Wallet supportati da Checkout', 'gest-web-rent' ), $s['stripe_wallets_enabled'] );
		self::checkbox( 'stripe_receipt_email', __( 'Invia ricevuta Stripe', 'gest-web-rent' ), $s['stripe_receipt_email'] );
		self::checkbox( 'stripe_admin_refunds', __( 'Abilita rimborso admin', 'gest-web-rent' ), $s['stripe_admin_refunds'] );
		echo '</div><div class="gwr-status-stack"><div class="gwr-status-line"><span>Webhook URL</span><strong><code>' . esc_html( $webhook_url ) . '</code></strong></div><div class="gwr-status-line"><span>' . esc_html__( 'Ultimo evento', 'gest-web-rent' ) . '</span><strong>' . esc_html( $s['webhook_last_event_at'] ?: '-' ) . '</strong></div><div class="gwr-status-line"><span>' . esc_html__( 'Ultimo errore', 'gest-web-rent' ) . '</span><strong>' . esc_html( $s['webhook_last_error'] ?: '-' ) . '</strong></div></div></section>';
		self::settings_form_close();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-form-submit"><input type="hidden" name="action" value="gwr_test_stripe_connection" />'; wp_nonce_field( 'gwr_test_stripe_connection' ); echo '<button class="button button-secondary">' . esc_html__( 'Verifica connessione Stripe', 'gest-web-rent' ) . '</button></form>';
	}

	private static function bank_tab() {
		$s = GWR_Pricing_Service::get_settings();
		self::settings_form_open( 'bank' );
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><h2>' . esc_html__( 'Bonifico bancario', 'gest-web-rent' ) . '</h2></div><div class="gwr-field-grid">';
		self::checkbox( 'bank_transfer_enabled', __( 'Abilita bonifico', 'gest-web-rent' ), $s['bank_transfer_enabled'] );
		self::field( 'bank_account_holder', __( 'Intestatario', 'gest-web-rent' ), $s['bank_account_holder'] );
		self::field( 'bank_iban', 'IBAN', $s['bank_iban'] );
		self::field( 'bank_bic', 'BIC/SWIFT', $s['bank_bic'] );
		self::field( 'bank_name', __( 'Banca', 'gest-web-rent' ), $s['bank_name'] );
		self::field( 'bank_reason_template', __( 'Causale', 'gest-web-rent' ), $s['bank_reason_template'] );
		self::field( 'bank_due_days', __( 'Scadenza giorni', 'gest-web-rent' ), $s['bank_due_days'], 'number' );
		self::textarea( 'bank_instructions', __( 'Istruzioni', 'gest-web-rent' ), $s['bank_instructions'] );
		echo '</div></section>';
		self::settings_form_close();
	}

	private static function refunds_tab() {
		$s = GWR_Pricing_Service::get_settings();
		self::settings_form_open( 'refunds' );
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><h2>' . esc_html__( 'Politica rimborsi', 'gest-web-rent' ) . '</h2></div><div class="gwr-field-grid">';
		self::select( 'refund_policy', __( 'Gestione rimborsi', 'gest-web-rent' ), $s['refund_policy'], array( 'manual' => 'Manuale', 'automatic' => 'Automatica predisposta' ) );
		self::field( 'free_cancellation_hours', __( 'Cancellazione gratuita ore', 'gest-web-rent' ), $s['free_cancellation_hours'], 'number' );
		self::field( 'cancellation_penalty', __( 'Penale fissa', 'gest-web-rent' ), GWR_Money::minor_to_decimal( $s['cancellation_penalty_minor'] ), 'number' );
		self::field( 'refund_deposit_policy', __( 'Policy acconto/saldo', 'gest-web-rent' ), $s['refund_deposit_policy'] );
		echo '</div></section>';
		self::settings_form_close();
	}

	private static function price_lists_tab() {
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><h2>' . esc_html__( 'Nuovo listino', 'gest-web-rent' ) . '</h2></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-settings-form"><input type="hidden" name="action" value="gwr_save_price_list" />'; wp_nonce_field( 'gwr_save_price_list' );
		echo '<div class="gwr-field-grid gwr-field-grid--triple">';
		foreach ( array( 'name' => 'Nome', 'code' => 'Codice', 'currency' => 'Valuta', 'tax_rate' => 'IVA %', 'start_date' => 'Data inizio', 'end_date' => 'Data fine', 'priority' => 'Priorita', 'scope_type' => 'Ambito', 'scope_ids' => 'Valori ambito', 'min_days' => 'Giorni min', 'max_days' => 'Giorni max' ) as $key => $label ) {
			self::raw_field( 'gwr_price_list[' . $key . ']', $label, '', false !== strpos( $key, 'date' ) ? 'date' : ( false !== strpos( $key, 'days' ) || 'priority' === $key || 'tax_rate' === $key ? 'number' : 'text' ) );
		}
		self::raw_textarea( 'gwr_price_list[config_json]', 'Config JSON opzionale' );
		echo '<label class="gwr-check-card"><input type="checkbox" name="gwr_price_list[active]" value="1" /> <span>' . esc_html__( 'Attivo', 'gest-web-rent' ) . '</span></label></div><button class="button button-primary">' . esc_html__( 'Salva listino', 'gest-web-rent' ) . '</button></form></section>';
		self::list_table( GWR_Pricing_Service::price_lists(), 'price_list' );
	}

	private static function rules_tab( $type ) {
		$labels = self::tabs();
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><h2>' . esc_html( $labels[ $type ] ?? $type ) . '</h2></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-settings-form"><input type="hidden" name="action" value="gwr_save_pricing_rule" /><input type="hidden" name="gwr_rule[rule_type]" value="' . esc_attr( $type ) . '" />'; wp_nonce_field( 'gwr_save_pricing_rule' );
		echo '<div class="gwr-field-grid gwr-field-grid--triple">';
		foreach ( array( 'name' => 'Nome', 'code' => 'Codice', 'start_date' => 'Data inizio', 'end_date' => 'Data fine', 'weekdays' => 'Giorni settimana ISO', 'scope_type' => 'Ambito', 'scope_ids' => 'Valori ambito', 'adjustment_type' => 'Tipo modifica', 'percent_value' => 'Percentuale', 'amount' => 'Importo', 'price' => 'Prezzo sostitutivo', 'min_days' => 'Giorni min', 'max_days' => 'Giorni max', 'priority' => 'Priorita' ) as $key => $label ) {
			self::raw_field( 'gwr_rule[' . $key . ']', $label, '', false !== strpos( $key, 'date' ) ? 'date' : ( in_array( $key, array( 'percent_value', 'amount', 'price', 'min_days', 'max_days', 'priority' ), true ) ? 'number' : 'text' ) );
		}
		self::raw_textarea( 'gwr_rule[description]', 'Descrizione' );
		self::raw_textarea( 'gwr_rule[config_json]', 'Condizioni JSON opzionali' );
		echo '<label class="gwr-check-card"><input type="checkbox" name="gwr_rule[recurring_annual]" value="1" /> <span>' . esc_html__( 'Ricorrenza annuale', 'gest-web-rent' ) . '</span></label><label class="gwr-check-card"><input type="checkbox" name="gwr_rule[combinable]" value="1" checked /> <span>' . esc_html__( 'Combinabile', 'gest-web-rent' ) . '</span></label><label class="gwr-check-card"><input type="checkbox" name="gwr_rule[active]" value="1" /> <span>' . esc_html__( 'Attiva', 'gest-web-rent' ) . '</span></label></div><button class="button button-primary">' . esc_html__( 'Salva regola', 'gest-web-rent' ) . '</button></form></section>';
		self::list_table( GWR_Pricing_Service::rules( $type ), 'rule' );
	}

	private static function coupons_tab() {
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><h2>Coupon</h2></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-settings-form"><input type="hidden" name="action" value="gwr_save_coupon" />'; wp_nonce_field( 'gwr_save_coupon' );
		echo '<div class="gwr-field-grid gwr-field-grid--triple">';
		foreach ( array( 'code' => 'Codice', 'discount_type' => 'Tipo', 'percent_value' => 'Percentuale', 'value' => 'Importo', 'free_days' => 'Giorni gratuiti', 'min_spend' => 'Minimo spesa', 'max_discount' => 'Massimo sconto', 'start_date' => 'Data inizio', 'end_date' => 'Data fine', 'total_uses' => 'Usi totali', 'uses_per_email' => 'Usi per email', 'scope_type' => 'Ambito', 'scope_ids' => 'Valori ambito', 'min_days' => 'Giorni min', 'max_days' => 'Giorni max' ) as $key => $label ) {
			self::raw_field( 'gwr_coupon[' . $key . ']', $label, '', false !== strpos( $key, 'date' ) ? 'date' : ( in_array( $key, array( 'percent_value', 'value', 'free_days', 'min_spend', 'max_discount', 'total_uses', 'uses_per_email', 'min_days', 'max_days' ), true ) ? 'number' : 'text' ) );
		}
		self::raw_textarea( 'gwr_coupon[description]', 'Descrizione' );
		self::raw_textarea( 'gwr_coupon[allowed_emails]', 'Email autorizzate opzionali' );
		echo '<label class="gwr-check-card"><input type="checkbox" name="gwr_coupon[combinable]" value="1" /> <span>' . esc_html__( 'Cumulabile', 'gest-web-rent' ) . '</span></label><label class="gwr-check-card"><input type="checkbox" name="gwr_coupon[single_use]" value="1" /> <span>' . esc_html__( 'Uso singolo', 'gest-web-rent' ) . '</span></label><label class="gwr-check-card"><input type="checkbox" name="gwr_coupon[active]" value="1" /> <span>' . esc_html__( 'Attivo', 'gest-web-rent' ) . '</span></label></div><button class="button button-primary">' . esc_html__( 'Salva coupon', 'gest-web-rent' ) . '</button></form></section>';
		self::list_table( GWR_Coupon_Service::coupons(), 'coupon' );
	}

	private static function logs_tab() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . GWR_Payment_Service::events_table() . ' ORDER BY received_at DESC LIMIT 100', ARRAY_A );
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><h2>' . esc_html__( 'Ultimi eventi pagamento', 'gest-web-rent' ) . '</h2></div><div class="gwr-table-wrap"><table class="widefat"><thead><tr><th>ID provider</th><th>Tipo</th><th>Pagamento</th><th>Prenotazione</th><th>Processato</th><th>Ricevuto</th><th>Errore</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td><code>' . esc_html( $row['provider_event_id'] ) . '</code></td><td>' . esc_html( $row['event_type'] ) . '</td><td>' . esc_html( $row['payment_id'] ) . '</td><td>' . esc_html( $row['booking_id'] ) . '</td><td>' . esc_html( $row['processed'] ? 'si' : 'no' ) . '</td><td>' . esc_html( $row['received_at'] ) . '</td><td>' . esc_html( $row['processing_error'] ) . '</td></tr>';
		}
		if ( ! $rows ) { echo '<tr><td colspan="7">' . esc_html__( 'Nessun evento registrato.', 'gest-web-rent' ) . '</td></tr>'; }
		echo '</tbody></table></div></section>';
	}

	private static function list_table( $rows, $type ) {
		echo '<section class="gwr-data-grid-card"><div class="gwr-table-wrap"><table class="widefat"><thead><tr><th>ID</th><th>Nome/Codice</th><th>Stato</th><th>Periodo</th><th>Priorita</th><th>Azioni</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$table = 'coupon' === $type ? GWR_Pricing_Service::coupons_table() : ( 'price_list' === $type ? GWR_Pricing_Service::price_lists_table() : GWR_Pricing_Service::rules_table() );
			$delete = wp_nonce_url( admin_url( 'admin-post.php?action=gwr_delete_pricing_row&type=' . rawurlencode( $type ) . '&id=' . absint( $row['id'] ) . '&table=' . rawurlencode( $table ) ), 'gwr_delete_pricing_row_' . absint( $row['id'] ) );
			$name = $row['name'] ?? ( $row['code'] ?? '' );
			echo '<tr><td>' . esc_html( $row['id'] ) . '</td><td><strong>' . esc_html( $name ) . '</strong><small>' . esc_html( $row['code'] ?? '' ) . '</small></td><td><span class="gwr-inline-status is-' . esc_attr( ! empty( $row['active'] ) ? 'active' : 'draft' ) . '">' . esc_html( ! empty( $row['active'] ) ? __( 'Attivo', 'gest-web-rent' ) : __( 'Bozza', 'gest-web-rent' ) ) . '</span></td><td>' . esc_html( ( $row['start_date'] ?? '-' ) . ' / ' . ( $row['end_date'] ?? '-' ) ) . '</td><td>' . esc_html( $row['priority'] ?? '-' ) . '</td><td><a class="button button-small" href="' . esc_url( $delete ) . '">' . esc_html__( 'Elimina', 'gest-web-rent' ) . '</a></td></tr>';
		}
		if ( ! $rows ) { echo '<tr><td colspan="6">' . esc_html__( 'Nessuna voce configurata.', 'gest-web-rent' ) . '</td></tr>'; }
		echo '</tbody></table></div></section>';
	}

	private static function settings_form_open( $tab ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-settings-form"><input type="hidden" name="action" value="gwr_save_economy_settings" /><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />';
		wp_nonce_field( 'gwr_save_economy_settings' );
	}

	private static function settings_form_close() {
		echo '<div class="gwr-form-submit"><button class="button button-primary">' . esc_html__( 'Salva impostazioni', 'gest-web-rent' ) . '</button></div></form>';
	}

	private static function field( $key, $label, $value, $type = 'text', $placeholder = '' ) {
		self::raw_field( 'gwr_settings[' . $key . ']', $label, $value, $type, $placeholder );
	}

	private static function raw_field( $name, $label, $value = '', $type = 'text', $placeholder = '' ) {
		$step = 'number' === $type ? ' step="any"' : '';
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $step . ' /></label>';
	}

	private static function textarea( $key, $label, $value ) {
		self::raw_textarea( 'gwr_settings[' . $key . ']', $label, $value );
	}

	private static function raw_textarea( $name, $label, $value = '' ) {
		echo '<label class="gwr-field gwr-field--wide"><span class="gwr-field__label">' . esc_html( $label ) . '</span><textarea name="' . esc_attr( $name ) . '" rows="4">' . esc_textarea( $value ) . '</textarea></label>';
	}

	private static function select( $key, $label, $value, $options ) {
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html( $label ) . '</span><select name="gwr_settings[' . esc_attr( $key ) . ']">';
		foreach ( $options as $option => $option_label ) {
			echo '<option value="' . esc_attr( $option ) . '" ' . selected( $value, $option, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></label>';
	}

	private static function checkbox( $key, $label, $checked ) {
		echo '<input type="hidden" name="gwr_settings[' . esc_attr( $key ) . ']" value="0" /><label class="gwr-check-card"><input type="checkbox" name="gwr_settings[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $checked ), true, false ) . ' /> <span>' . esc_html( $label ) . '</span></label>';
	}

	private static function secret( $key, $label, $value ) {
		$placeholder = $value ? '********' : '';
		self::field( $key, $label, '', 'password', $placeholder );
		if ( $value ) {
			echo '<label class="gwr-check-card"><input type="checkbox" name="gwr_settings[' . esc_attr( $key ) . '_clear]" value="1" /> <span>' . esc_html__( 'Rimuovi valore salvato', 'gest-web-rent' ) . '</span></label>';
		}
	}

	private static function notice() {
		if ( empty( $_GET['gwr_message'] ) ) { return; }
		$class = ! empty( $_GET['gwr_error'] ) ? 'notice-error' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $class ) . ' inline is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['gwr_message'] ) ) ) . '</p></div>';
	}

	public static function handle_save_settings() {
		self::require_cap();
		check_admin_referer( 'gwr_save_economy_settings' );
		GWR_Pricing_Service::save_settings( $_POST['gwr_settings'] ?? array() );
		self::redirect( sanitize_key( $_POST['tab'] ?? 'general' ), __( 'Impostazioni salvate.', 'gest-web-rent' ) );
	}

	public static function handle_save_price_list() {
		self::require_cap();
		check_admin_referer( 'gwr_save_price_list' );
		$result = GWR_Pricing_Service::save_price_list( $_POST['gwr_price_list'] ?? array() );
		self::redirect( 'lists', is_wp_error( $result ) ? $result->get_error_message() : __( 'Listino salvato.', 'gest-web-rent' ), is_wp_error( $result ) );
	}

	public static function handle_save_rule() {
		self::require_cap();
		check_admin_referer( 'gwr_save_pricing_rule' );
		$input = $_POST['gwr_rule'] ?? array();
		$result = GWR_Pricing_Service::save_rule( $input );
		self::redirect( sanitize_key( $input['rule_type'] ?? 'seasonal' ), is_wp_error( $result ) ? $result->get_error_message() : __( 'Regola salvata.', 'gest-web-rent' ), is_wp_error( $result ) );
	}

	public static function handle_save_coupon() {
		self::require_cap();
		check_admin_referer( 'gwr_save_coupon' );
		$result = GWR_Pricing_Service::save_coupon( $_POST['gwr_coupon'] ?? array() );
		self::redirect( 'coupons', is_wp_error( $result ) ? $result->get_error_message() : __( 'Coupon salvato.', 'gest-web-rent' ), is_wp_error( $result ) );
	}

	public static function handle_delete_row() {
		self::require_cap();
		$id = absint( $_GET['id'] ?? 0 );
		check_admin_referer( 'gwr_delete_pricing_row_' . $id );
		$type = sanitize_key( $_GET['type'] ?? 'lists' );
		$table = sanitize_text_field( wp_unslash( $_GET['table'] ?? '' ) );
		GWR_Pricing_Service::delete_row( $table, $id );
		self::redirect( 'price_list' === $type ? 'lists' : ( 'coupon' === $type ? 'coupons' : 'seasonal' ), __( 'Voce eliminata.', 'gest-web-rent' ) );
	}

	public static function handle_test_stripe() {
		self::require_cap();
		check_admin_referer( 'gwr_test_stripe_connection' );
		$result = GWR_Stripe_Service::test_connection( GWR_Pricing_Service::get_settings() );
		$message = is_wp_error( $result ) ? $result->get_error_message() : sprintf( __( 'Connessione Stripe riuscita: %s', 'gest-web-rent' ), sanitize_text_field( $result['id'] ?? 'account' ) );
		self::redirect( 'stripe', $message, is_wp_error( $result ) );
	}

	private static function require_cap() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) );
		}
	}

	private static function redirect( $tab, $message, $error = false ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'gwr-pricing', 'tab' => sanitize_key( $tab ), 'gwr_message' => rawurlencode( $message ), 'gwr_error' => $error ? 1 : 0 ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
