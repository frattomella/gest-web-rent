<?php
/**
 * Information and manually reviewed booking requests.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores lightweight requests without creating bookings or payments.
 */
class GWR_Inquiries {
	const DB_VERSION = '2.0.0';
	const DB_OPTION  = 'gwr_inquiries_db_version';
	const CRON_HOOK  = 'gwr_expire_inquiry_holds';

	/** Register only the hooks used by showcase and request modes. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 6 );
		add_action( 'wp_ajax_gwr_submit_inquiry', array( __CLASS__, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_gwr_submit_inquiry', array( __CLASS__, 'ajax_submit' ) );
		add_action( 'admin_post_gwr_inquiry_action', array( __CLASS__, 'handle_admin_action' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'expire_temporary_holds' ) );
		self::schedule();
	}

	/** Request table name. */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_inquiries';
	}

	/** Idempotent schema upgrade. */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_OPTION ) !== self::DB_VERSION ) {
			self::create_table();
		}
	}

	/** Create the request table without touching existing booking data. */
	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			request_code varchar(50) NOT NULL,
			request_type varchar(30) NOT NULL DEFAULT 'information',
			status varchar(30) NOT NULL DEFAULT 'new',
			vehicle_id bigint(20) unsigned NOT NULL,
			availability_id bigint(20) unsigned NULL,
			start_date date NULL,
			end_date date NULL,
			pickup_time varchar(5) NULL,
			return_time varchar(5) NULL,
			customer_type varchar(20) NOT NULL DEFAULT 'private',
			first_name varchar(120) NOT NULL,
			last_name varchar(120) NOT NULL,
			email varchar(190) NOT NULL,
			phone varchar(60) NOT NULL,
			message text NULL,
			selection_json longtext NULL,
			privacy_accepted_at datetime NOT NULL,
			terms_accepted_at datetime NULL,
			expires_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY request_code (request_code),
			KEY vehicle_status (vehicle_id,status),
			KEY status_created (status,created_at),
			KEY email (email)
		) {$charset};";
		dbDelta( $sql );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/** Public AJAX endpoint for lightweight forms. */
	public static function ajax_submit() {
		if ( gwr_is_booking_mode() ) {
			wp_send_json_error( array( 'code' => 'feature_not_available', 'message' => __( 'Il modulo richieste non e attivo nella modalita corrente.', 'gest-web-rent' ) ), 409 );
		}
		check_ajax_referer( 'gwr_catalog_nonce', 'nonce' );

		$raw    = isset( $_POST['inquiry'] ) ? json_decode( wp_unslash( $_POST['inquiry'] ), true ) : array();
		$result = self::create( is_array( $raw ) ? $raw : array() );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			wp_send_json_error(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
					'field'   => is_array( $data ) ? sanitize_key( $data['field'] ?? '' ) : '',
				),
				400
			);
		}

		wp_send_json_success( $result );
	}

	/** Validate, optionally persist and notify a request. */
	public static function create( $raw ) {
		global $wpdb;
		$settings = GWR_Admin::get_settings();
		$input    = self::sanitize_request( $raw );
		if ( is_wp_error( $input ) ) {
			return $input;
		}

		$antispam = self::check_antispam( $input );
		if ( is_wp_error( $antispam ) ) {
			return $antispam;
		}

		$vehicle = GWR_CPT::get_vehicle( $input['vehicle_id'] );
		if ( ! $vehicle || 'active' !== ( $vehicle['status'] ?? '' ) ) {
			return new WP_Error( 'vehicle_not_found', __( 'Il veicolo selezionato non e disponibile.', 'gest-web-rent' ), array( 'field' => 'vehicle_id' ) );
		}

		$is_request = gwr_is_request_mode();
		if ( $is_request && ( ! $input['start_date'] || ! $input['end_date'] ) ) {
			return new WP_Error( 'dates_required', __( 'Seleziona data di ritiro e riconsegna.', 'gest-web-rent' ), array( 'field' => 'start_date' ) );
		}
		if ( $input['start_date'] && ! self::period_available( $input['vehicle_id'], $input['start_date'], $input['end_date'] ) ) {
			return new WP_Error( 'vehicle_unavailable', __( 'Il veicolo non e piu disponibile nel periodo selezionato.', 'gest-web-rent' ), array( 'field' => 'start_date' ) );
		}

		$save = $is_request || ! empty( $settings['save_information_requests'] );
		$now  = current_time( 'mysql' );
		$row  = array(
			'request_code'        => 'GWR-R-' . strtoupper( wp_generate_password( 8, false, false ) ),
			'request_type'        => $is_request ? 'booking_request' : 'information',
			'status'              => $is_request ? 'pending_review' : 'new',
			'vehicle_id'          => $input['vehicle_id'],
			'availability_id'     => null,
			'start_date'          => $input['start_date'] ?: null,
			'end_date'            => $input['end_date'] ?: null,
			'pickup_time'         => $input['pickup_time'] ?: null,
			'return_time'         => $input['return_time'] ?: null,
			'customer_type'       => $input['customer_type'],
			'first_name'          => $input['first_name'],
			'last_name'           => $input['last_name'],
			'email'               => $input['email'],
			'phone'               => $input['phone'],
			'message'             => $input['message'],
			'selection_json'      => wp_json_encode( $input['selection'] ),
			'privacy_accepted_at' => $now,
			'terms_accepted_at'   => $is_request ? $now : null,
			'expires_at'          => null,
			'created_at'          => $now,
			'updated_at'          => $now,
		);

		if ( $is_request && 'hours' === ( $settings['pending_request_block_mode'] ?? 'no' ) ) {
			$hours             = max( 1, min( 168, absint( $settings['pending_request_block_hours'] ?? 24 ) ) );
			$row['expires_at'] = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+' . $hours . ' hours' )->format( 'Y-m-d H:i:s' );
		}

		$request_id = 0;
		if ( $save ) {
			if ( false === $wpdb->insert( self::table(), $row ) ) {
				return new WP_Error( 'request_save_failed', __( 'Non e stato possibile registrare la richiesta.', 'gest-web-rent' ) );
			}
			$request_id = (int) $wpdb->insert_id;

			$block_mode = sanitize_key( $settings['pending_request_block_mode'] ?? 'no' );
			if ( $is_request && in_array( $block_mode, array( 'hours', 'manual' ), true ) ) {
				$availability_id = self::create_hold( $row );
				if ( $availability_id ) {
					$wpdb->update( self::table(), array( 'availability_id' => $availability_id ), array( 'id' => $request_id ), array( '%d' ), array( '%d' ) );
					$row['availability_id'] = $availability_id;
				}
			}
		}

		$sent = self::send_notifications( $row, $vehicle, $settings );
		if ( ! $save && ! $sent ) {
			return new WP_Error( 'notification_failed', __( 'Non e stato possibile inviare la richiesta. Usa WhatsApp o email per contattarci.', 'gest-web-rent' ) );
		}

		return array(
			'request_id'   => $request_id,
			'request_code' => $save ? $row['request_code'] : '',
			'status'       => $is_request ? 'pending_review' : 'sent',
			'message'      => $is_request
				? __( 'Richiesta inviata correttamente. Il noleggiatore verifichera disponibilita e condizioni e ti contattera per la conferma.', 'gest-web-rent' )
				: __( 'Richiesta di informazioni inviata correttamente. Ti ricontatteremo appena possibile.', 'gest-web-rent' ),
		);
	}

	/** Sanitize and validate public request fields. */
	private static function sanitize_request( $raw ) {
		$raw   = is_array( $raw ) ? wp_unslash( $raw ) : array();
		$start = sanitize_text_field( $raw['start_date'] ?? '' );
		$end   = sanitize_text_field( $raw['end_date'] ?? '' );
		if ( $start || $end ) {
			$range = GWR_CPT::normalize_date_range( $start, $end );
			if ( is_wp_error( $range ) ) {
				return new WP_Error( 'invalid_dates', __( 'Il periodo selezionato non e valido.', 'gest-web-rent' ), array( 'field' => 'start_date' ) );
			}
			$start = $range['start_date'];
			$end   = $range['end_date'];
		}

		$input = array(
			'vehicle_id'   => absint( $raw['vehicle_id'] ?? 0 ),
			'first_name'   => sanitize_text_field( $raw['first_name'] ?? '' ),
			'last_name'    => sanitize_text_field( $raw['last_name'] ?? '' ),
			'email'        => sanitize_email( $raw['email'] ?? '' ),
			'phone'        => preg_replace( '/[^0-9+ ]/', '', sanitize_text_field( $raw['phone'] ?? '' ) ),
			'customer_type'=> 'company' === sanitize_key( $raw['customer_type'] ?? '' ) ? 'company' : 'private',
			'message'      => sanitize_textarea_field( $raw['message'] ?? '' ),
			'start_date'   => $start,
			'end_date'     => $end,
			'pickup_time'  => self::valid_time( $raw['pickup_time'] ?? '' ),
			'return_time'  => self::valid_time( $raw['return_time'] ?? '' ),
			'privacy'      => ! empty( $raw['privacy'] ),
			'terms'        => ! empty( $raw['terms'] ),
			'website'      => sanitize_text_field( $raw['website'] ?? '' ),
			'form_started_at' => absint( $raw['form_started_at'] ?? 0 ),
			'selection'    => self::sanitize_selection( $raw['selection'] ?? array() ),
		);

		if ( ! $input['vehicle_id'] || ! $input['first_name'] || ! $input['last_name'] || ! is_email( $input['email'] ) || strlen( preg_replace( '/\D/', '', $input['phone'] ) ) < 7 ) {
			return new WP_Error( 'customer_invalid', __( 'Inserisci nome, cognome, email e telefono validi.', 'gest-web-rent' ), array( 'field' => 'first_name' ) );
		}
		if ( ! $input['privacy'] ) {
			return new WP_Error( 'privacy_required', __( 'Accetta l informativa privacy per continuare.', 'gest-web-rent' ), array( 'field' => 'privacy' ) );
		}
		if ( gwr_is_request_mode() && ! $input['terms'] ) {
			return new WP_Error( 'terms_required', __( 'Accetta le condizioni di noleggio per inviare la richiesta.', 'gest-web-rent' ), array( 'field' => 'terms' ) );
		}

		return $input;
	}

	/** Keep only public extra and coverage identifiers. */
	private static function sanitize_selection( $selection ) {
		$selection = is_array( $selection ) ? $selection : array();
		$output    = array( 'extras' => array(), 'coverages' => array() );
		foreach ( array( 'extras', 'coverages' ) as $group ) {
			foreach ( is_array( $selection[ $group ] ?? null ) ? $selection[ $group ] : array() as $item ) {
				$id = sanitize_key( is_array( $item ) ? ( $item['id'] ?? '' ) : $item );
				if ( $id ) {
					$output[ $group ][] = 'extras' === $group ? array( 'id' => $id, 'quantity' => max( 1, absint( is_array( $item ) ? ( $item['quantity'] ?? 1 ) : 1 ) ) ) : $id;
				}
			}
		}
		return $output;
	}

	/** Normalize a public HH:MM value. */
	private static function valid_time( $value ) {
		$value = sanitize_text_field( $value );
		return preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
	}

	/** Honeypot, timing and per-IP rate limit. */
	private static function check_antispam( $input ) {
		if ( $input['website'] || ! $input['form_started_at'] || time() - $input['form_started_at'] < 2 ) {
			return new WP_Error( 'request_rejected', __( 'La richiesta non e valida. Riprova.', 'gest-web-rent' ) );
		}
		$ip    = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
		$key   = 'gwr_inquiry_rate_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 );
		$count = absint( get_transient( $key ) );
		if ( $count >= 8 ) {
			return new WP_Error( 'rate_limit', __( 'Troppe richieste. Riprova piu tardi.', 'gest-web-rent' ) );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/** Check public availability without exposing or returning occupied periods. */
	private static function period_available( $vehicle_id, $start_date, $end_date, $exclude_availability_id = 0 ) {
		global $wpdb;
		$sql  = 'SELECT COUNT(*) FROM ' . GWR_CPT::availability_table() . ' WHERE vehicle_id = %d AND start_date <= %s AND end_date >= %s';
		$args = array( absint( $vehicle_id ), $end_date, $start_date );
		if ( $exclude_availability_id ) {
			$sql   .= ' AND id <> %d';
			$args[] = absint( $exclude_availability_id );
		}
		if ( (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) > 0 ) {
			return false;
		}

		if ( class_exists( 'GWR_Bookings' ) ) {
			$pickup       = $start_date . ' 00:00:00';
			$return       = ( new DateTimeImmutable( $end_date, wp_timezone() ) )->modify( '+1 day' )->format( 'Y-m-d 00:00:00' );
			$statuses     = GWR_Bookings::blocking_statuses();
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$query         = 'SELECT COUNT(*) FROM ' . GWR_Bookings::table() . " WHERE vehicle_id=%d AND status IN ({$placeholders}) AND pickup_datetime < %s AND return_datetime > %s";
			$query_args    = array_merge( array( absint( $vehicle_id ) ), $statuses, array( $return, $pickup ) );
			return 0 === (int) $wpdb->get_var( $wpdb->prepare( $query, $query_args ) );
		}
		return true;
	}

	/** Create a temporary or manual availability hold. */
	private static function create_hold( $row ) {
		if ( empty( $row['start_date'] ) || empty( $row['end_date'] ) ) {
			return 0;
		}
		$result = GWR_CPT::save_availability(
			$row['vehicle_id'],
			array(
				'start_date'         => $row['start_date'],
				'end_date'           => $row['end_date'],
				'status'             => 'reserved',
				'internal_note'      => __( 'Blocco generato da richiesta cliente.', 'gest-web-rent' ),
				'external_reference' => 'inquiry:' . $row['request_code'],
			)
		);
		return is_wp_error( $result ) ? 0 : absint( $result );
	}

	/** Send concise administrator and customer emails. */
	private static function send_notifications( $row, $vehicle, $settings ) {
		$dealer_email = sanitize_email( $settings['contact_email'] ?? get_option( 'admin_email' ) );
		$subject      = sprintf( __( 'Nuova richiesta %s - %s', 'gest-web-rent' ), $row['request_code'], $vehicle['title'] );
		$period       = $row['start_date'] ? self::display_date( $row['start_date'] ) . ' - ' . self::display_date( $row['end_date'] ) : __( 'Date da definire', 'gest-web-rent' );
		$body         = sprintf(
			"%s %s\n%s: %s\n%s: %s\n%s: %s\n%s: %s\n\n%s",
			$row['first_name'],
			$row['last_name'],
			__( 'Veicolo', 'gest-web-rent' ),
			$vehicle['title'],
			__( 'Periodo', 'gest-web-rent' ),
			$period,
			__( 'Telefono', 'gest-web-rent' ),
			$row['phone'],
			__( 'Email', 'gest-web-rent' ),
			$row['email'],
			$row['message']
		);
		$headers = array( 'Reply-To: ' . $row['first_name'] . ' ' . $row['last_name'] . ' <' . $row['email'] . '>' );
		$admin_sent = $dealer_email && wp_mail( $dealer_email, $subject, $body, $headers );

		$customer_subject = gwr_is_request_mode() ? __( 'Richiesta di prenotazione ricevuta', 'gest-web-rent' ) : __( 'Richiesta di informazioni ricevuta', 'gest-web-rent' );
		$customer_body    = gwr_is_request_mode()
			? __( 'Abbiamo ricevuto la tua richiesta. Verificheremo disponibilita e condizioni prima di confermarla.', 'gest-web-rent' )
			: __( 'Abbiamo ricevuto la tua richiesta di informazioni e ti ricontatteremo appena possibile.', 'gest-web-rent' );
		wp_mail( $row['email'], $customer_subject, $customer_body );

		return (bool) $admin_sent;
	}

	/** Format one ISO date for customer-facing messages. */
	private static function display_date( $value ) {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $value, wp_timezone() );
		return $date ? $date->format( 'd-m-Y' ) : '';
	}

	/** Whether the requests archive contains any records. */
	public static function has_records() {
		global $wpdb;
		return (bool) $wpdb->get_var( 'SELECT EXISTS(SELECT 1 FROM ' . self::table() . ' LIMIT 1)' );
	}

	/** Admin request statuses. */
	public static function statuses() {
		return array(
			'new'               => __( 'Nuova', 'gest-web-rent' ),
			'pending_review'    => __( 'Da valutare', 'gest-web-rent' ),
			'awaiting_customer' => __( 'In attesa del cliente', 'gest-web-rent' ),
			'proposal_sent'     => __( 'Proposta inviata', 'gest-web-rent' ),
			'approved'          => __( 'Approvata', 'gest-web-rent' ),
			'rejected'          => __( 'Rifiutata', 'gest-web-rent' ),
			'converted'         => __( 'Convertita', 'gest-web-rent' ),
			'archived'          => __( 'Archiviata', 'gest-web-rent' ),
		);
	}

	/** Render the custom admin list. */
	public static function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non hai i permessi necessari.', 'gest-web-rent' ) );
		}
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT i.*, v.title AS vehicle_title FROM ' . self::table() . ' i LEFT JOIN ' . GWR_CPT::vehicles_table() . ' v ON v.id=i.vehicle_id ORDER BY i.created_at DESC LIMIT 200', ARRAY_A );
		GWR_Admin::page_start( 'inquiries', __( 'Richieste clienti', 'gest-web-rent' ), __( 'Richieste informative e richieste di prenotazione da verificare manualmente.', 'gest-web-rent' ) );
		if ( ! empty( $_GET['gwr_message'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['gwr_message'] ) ) ) . '</p></div>';
		}
		echo '<section class="gwr-data-grid-card"><div class="gwr-table-wrap"><table class="widefat"><thead><tr><th>' . esc_html__( 'Codice', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Cliente', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Veicolo e periodo', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Azioni', 'gest-web-rent' ) . '</th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Nessuna richiesta ricevuta.', 'gest-web-rent' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$period = $row['start_date'] ? $row['start_date'] . ' - ' . $row['end_date'] : __( 'Date da definire', 'gest-web-rent' );
			echo '<tr><td><strong>' . esc_html( $row['request_code'] ) . '</strong><br><small>' . esc_html( $row['created_at'] ) . '</small></td><td>' . esc_html( $row['first_name'] . ' ' . $row['last_name'] ) . '<br><a href="mailto:' . esc_attr( $row['email'] ) . '">' . esc_html( $row['email'] ) . '</a><br>' . esc_html( $row['phone'] ) . '</td><td><strong>' . esc_html( $row['vehicle_title'] ?: '#' . $row['vehicle_id'] ) . '</strong><br>' . esc_html( $period ) . '</td><td><span class="gwr-inline-status">' . esc_html( self::statuses()[ $row['status'] ] ?? $row['status'] ) . '</span></td><td><div class="gwr-row-actions">';
			foreach ( self::admin_actions( $row ) as $action => $label ) {
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=gwr_inquiry_action&request_id=' . absint( $row['id'] ) . '&request_action=' . $action ), 'gwr_inquiry_action_' . absint( $row['id'] ) );
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			}
			echo '</div></td></tr>';
		}
		echo '</tbody></table></div></section>';
		GWR_Admin::page_end();
	}

	/** Contextual actions for a request row. */
	private static function admin_actions( $row ) {
		$actions = array(
			'approve'    => __( 'Approva', 'gest-web-rent' ),
			'needs_info' => __( 'Richiedi informazioni', 'gest-web-rent' ),
			'proposal'   => __( 'Invia proposta', 'gest-web-rent' ),
			'reject'     => __( 'Rifiuta', 'gest-web-rent' ),
			'archive'    => __( 'Archivia', 'gest-web-rent' ),
		);
		if ( gwr_is_booking_mode() ) {
			$actions['convert'] = __( 'Converti in prenotazione', 'gest-web-rent' );
		}
		return $actions;
	}

	/** Capability- and nonce-protected admin state transition. */
	public static function handle_admin_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non hai i permessi necessari.', 'gest-web-rent' ) );
		}
		global $wpdb;
		$request_id = absint( $_GET['request_id'] ?? 0 );
		$action     = sanitize_key( $_GET['request_action'] ?? '' );
		check_admin_referer( 'gwr_inquiry_action_' . $request_id );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id=%d', $request_id ), ARRAY_A );
		if ( ! $row ) {
			wp_die( esc_html__( 'Richiesta non trovata.', 'gest-web-rent' ) );
		}

		$status = '';
		if ( 'approve' === $action ) {
			if ( $row['start_date'] && ! self::period_available( $row['vehicle_id'], $row['start_date'], $row['end_date'], $row['availability_id'] ) ) {
				wp_die( esc_html__( 'Il veicolo non e piu disponibile nel periodo richiesto.', 'gest-web-rent' ) );
			}
			$detail_check = GWR_Frontend::get_public_vehicle_details(
				$row['vehicle_id'],
				array(
					'start_date'  => $row['start_date'],
					'end_date'    => $row['end_date'],
					'pickup_time' => $row['pickup_time'] ?: '09:00',
					'return_time' => $row['return_time'] ?: '18:00',
				)
			);
			if ( is_wp_error( $detail_check ) || ( empty( $row['availability_id'] ) && false === ( $detail_check['availability']['available'] ?? true ) ) ) {
				wp_die( esc_html__( 'Disponibilita o condizioni non sono piu valide. Verifica la richiesta prima di approvarla.', 'gest-web-rent' ) );
			}
			if ( empty( $row['availability_id'] ) ) {
				$row['availability_id'] = self::create_hold( $row );
			}
			$status = 'approved';
			wp_mail( $row['email'], __( 'Richiesta approvata', 'gest-web-rent' ), __( 'La tua richiesta e stata approvata. Il noleggiatore ti contattera per completare conferma e condizioni.', 'gest-web-rent' ) );
		} elseif ( 'needs_info' === $action ) {
			$status = 'awaiting_customer';
			wp_mail( $row['email'], __( 'Servono ulteriori informazioni sulla tua richiesta', 'gest-web-rent' ), __( 'Contattaci rispondendo a questa email per completare la valutazione della richiesta.', 'gest-web-rent' ) );
		} elseif ( 'proposal' === $action ) {
			$status = 'proposal_sent';
			wp_mail( $row['email'], __( 'Proposta di noleggio', 'gest-web-rent' ), __( 'Abbiamo valutato la richiesta. Ti contatteremo con la proposta e le condizioni definitive.', 'gest-web-rent' ) );
		} elseif ( 'reject' === $action ) {
			$status = 'rejected';
			self::release_hold( $row );
			wp_mail( $row['email'], __( 'Aggiornamento richiesta di noleggio', 'gest-web-rent' ), __( 'La richiesta non puo essere confermata nelle condizioni indicate. Contattaci per valutare alternative.', 'gest-web-rent' ) );
		} elseif ( 'archive' === $action ) {
			$status = 'archived';
			self::release_hold( $row );
		} elseif ( 'convert' === $action && gwr_is_booking_mode() ) {
			$booking = GWR_Bookings::create_from_inquiry( $row );
			if ( is_wp_error( $booking ) ) {
				wp_die( esc_html( $booking->get_error_message() ) );
			}
			$status = 'converted';
		}
		if ( ! $status ) {
			wp_die( esc_html__( 'Azione non disponibile.', 'gest-web-rent' ) );
		}

		$wpdb->update(
			self::table(),
			array( 'status' => $status, 'availability_id' => absint( $row['availability_id'] ?? 0 ) ?: null, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $request_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
		wp_safe_redirect( add_query_arg( array( 'page' => 'gwr-inquiries', 'gwr_message' => rawurlencode( __( 'Richiesta aggiornata.', 'gest-web-rent' ) ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Remove a request-owned availability row. */
	private static function release_hold( $row ) {
		if ( ! empty( $row['availability_id'] ) ) {
			GWR_CPT::delete_availability( absint( $row['availability_id'] ) );
		}
	}

	/** Expire only temporary request holds, never manual/approved ones. */
	public static function expire_temporary_holds() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE status='pending_review' AND expires_at IS NOT NULL AND expires_at <= %s LIMIT 100", current_time( 'mysql' ) ), ARRAY_A );
		foreach ( $rows as $row ) {
			self::release_hold( $row );
			$wpdb->update( self::table(), array( 'availability_id' => null, 'expires_at' => null, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $row['id'] ), array( '%d', '%s', '%s' ), array( '%d' ) );
		}
	}

	/** Keep cleanup available after mode changes so historical holds cannot linger. */
	private static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}
}
