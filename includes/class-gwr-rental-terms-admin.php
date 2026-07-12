<?php
/**
 * Rental terms administration UI.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin forms for global rental terms and vehicle overrides.
 */
class GWR_Rental_Terms_Admin {
	/** Register save handler. */
	public static function init() {
		add_action( 'admin_post_gwr_save_rental_terms', array( __CLASS__, 'handle_save' ) );
	}

	/** Render global terms page. */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) );
		}

		$terms = GWR_Rental_Terms::global_terms();
		GWR_Admin::page_start(
			'terms',
			__( 'Condizioni di noleggio', 'gest-web-rent' ),
			__( 'Configura i valori globali usati dal configuratore. I veicoli possono ereditare o personalizzare ogni sezione.', 'gest-web-rent' )
		);
		if ( isset( $_GET['gwr_terms_saved'] ) ) {
			echo '<div class="notice notice-success inline is-dismissible"><p>' . esc_html__( 'Condizioni di noleggio salvate.', 'gest-web-rent' ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-terms-form" data-gwr-terms-editor>';
		echo '<input type="hidden" name="action" value="gwr_save_rental_terms" />';
		wp_nonce_field( 'gwr_save_rental_terms', 'gwr_terms_nonce' );
		self::render_tabs();
		self::render_editor( $terms, 'gwr_terms', array() );
		echo '<div class="gwr-sticky-submit"><button type="submit" class="button button-primary button-hero">' . esc_html__( 'Salva condizioni', 'gest-web-rent' ) . '</button></div>';
		echo '</form>';
		GWR_Admin::page_end();
	}

	/** Save global terms with nonce and capability checks. */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) );
		}
		check_admin_referer( 'gwr_save_rental_terms', 'gwr_terms_nonce' );
		GWR_Rental_Terms::save_global_terms( $_POST['gwr_terms'] ?? array() );
		wp_safe_redirect( add_query_arg( array( 'page' => 'gwr-rental-terms', 'gwr_terms_saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render vehicle override editor inside the custom vehicle form.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return void
	 */
	public static function render_vehicle_overrides( $vehicle_id ) {
		$override = GWR_Rental_Terms::vehicle_override( $vehicle_id );
		echo '<section id="gwr-rental-terms-section" class="gwr-data-grid-card gwr-vehicle-terms" data-gwr-terms-editor>';
		echo '<div class="gwr-data-grid-card__head"><div><h2>' . esc_html__( 'Condizioni di noleggio', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Ogni sezione puo usare i valori globali oppure una configurazione specifica per questo veicolo. I valori personalizzati restano salvati quando riattivi l eredita.', 'gest-web-rent' ) . '</p></div><span class="gwr-mini-badge">' . esc_html__( 'Override veicolo', 'gest-web-rent' ) . '</span></div>';
		if ( ! $vehicle_id ) {
			echo '<div class="gwr-empty-state"><p>' . esc_html__( 'Salva prima il veicolo. Al salvataggio iniziale le condizioni globali verranno ereditate automaticamente.', 'gest-web-rent' ) . '</p></div></section>';
			return;
		}
		self::render_tabs();
		self::render_editor( $override['terms'], 'gwr_vehicle_terms[terms]', $override['inherit'] );
		echo '</section>';
	}

	/** Render navigation for long forms. */
	private static function render_tabs() {
		echo '<nav class="gwr-terms-tabs" aria-label="' . esc_attr__( 'Sezioni condizioni di noleggio', 'gest-web-rent' ) . '">';
		foreach ( GWR_Rental_Terms::section_labels() as $key => $label ) {
			echo '<a href="#gwr-terms-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Render all scalar and repeater sections.
	 *
	 * @param array  $terms Terms.
	 * @param string $prefix Input prefix.
	 * @param array  $inherit Inheritance map for vehicle mode.
	 * @return void
	 */
	private static function render_editor( $terms, $prefix, $inherit ) {
		$field_schema = GWR_Rental_Terms::field_schema();
		$repeater_schema = GWR_Rental_Terms::repeater_schema();
		foreach ( GWR_Rental_Terms::section_labels() as $section => $label ) {
			$is_inherited = ! empty( $inherit ) && ! empty( $inherit[ $section ] );
			echo '<section id="gwr-terms-' . esc_attr( $section ) . '" class="gwr-terms-section' . ( $is_inherited ? ' is-inherited' : '' ) . '" data-gwr-terms-section>';
			echo '<header class="gwr-terms-section__header"><div><span class="gwr-surface__eyebrow">' . esc_html__( 'Configurazione commerciale', 'gest-web-rent' ) . '</span><h2>' . esc_html( $label ) . '</h2><p>' . esc_html( self::section_help( $section ) ) . '</p></div>';
			if ( ! empty( $inherit ) ) {
				echo '<label class="gwr-inherit-switch"><input type="checkbox" name="gwr_vehicle_terms[inherit][' . esc_attr( $section ) . ']" value="1" data-gwr-inherit-toggle ' . checked( $is_inherited, true, false ) . ' /><span>' . esc_html__( 'Usa impostazioni globali', 'gest-web-rent' ) . '</span><strong data-gwr-inherit-badge>' . esc_html( $is_inherited ? __( 'Globale', 'gest-web-rent' ) : __( 'Personalizzato', 'gest-web-rent' ) ) . '</strong></label>';
			}
			echo '</header><div class="gwr-terms-section__body" data-gwr-section-fields' . ( $is_inherited ? ' aria-disabled="true"' : '' ) . '>';
			if ( isset( $field_schema[ $section ] ) ) {
				self::render_fields( $field_schema[ $section ], $terms[ $section ] ?? array(), $prefix . '[' . $section . ']' );
			} elseif ( isset( $repeater_schema[ $section ] ) ) {
				self::render_repeater( $section, $repeater_schema[ $section ], $terms[ $section ] ?? array(), $prefix . '[' . $section . ']' );
			}
			echo '</div></section>';
		}
	}

	/** Render scalar field grid. */
	private static function render_fields( $schema, $values, $prefix ) {
		echo '<div class="gwr-field-grid gwr-field-grid--triple">';
		foreach ( $schema as $key => $type ) {
			self::render_field( $prefix . '[' . $key . ']', $key, $type, $values[ $key ] ?? '' );
		}
		echo '</div>';
	}

	/** Render one repeater. */
	private static function render_repeater( $section, $schema, $rows, $prefix ) {
		echo '<div class="gwr-repeater" data-gwr-repeater data-gwr-repeater-section="' . esc_attr( $section ) . '"><div class="gwr-repeater__rows" data-gwr-repeater-rows>';
		foreach ( array_values( is_array( $rows ) ? $rows : array() ) as $index => $row ) {
			self::render_repeater_row( $schema, $row, $prefix, $index );
		}
		echo '</div><div class="gwr-repeater__empty" data-gwr-repeater-empty' . ( empty( $rows ) ? '' : ' hidden' ) . '><strong>' . esc_html__( 'Nessuna voce configurata', 'gest-web-rent' ) . '</strong><span>' . esc_html__( 'Aggiungi soltanto informazioni commerciali realmente applicate.', 'gest-web-rent' ) . '</span></div>';
		echo '<button type="button" class="button button-secondary" data-gwr-add-repeater>' . esc_html__( 'Aggiungi voce', 'gest-web-rent' ) . '</button>';
		echo '<template data-gwr-repeater-template>';
		self::render_repeater_row( $schema, array(), $prefix, '__INDEX__' );
		echo '</template></div>';
	}

	/** Render repeatable card. */
	private static function render_repeater_row( $schema, $row, $prefix, $index ) {
		$name = $prefix . '[' . $index . ']';
		echo '<article class="gwr-repeater-row" data-gwr-repeater-row tabindex="-1"><header><strong data-gwr-row-title>' . esc_html( self::row_title( $row ) ) . '</strong><div class="gwr-repeater-row__actions"><button type="button" class="button-link" data-gwr-move-repeater="up">' . esc_html__( 'Sposta su', 'gest-web-rent' ) . '</button><button type="button" class="button-link" data-gwr-move-repeater="down">' . esc_html__( 'Sposta giu', 'gest-web-rent' ) . '</button><button type="button" class="button-link-delete" data-gwr-remove-repeater>' . esc_html__( 'Elimina', 'gest-web-rent' ) . '</button></div></header><div class="gwr-field-grid gwr-field-grid--triple">';
		foreach ( $schema as $key => $type ) {
			self::render_field( $name . '[' . $key . ']', $key, $type, $row[ $key ] ?? '' );
		}
		echo '</div></article>';
	}

	/** Render one typed field. */
	private static function render_field( $name, $key, $type, $value ) {
		$label = self::field_label( $key );
		$wide = in_array( $type, array( 'textarea', 'internal_textarea', 'html' ), true ) ? ' gwr-field--wide' : '';
		if ( 'bool' === $type ) {
			echo '<label class="gwr-check-card"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $value ), true, false ) . ' /><span>' . esc_html( $label ) . '</span></label>';
			return;
		}
		echo '<label class="gwr-field' . esc_attr( $wide ) . '"><span class="gwr-field__label">' . esc_html( $label ) . '</span>';
		if ( isset( GWR_Rental_Terms::choices()[ $type ] ) ) {
			echo '<select name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'Seleziona', 'gest-web-rent' ) . '</option>';
			foreach ( GWR_Rental_Terms::choices()[ $type ] as $option_value => $option_label ) {
				echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( (string) $value, (string) $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
			}
			echo '</select>';
		} elseif ( in_array( $type, array( 'textarea', 'internal_textarea', 'html' ), true ) ) {
			echo '<textarea name="' . esc_attr( $name ) . '" rows="4">' . esc_textarea( $value ) . '</textarea>';
		} else {
			$input_type = in_array( $type, array( 'decimal', 'percent', 'integer' ), true ) ? 'number' : ( in_array( $type, array( 'url', 'email', 'date' ), true ) ? $type : 'text' );
			$step = 'decimal' === $type || 'percent' === $type ? ' step="0.01" min="0"' : ( 'integer' === $type ? ' step="1" min="0"' : '' );
			if ( 'id_list' === $type && is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
			echo '<input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $step . ( in_array( $key, array( 'title', 'name', 'question' ), true ) ? ' data-gwr-row-label' : '' ) . ' />';
		}
		if ( 'available_quantity' === $key ) {
			echo '<span class="gwr-field__description">' . esc_html__( 'Quantita informativa: il controllo reale sara disponibile con il modulo prenotazioni.', 'gest-web-rent' ) . '</span>';
		}
		if ( 'operational_notes' === $key ) {
			echo '<span class="gwr-field__description">' . esc_html__( 'Nota interna, mai esposta nel frontend.', 'gest-web-rent' ) . '</span>';
		}
		echo '</label>';
	}

	/** Labels for fields used in multiple sections. */
	private static function field_label( $key ) {
		$labels = array(
			'id' => 'ID interno', 'title' => 'Titolo', 'intro' => 'Testo introduttivo', 'rental_name' => 'Nome commerciale noleggiatore', 'currency' => 'Valuta', 'taxes' => 'Tasse', 'tax_rate' => 'Aliquota tasse (%)', 'terms_url' => 'Link condizioni complete', 'general_note' => 'Nota generale', 'updated_at' => 'Ultimo aggiornamento',
			'name' => 'Nome', 'code' => 'Codice', 'description' => 'Descrizione', 'icon' => 'Icona', 'note' => 'Nota', 'notes' => 'Note pubbliche', 'active' => 'Attivo', 'sort_order' => 'Ordine', 'cost' => 'Costo', 'unit' => 'Unita di misura',
			'type' => 'Tipologia', 'status' => 'Stato', 'price_mode' => 'Modalita prezzo', 'excess' => 'Franchigia associata', 'maximum' => 'Massimale', 'deposit_required' => 'Deposito richiesto', 'conditions' => 'Condizioni', 'exclusions' => 'Esclusioni',
			'damage_amount' => 'Franchigia danni - importo', 'damage_percent' => 'Franchigia danni - percentuale', 'theft_amount' => 'Franchigia furto - importo', 'theft_percent' => 'Franchigia furto - percentuale', 'fire_amount' => 'Franchigia incendio', 'glass_amount' => 'Franchigia cristalli', 'tyres_amount' => 'Franchigia pneumatici', 'generic_amount' => 'Franchigia generica', 'reduction_amount' => 'Riduzione franchigia', 'minimum' => 'Minimo',
			'required' => 'Richiesto', 'amount' => 'Importo', 'method' => 'Modalita', 'card_required' => 'Carta obbligatoria', 'card_holder_required' => 'Carta intestata al conducente', 'card_networks' => 'Circuiti accettati', 'debit_cards_allowed' => 'Carte di debito ammesse', 'block_timing' => 'Momento del blocco', 'release_timing' => 'Tempi di sblocco', 'operational_notes' => 'Note operative interne',
			'min_age' => 'Eta minima', 'max_age' => 'Eta massima', 'min_license_years' => 'Anni minimi patente', 'international_license' => 'Patente internazionale richiesta', 'eu_license' => 'Patente UE richiesta', 'young_driver_surcharge' => 'Supplemento giovane conducente', 'young_driver_max_age' => 'Eta massima giovane conducente', 'senior_driver_surcharge' => 'Supplemento senior', 'senior_driver_min_age' => 'Eta minima senior', 'surcharge_cost' => 'Costo supplemento', 'surcharge_mode' => 'Modalita supplemento', 'main_driver_required' => 'Conducente principale richiesto', 'additional_drivers' => 'Conducenti aggiuntivi ammessi', 'max_drivers' => 'Numero massimo conducenti',
			'at_pickup' => 'Richiesto al ritiro', 'at_booking' => 'Richiesto in prenotazione', 'private_customer' => 'Valido per privato', 'business_customer' => 'Valido per azienda',
			'minimum_level' => 'Livello minimo', 'charge_percent' => 'Percentuale ricarica', 'missing_fuel_cost' => 'Costo carburante mancante', 'refuel_service_cost' => 'Costo servizio rifornimento',
			'included_km' => 'Km inclusi', 'extra_km_cost' => 'Costo km eccedente', 'territorial_limits' => 'Limiti territoriali', 'foreign_use' => 'Uso estero', 'motorway_use' => 'Uso autostradale',
			'deposit_percent' => 'Percentuale anticipo', 'deposit_amount' => 'Importo anticipo', 'balance_timing' => 'Saldo', 'accepted_methods' => 'Metodi accettati', 'bank_transfer' => 'Bonifico', 'cash' => 'Contanti', 'online_payment' => 'Pagamento online',
			'allowed' => 'Cancellazione consentita', 'free' => 'Cancellazione gratuita', 'hours_limit' => 'Ore limite', 'days_limit' => 'Giorni limite', 'fixed_penalty' => 'Penale fissa', 'percent_penalty' => 'Penale percentuale', 'no_show' => 'Condizioni no-show', 'date_changes' => 'Modifica date consentita', 'vehicle_changes' => 'Modifica veicolo consentita', 'refund' => 'Rimborso previsto', 'refund_timing' => 'Tempi rimborso', 'policy_url' => 'Link politica completa',
			'pickup_mode' => 'Modalita ritiro', 'return_mode' => 'Modalita riconsegna', 'location_name' => 'Nome sede', 'address' => 'Indirizzo', 'instructions' => 'Istruzioni', 'contact_name' => 'Referente', 'phone' => 'Telefono', 'email' => 'Email', 'appointment_only' => 'Ritiro su appuntamento', 'after_hours_return' => 'Riconsegna fuori orario', 'after_hours_cost' => 'Costo fuori orario', 'late_tolerance_minutes' => 'Tolleranza ritardo (minuti)', 'late_cost' => 'Costo ritardo', 'home_delivery' => 'Consegna a domicilio', 'delivery_cost' => 'Costo consegna',
			'national_use' => 'Uso nazionale', 'allowed_countries' => 'Paesi consentiti', 'forbidden_countries' => 'Paesi vietati', 'authorization_required' => 'Autorizzazione necessaria', 'authorization_cost' => 'Costo autorizzazione', 'ferries' => 'Traghetti consentiti', 'islands' => 'Isole consentite', 'motorway' => 'Autostrada consentita', 'unpaved_roads' => 'Strade sterrate consentite', 'professional_use' => 'Uso professionale consentito', 'sports_use' => 'Uso sportivo consentito', 'towing' => 'Traino consentito', 'sublease' => 'Subnoleggio consentito',
			'category' => 'Categoria', 'price' => 'Prezzo', 'min_quantity' => 'Quantita minima', 'max_quantity' => 'Quantita massima', 'available' => 'Disponibile', 'available_quantity' => 'Quantita disponibile dichiarata', 'mandatory' => 'Obbligatorio', 'default_selected' => 'Selezionato di default', 'taxable' => 'Tassabile', 'all_vehicles' => 'Tutti i veicoli', 'vehicle_ids' => 'ID veicoli compatibili', 'vehicle_categories' => 'Categorie compatibili', 'question' => 'Domanda', 'answer' => 'Risposta',
		);

		return $labels[ $key ] ?? ucwords( str_replace( '_', ' ', $key ) );
	}

	/** Section help text. */
	private static function section_help( $section ) {
		$help = array(
			'general' => __( 'Identita e note generali mostrate nel configuratore.', 'gest-web-rent' ),
			'included_services' => __( 'Voci realmente comprese nel prezzo.', 'gest-web-rent' ),
			'excluded_services' => __( 'Costi e servizi esplicitamente non compresi.', 'gest-web-rent' ),
			'insurance_coverages' => __( 'Coperture incluse, opzionali o non disponibili.', 'gest-web-rent' ),
			'excesses' => __( 'Importi e percentuali applicabili alle franchigie.', 'gest-web-rent' ),
			'security_deposit' => __( 'Modalita e condizioni del deposito cauzionale.', 'gest-web-rent' ),
			'driver_requirements' => __( 'Requisiti anagrafici e di patente.', 'gest-web-rent' ),
			'required_documents' => __( 'Documenti richiesti al cliente.', 'gest-web-rent' ),
			'fuel_policy' => __( 'Regola di consegna e restituzione carburante o ricarica.', 'gest-web-rent' ),
			'mileage_policy' => __( 'Chilometri inclusi e costo eccedenza.', 'gest-web-rent' ),
			'payment_policy' => __( 'Condizioni informative, senza gateway di pagamento.', 'gest-web-rent' ),
			'cancellation_policy' => __( 'Regole per cancellazione, modifiche e no-show.', 'gest-web-rent' ),
			'pickup_return_policy' => __( 'Sede, contatti e modalita operative pubbliche.', 'gest-web-rent' ),
			'territorial_policy' => __( 'Limitazioni geografiche e di utilizzo.', 'gest-web-rent' ),
			'extras' => __( 'Optional selezionabili nel configuratore; le quantita non sono ancora prenotate.', 'gest-web-rent' ),
			'faq' => __( 'Domande frequenti pubbliche con HTML essenziale.', 'gest-web-rent' ),
		);

		return $help[ $section ] ?? '';
	}

	/** @param array $row Row. @return string */
	private static function row_title( $row ) {
		return $row['title'] ?? ( $row['name'] ?? ( $row['question'] ?? __( 'Nuova voce', 'gest-web-rent' ) ) );
	}
}
