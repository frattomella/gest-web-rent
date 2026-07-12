<?php
/**
 * Centralized customer communications, queue and reminders.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

class GWR_Notification_Service {
	const DB_VERSION = '1.9.0';
	const DB_OPTION = 'gwr_notifications_db_version';
	const OPTION_NAME = 'gwr_notification_settings';
	const CRON_HOOK = 'gwr_process_notifications';
	const LOCK_KEY = 'gwr_notification_queue_lock';
	private static $runtime_tokens = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 6 );
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_cron' ) );
		add_action( 'admin_post_gwr_notifications_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_gwr_notification_test', array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_gwr_notification_action', array( __CLASS__, 'handle_queue_action' ) );
		self::schedule();
	}

	public static function queue_table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_notification_queue';
	}

	public static function logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_notification_logs';
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE " . self::queue_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			event_type varchar(80) NOT NULL,
			event_reference varchar(120) NOT NULL DEFAULT '',
			recipient varchar(190) NOT NULL,
			channel varchar(30) NOT NULL DEFAULT 'email',
			template_version int unsigned NOT NULL DEFAULT 1,
			payload_json longtext NULL,
			idempotency_key varchar(128) NOT NULL,
			status varchar(30) NOT NULL DEFAULT 'pending',
			attempts smallint unsigned NOT NULL DEFAULT 0,
			scheduled_at datetime NOT NULL,
			sent_at datetime NULL,
			last_error text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY booking_status (booking_id,status),
			KEY scheduled_status (scheduled_at,status)
		) $charset;" );
		dbDelta( "CREATE TABLE " . self::logs_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			queue_id bigint(20) unsigned NULL,
			booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event_type varchar(80) NOT NULL,
			channel varchar(30) NOT NULL DEFAULT 'email',
			recipient_masked varchar(190) NOT NULL,
			subject varchar(255) NULL,
			status varchar(30) NOT NULL,
			attempt smallint unsigned NOT NULL DEFAULT 1,
			error_message text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY event_type (event_type),
			KEY status_created (status,created_at)
		) $charset;" );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults(), '', false );
		}
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_OPTION ) !== self::DB_VERSION ) {
			self::create_tables();
		}
	}

	public static function defaults() {
		$company = class_exists( 'GWR_Admin' ) ? GWR_Admin::get_settings() : array();
		return array(
			'enabled' => 1,
			'from_name' => $company['dealer_name'] ?? get_bloginfo( 'name' ),
			'from_email' => $company['contact_email'] ?? get_option( 'admin_email' ),
			'reply_to' => $company['contact_email'] ?? get_option( 'admin_email' ),
			'portal_url' => home_url( '/prenotazione/' ),
			'portal_token_days' => 365,
			'max_attempts' => 3,
			'batch_size' => 20,
			'retention_days' => 730,
			'reminders' => array(
				'unpaid_hours' => 24,
				'pickup_hours' => 48,
				'return_hours' => 24,
				'enabled' => 1,
			),
			'templates' => self::default_templates(),
		);
	}

	public static function settings() {
		$value = get_option( self::OPTION_NAME, array() );
		return array_replace_recursive( self::defaults(), is_array( $value ) ? $value : array() );
	}

	public static function events() {
		return array(
			'booking_created' => __( 'Prenotazione creata', 'gest-web-rent' ),
			'booking_pending_confirmation' => __( 'Prenotazione in valutazione', 'gest-web-rent' ),
			'booking_confirmed' => __( 'Prenotazione confermata', 'gest-web-rent' ),
			'booking_rejected' => __( 'Prenotazione rifiutata', 'gest-web-rent' ),
			'booking_cancelled' => __( 'Prenotazione annullata', 'gest-web-rent' ),
			'booking_expired' => __( 'Prenotazione scaduta', 'gest-web-rent' ),
			'payment_requested' => __( 'Pagamento richiesto', 'gest-web-rent' ),
			'payment_succeeded' => __( 'Pagamento riuscito', 'gest-web-rent' ),
			'payment_failed' => __( 'Pagamento non riuscito', 'gest-web-rent' ),
			'payment_refunded' => __( 'Pagamento rimborsato', 'gest-web-rent' ),
			'payment_partially_refunded' => __( 'Pagamento parzialmente rimborsato', 'gest-web-rent' ),
			'bank_transfer_instructions' => __( 'Istruzioni bonifico', 'gest-web-rent' ),
			'booking_reminder' => __( 'Promemoria pagamento', 'gest-web-rent' ),
			'pickup_reminder' => __( 'Promemoria ritiro', 'gest-web-rent' ),
			'return_reminder' => __( 'Promemoria riconsegna', 'gest-web-rent' ),
			'document_available' => __( 'Documento disponibile', 'gest-web-rent' ),
			'document_rejected' => __( 'Documento rifiutato', 'gest-web-rent' ),
			'change_request_received' => __( 'Richiesta modifica ricevuta', 'gest-web-rent' ),
			'change_request_approved' => __( 'Richiesta modifica approvata', 'gest-web-rent' ),
			'change_request_rejected' => __( 'Richiesta modifica rifiutata', 'gest-web-rent' ),
			'cancellation_request_received' => __( 'Richiesta annullamento ricevuta', 'gest-web-rent' ),
		);
	}

	private static function default_templates() {
		$templates = array();
		foreach ( self::events() as $event => $label ) {
			$templates[ $event ] = array(
				'enabled' => 1,
				'version' => 1,
				'language' => 'it_IT',
				'subject' => '{{company_name}} - ' . $label . ' {{booking_code}}',
				'title' => $label,
				'html' => '<p>Ciao {{customer_name}},</p><p>' . esc_html( $label ) . ' per la prenotazione <strong>{{booking_code}}</strong>.</p><p>Veicolo: {{vehicle_name}}<br>Periodo: {{pickup_datetime}} - {{return_datetime}}<br>Stato: {{booking_status}}</p><p>Totale: {{total}} - Pagato: {{paid_amount}} - Saldo: {{balance}}</p><p><a href="{{booking_url}}">Apri la tua prenotazione</a></p>',
				'text' => $label . " - {{booking_code}}\n{{vehicle_name}}\n{{pickup_datetime}} - {{return_datetime}}\n{{booking_url}}",
			);
		}
		return $templates;
	}

	public static function queue( $booking, $event, $args = array() ) {
		global $wpdb;
		$booking = is_array( $booking ) ? $booking : GWR_Bookings::get( absint( $booking ) );
		$event = sanitize_key( $event );
		if ( ! $booking || ! isset( self::events()[ $event ] ) ) {
			return new WP_Error( 'gwr_notification_invalid', __( 'Comunicazione non valida.', 'gest-web-rent' ) );
		}
		$settings = self::settings();
		$template = $settings['templates'][ $event ] ?? array();
		if ( empty( $settings['enabled'] ) || empty( $template['enabled'] ) ) {
			return new WP_Error( 'gwr_notification_disabled', __( 'Comunicazione disattivata.', 'gest-web-rent' ) );
		}
		$recipient = sanitize_email( $args['recipient'] ?? $booking['customer_email'] );
		if ( ! is_email( $recipient ) ) {
			return new WP_Error( 'gwr_notification_email_invalid', __( 'Email destinatario non configurata.', 'gest-web-rent' ) );
		}
		$reference = sanitize_text_field( $args['reference'] ?? $booking['updated_at'] ?? $booking['created_at'] );
		$manual = ! empty( $args['manual'] );
		$key_source = absint( $booking['id'] ) . '|' . $event . '|' . $reference . '|' . strtolower( $recipient );
		if ( $manual ) {
			$key_source .= '|' . wp_generate_uuid4();
		}
		$key = hash( 'sha256', $key_source );
		$runtime_token = sanitize_text_field( $args['booking_token'] ?? $booking['public_token'] ?? '' );
		$payload = array(
			'extra' => is_array( $args['extra'] ?? null ) ? $args['extra'] : array(),
			'attachments' => array_values( array_filter( array_map( 'sanitize_text_field', is_array( $args['attachments'] ?? null ) ? $args['attachments'] : array() ) ) ),
		);
		$now = current_time( 'mysql' );
		$scheduled = sanitize_text_field( $args['scheduled_at'] ?? $now );
		$inserted = $wpdb->insert( self::queue_table(), array(
			'booking_id' => absint( $booking['id'] ), 'event_type' => $event, 'event_reference' => $reference,
			'recipient' => $recipient, 'channel' => 'email', 'template_version' => absint( $template['version'] ?? 1 ),
			'payload_json' => wp_json_encode( $payload ), 'idempotency_key' => $key, 'status' => 'pending',
			'attempts' => 0, 'scheduled_at' => $scheduled, 'created_at' => $now, 'updated_at' => $now,
		) );
		if ( false === $inserted ) {
			return new WP_Error( 'gwr_notification_duplicate', __( 'Comunicazione gia pianificata.', 'gest-web-rent' ) );
		}
		$queue_id = (int) $wpdb->insert_id;
		if ( $runtime_token ) { self::$runtime_tokens[ $queue_id ] = $runtime_token; }
		return $queue_id;
	}

	public static function send_event( $booking, $event, $args = array() ) {
		$id = self::queue( $booking, $event, $args );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return self::process_one( $id );
	}

	public static function send_custom( $booking, $subject, $html, $attachments = array(), $event = 'document_available' ) {
		$booking = is_array( $booking ) ? $booking : GWR_Bookings::get( absint( $booking ) );
		if ( ! $booking || ! is_email( $booking['customer_email'] ) ) {
			return false;
		}
		return self::deliver( $booking, sanitize_text_field( $subject ), wp_kses_post( $html ), $booking['customer_email'], $attachments, $event, 0, 1 );
	}

	public static function process_queue( $limit = 0 ) {
		global $wpdb;
		if ( get_transient( self::LOCK_KEY ) ) {
			return 0;
		}
		set_transient( self::LOCK_KEY, 1, 10 * MINUTE_IN_SECONDS );
		$settings = self::settings();
		$limit = $limit ?: max( 1, min( 100, absint( $settings['batch_size'] ) ) );
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::queue_table() . " WHERE status='pending' AND scheduled_at<=%s ORDER BY scheduled_at ASC,id ASC LIMIT %d", current_time( 'mysql' ), $limit ) );
		$count = 0;
		foreach ( $ids as $id ) {
			if ( self::process_one( $id ) ) {
				$count++;
			}
		}
		delete_transient( self::LOCK_KEY );
		return $count;
	}

	public static function process_one( $queue_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::queue_table() . ' WHERE id=%d', absint( $queue_id ) ), ARRAY_A );
		if ( ! $row || ! in_array( $row['status'], array( 'pending', 'failed' ), true ) ) {
			return false;
		}
		$settings = self::settings();
		$attempt = absint( $row['attempts'] ) + 1;
		if ( $attempt > max( 1, absint( $settings['max_attempts'] ) ) ) {
			$wpdb->update( self::queue_table(), array( 'status' => 'failed', 'last_error' => __( 'Numero massimo tentativi raggiunto.', 'gest-web-rent' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $queue_id ) );
			return false;
		}
		$wpdb->update( self::queue_table(), array( 'status' => 'processing', 'attempts' => $attempt, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $queue_id ) );
		$booking = GWR_Bookings::get( $row['booking_id'] );
		$payload = json_decode( (string) $row['payload_json'], true );
		$payload = is_array( $payload ) ? $payload : array();
		if ( ! empty( self::$runtime_tokens[ $queue_id ] ) ) { $payload['booking_token'] = self::$runtime_tokens[ $queue_id ]; }
		$template = $settings['templates'][ $row['event_type'] ] ?? array();
		$tokens = self::tokens( $booking, $payload );
		$subject = self::render_template( $template['subject'] ?? '', $tokens, false );
		$html = self::render_template( $template['html'] ?? '', $tokens, true );
		$sent = $booking && self::deliver( $booking, $subject, $html, $row['recipient'], $payload['attachments'] ?? array(), $row['event_type'], $queue_id, $attempt );
		if ( $sent ) {
			unset( self::$runtime_tokens[ $queue_id ] );
			$wpdb->update( self::queue_table(), array( 'status' => 'sent', 'sent_at' => current_time( 'mysql' ), 'last_error' => '', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $queue_id ) );
			return true;
		}
		$max = max( 1, absint( $settings['max_attempts'] ) );
		$status = $attempt >= $max ? 'failed' : 'pending';
		$delay = min( DAY_IN_SECONDS, HOUR_IN_SECONDS * (int) pow( 2, max( 0, $attempt - 1 ) ) );
		$wpdb->update( self::queue_table(), array( 'status' => $status, 'last_error' => __( 'wp_mail ha restituito false.', 'gest-web-rent' ), 'scheduled_at' => wp_date( 'Y-m-d H:i:s', time() + $delay ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $queue_id ) );
		return false;
	}

	private static function deliver( $booking, $subject, $html, $recipient, $attachments, $event, $queue_id, $attempt ) {
		global $wpdb;
		$settings = self::settings();
		$from_email = sanitize_email( $settings['from_email'] );
		$reply_to = sanitize_email( $settings['reply_to'] );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $from_email ) ) {
			$headers[] = 'From: ' . sanitize_text_field( $settings['from_name'] ) . ' <' . $from_email . '>';
		}
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		$attachments = array_values( array_filter( $attachments, function( $path ) { return is_string( $path ) && file_exists( $path ) && GWR_PDF_Service::is_path_allowed( $path ); } ) );
		$sent = wp_mail( sanitize_email( $recipient ), $subject, $html, $headers, $attachments );
		$wpdb->insert( self::logs_table(), array(
			'queue_id' => $queue_id ?: null, 'booking_id' => absint( $booking['id'] ?? 0 ), 'event_type' => sanitize_key( $event ),
			'channel' => 'email', 'recipient_masked' => self::mask_email( $recipient ), 'subject' => $subject,
			'status' => $sent ? 'sent' : 'failed', 'attempt' => absint( $attempt ), 'error_message' => $sent ? '' : __( 'Invio email non riuscito.', 'gest-web-rent' ), 'created_at' => current_time( 'mysql' ),
		) );
		return (bool) $sent;
	}

	public static function tokens( $booking, $payload = array() ) {
		$settings = self::settings();
		$company = GWR_Admin::get_settings();
		$customer = $booking['customer'] ?? array();
		$paid = self::paid_amount( $booking['id'] ?? 0 );
		$total = (float) ( $booking['total_amount'] ?? 0 );
		$token = sanitize_text_field( $payload['booking_token'] ?? '' );
		$url = add_query_arg( array( 'booking_code' => $booking['booking_code'] ?? '', 'gwr_token' => $token ), $settings['portal_url'] );
		return array(
			'booking_code' => $booking['booking_code'] ?? '', 'booking_status' => GWR_Bookings::statuses()[ $booking['status'] ?? '' ] ?? '',
			'customer_name' => trim( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) ), 'customer_email' => $booking['customer_email'] ?? '',
			'vehicle_name' => $booking['vehicle_title'] ?? '', 'pickup_location' => $booking['pickup_location'] ?? '', 'return_location' => $booking['return_location'] ?? '',
			'pickup_datetime' => GWR_Bookings::format_datetime( $booking['pickup_datetime'] ?? '' ), 'return_datetime' => GWR_Bookings::format_datetime( $booking['return_datetime'] ?? '' ), 'rental_days' => absint( $booking['rental_days'] ?? 0 ),
			'total' => GWR_Bookings::format_money( $total, $booking['currency'] ?? 'EUR' ), 'pay_now' => GWR_Bookings::format_money( $booking['pay_now_amount'] ?? 0, $booking['currency'] ?? 'EUR' ),
			'paid_amount' => GWR_Bookings::format_money( $paid, $booking['currency'] ?? 'EUR' ), 'balance' => GWR_Bookings::format_money( max( 0, $total - $paid ), $booking['currency'] ?? 'EUR' ),
			'deposit' => GWR_Bookings::format_money( $booking['deposit_amount'] ?? 0, $booking['currency'] ?? 'EUR' ), 'payment_method' => $booking['payment_method'] ?? '',
			'booking_url' => $token ? esc_url_raw( $url ) : esc_url_raw( $settings['portal_url'] ), 'payment_url' => $token ? esc_url_raw( $url . '#gwr-payment' ) : '',
			'company_name' => $company['dealer_name'] ?? get_bloginfo( 'name' ), 'company_phone' => $company['whatsapp_number'] ?? '', 'company_email' => $company['contact_email'] ?? '',
		);
	}

	private static function render_template( $template, $tokens, $html ) {
		$replace = array();
		foreach ( $tokens as $key => $value ) {
			$replace[ '{{' . $key . '}}' ] = $html ? esc_html( (string) $value ) : sanitize_text_field( (string) $value );
		}
		$output = strtr( (string) $template, $replace );
		$output = preg_replace( '/\{\{[a-z0-9_]+\}\}/i', '', $output );
		return $html ? wp_kses_post( $output ) : sanitize_text_field( $output );
	}

	public static function logs_for_booking( $booking_id, $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::logs_table() . ' WHERE booking_id=%d ORDER BY created_at DESC,id DESC LIMIT %d', absint( $booking_id ), max( 1, min( 200, absint( $limit ) ) ) ), ARRAY_A );
	}

	public static function queue_for_booking( $booking_id, $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::queue_table() . ' WHERE booking_id=%d ORDER BY created_at DESC,id DESC LIMIT %d', absint( $booking_id ), max( 1, min( 200, absint( $limit ) ) ) ), ARRAY_A );
	}

	private static function paid_amount( $booking_id ) {
		global $wpdb;
		if ( ! $booking_id || ! class_exists( 'GWR_Payment_Service' ) ) {
			return 0.0;
		}
		$minor = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(amount-amount_refunded),0) FROM ' . GWR_Payment_Service::table() . " WHERE booking_id=%d AND status IN ('paid','partially_refunded')", absint( $booking_id ) ) );
		return $minor / 100;
	}

	public static function cron_schedules( $schedules ) {
		$schedules['gwr_every_fifteen_minutes'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => __( 'Ogni 15 minuti', 'gest-web-rent' ) );
		return $schedules;
	}

	public static function schedule() {
		if ( wp_next_scheduled( 'gwr_expire_pending_bookings' ) ) { wp_clear_scheduled_hook( 'gwr_expire_pending_bookings' ); }
		if ( wp_next_scheduled( 'gwr_reconcile_payments' ) ) { wp_clear_scheduled_hook( 'gwr_reconcile_payments' ); }
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'gwr_every_fifteen_minutes', self::CRON_HOOK );
		}
	}

	public static function run_cron() {
		GWR_Bookings::expire_pending();
		GWR_Payment_Service::reconcile_pending_payments();
		self::schedule_reminders();
		self::process_queue();
		self::cleanup();
	}

	private static function schedule_reminders() {
		global $wpdb;
		$settings = self::settings();
		if ( empty( $settings['reminders']['enabled'] ) ) {
			return;
		}
		$now = time();
		$pickup_until = wp_date( 'Y-m-d H:i:s', $now + max( 1, absint( $settings['reminders']['pickup_hours'] ) ) * HOUR_IN_SECONDS );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id FROM ' . GWR_Bookings::table() . " WHERE status IN ('confirmed','delivered','in_progress') AND pickup_datetime BETWEEN %s AND %s ORDER BY pickup_datetime ASC LIMIT 50", current_time( 'mysql' ), $pickup_until ), ARRAY_A );
		foreach ( $rows as $row ) {
			$booking = GWR_Bookings::get( $row['id'] );
			self::queue( $booking, 'pickup_reminder', array( 'reference' => substr( $booking['pickup_datetime'], 0, 13 ) ) );
		}
		$return_until = wp_date( 'Y-m-d H:i:s', $now + max( 1, absint( $settings['reminders']['return_hours'] ) ) * HOUR_IN_SECONDS );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id FROM ' . GWR_Bookings::table() . " WHERE status IN ('delivered','in_progress') AND return_datetime BETWEEN %s AND %s ORDER BY return_datetime ASC LIMIT 50", current_time( 'mysql' ), $return_until ), ARRAY_A );
		foreach ( $rows as $row ) { $booking = GWR_Bookings::get( $row['id'] ); self::queue( $booking, 'return_reminder', array( 'reference' => substr( $booking['return_datetime'], 0, 13 ) ) ); }
		$unpaid_before = wp_date( 'Y-m-d H:i:s', $now - max( 1, absint( $settings['reminders']['unpaid_hours'] ) ) * HOUR_IN_SECONDS );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id FROM ' . GWR_Bookings::table() . " WHERE status IN ('pending','awaiting_payment','payment_failed') AND created_at<=%s ORDER BY created_at ASC LIMIT 50", $unpaid_before ), ARRAY_A );
		foreach ( $rows as $row ) {
			$booking = GWR_Bookings::get( $row['id'] );
			self::queue( $booking, 'booking_reminder', array( 'reference' => wp_date( 'Y-m-d' ) ) );
		}
	}

	private static function cleanup() {
		global $wpdb;
		$days = max( 30, absint( self::settings()['retention_days'] ) );
		$before = wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::queue_table() . " WHERE status IN ('sent','cancelled') AND updated_at<%s LIMIT 200", $before ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::logs_table() . ' WHERE created_at<%s LIMIT 200', $before ) );
	}

	private static function mask_email( $email ) {
		$parts = explode( '@', (string) $email, 2 );
		if ( 2 !== count( $parts ) ) { return '***'; }
		return substr( $parts[0], 0, 1 ) . '***@' . $parts[1];
	}

	public static function booking_panel( $booking ) {
		$queue = self::queue_for_booking( $booking['id'], 30 );
		$logs = self::logs_for_booking( $booking['id'], 30 );
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><div><h2>' . esc_html__( 'Comunicazioni', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Coda, invii e tentativi della prenotazione.', 'gest-web-rent' ) . '</p></div></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-inline-form"><input type="hidden" name="action" value="gwr_notification_action" /><input type="hidden" name="booking_id" value="' . esc_attr( $booking['id'] ) . '" /><input type="hidden" name="queue_action" value="manual" />'; wp_nonce_field( 'gwr_notification_action_' . $booking['id'] );
		echo '<select name="event_type">'; foreach ( self::events() as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; } echo '</select><button class="button button-primary">' . esc_html__( 'Invia manualmente', 'gest-web-rent' ) . '</button></form>';
		echo '<div class="gwr-table-wrap"><table class="widefat"><thead><tr><th>' . esc_html__( 'Data', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Evento', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Destinatario', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Tentativi', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Errore', 'gest-web-rent' ) . '</th></tr></thead><tbody>';
		foreach ( $queue as $row ) { echo '<tr><td>' . esc_html( GWR_Bookings::format_datetime( $row['created_at'] ) ) . '</td><td>' . esc_html( self::events()[ $row['event_type'] ] ?? $row['event_type'] ) . '</td><td>' . esc_html( self::mask_email( $row['recipient'] ) ) . '</td><td>' . esc_html( $row['status'] ) . '</td><td>' . esc_html( $row['attempts'] ) . '</td><td>' . esc_html( $row['last_error'] ) . '</td></tr>'; }
		if ( ! $queue && ! $logs ) { echo '<tr><td colspan="6">' . esc_html__( 'Nessuna comunicazione registrata.', 'gest-web-rent' ) . '</td></tr>'; }
		echo '</tbody></table></div></section>';
	}

	public static function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) ); }
		$settings = self::settings();
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT q.*,b.booking_code FROM ' . self::queue_table() . ' q LEFT JOIN ' . GWR_Bookings::table() . ' b ON b.id=q.booking_id ORDER BY q.created_at DESC LIMIT 50', ARRAY_A );
		GWR_Admin::page_start( 'communications', __( 'Comunicazioni', 'gest-web-rent' ), __( 'Template email, mittente, promemoria, coda e diagnostica.', 'gest-web-rent' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-settings-form"><input type="hidden" name="action" value="gwr_notifications_save" />';
		wp_nonce_field( 'gwr_notifications_save' );
		echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Mittente e pianificazione', 'gest-web-rent' ) . '</h2><div class="gwr-field-grid">';
		foreach ( array( 'from_name' => __( 'Nome mittente', 'gest-web-rent' ), 'from_email' => __( 'Email mittente', 'gest-web-rent' ), 'reply_to' => __( 'Reply-to', 'gest-web-rent' ), 'portal_url' => __( 'URL area cliente', 'gest-web-rent' ), 'portal_token_days' => __( 'Validita link cliente (giorni)', 'gest-web-rent' ), 'max_attempts' => __( 'Tentativi massimi', 'gest-web-rent' ), 'batch_size' => __( 'Dimensione batch', 'gest-web-rent' ) ) as $key => $label ) {
			echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html( $label ) . '</span><input name="settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $settings[ $key ] ) . '" /></label>';
		}
		echo '</div><label class="gwr-check-card"><input type="checkbox" name="settings[enabled]" value="1" ' . checked( ! empty( $settings['enabled'] ), true, false ) . ' /><span>' . esc_html__( 'Invio email attivo', 'gest-web-rent' ) . '</span></label></section>';
		echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Promemoria', 'gest-web-rent' ) . '</h2><div class="gwr-field-grid">';
		foreach ( array( 'unpaid_hours' => __( 'Ore prima del sollecito pagamento', 'gest-web-rent' ), 'pickup_hours' => __( 'Ore prima del ritiro', 'gest-web-rent' ), 'return_hours' => __( 'Ore prima della riconsegna', 'gest-web-rent' ) ) as $key => $label ) { echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html( $label ) . '</span><input type="number" min="1" max="720" name="settings[reminders][' . esc_attr( $key ) . ']" value="' . esc_attr( $settings['reminders'][ $key ] ) . '" /></label>'; }
		echo '</div><label class="gwr-check-card"><input type="checkbox" name="settings[reminders][enabled]" value="1" ' . checked( ! empty( $settings['reminders']['enabled'] ), true, false ) . ' /><span>' . esc_html__( 'Promemoria automatici attivi', 'gest-web-rent' ) . '</span></label></section>';
		$wa = preg_replace( '/\D+/', '', (string) ( GWR_Admin::get_settings()['whatsapp_number'] ?? '' ) );
		echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'WhatsApp', 'gest-web-rent' ) . '</h2><p>' . esc_html( $wa ? __( 'Numero configurato. I messaggi sono aperti tramite link wa.me e non inviati automaticamente.', 'gest-web-rent' ) : __( 'Numero non configurato: i pulsanti WhatsApp restano nascosti senza bloccare le email.', 'gest-web-rent' ) ) . '</p></section>';
		echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Template email', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Token ammessi: booking_code, booking_status, customer_name, customer_email, vehicle_name, pickup_location, return_location, pickup_datetime, return_datetime, rental_days, total, pay_now, paid_amount, balance, deposit, payment_method, booking_url, payment_url, company_name, company_phone, company_email.', 'gest-web-rent' ) . '</p>';
		foreach ( self::events() as $event => $label ) {
			$template = $settings['templates'][ $event ];
			echo '<details class="gwr-communication-template"><summary>' . esc_html( $label ) . '</summary><label class="gwr-check-card"><input type="checkbox" name="settings[templates][' . esc_attr( $event ) . '][enabled]" value="1" ' . checked( ! empty( $template['enabled'] ), true, false ) . ' /><span>' . esc_html__( 'Attivo', 'gest-web-rent' ) . '</span></label><input type="hidden" name="settings[templates][' . esc_attr( $event ) . '][version]" value="' . esc_attr( absint( $template['version'] ) ) . '" /><label class="gwr-field"><span>' . esc_html__( 'Lingua predisposta', 'gest-web-rent' ) . '</span><input name="settings[templates][' . esc_attr( $event ) . '][language]" value="' . esc_attr( $template['language'] ?? 'it_IT' ) . '" /></label><label class="gwr-field"><span>' . esc_html__( 'Oggetto', 'gest-web-rent' ) . '</span><input name="settings[templates][' . esc_attr( $event ) . '][subject]" value="' . esc_attr( $template['subject'] ) . '" /></label><label class="gwr-field gwr-field--wide"><span>' . esc_html__( 'Corpo HTML controllato', 'gest-web-rent' ) . '</span><textarea rows="6" name="settings[templates][' . esc_attr( $event ) . '][html]">' . esc_textarea( $template['html'] ) . '</textarea></label><label class="gwr-field gwr-field--wide"><span>' . esc_html__( 'Corpo testuale', 'gest-web-rent' ) . '</span><textarea rows="4" name="settings[templates][' . esc_attr( $event ) . '][text]">' . esc_textarea( $template['text'] ) . '</textarea></label></details>';
		}
		echo '</section><button class="button button-primary button-hero">' . esc_html__( 'Salva comunicazioni', 'gest-web-rent' ) . '</button></form>';
		echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Coda recente', 'gest-web-rent' ) . '</h2><div class="gwr-table-wrap"><table class="widefat"><thead><tr><th>' . esc_html__( 'Data', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Prenotazione', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Evento', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Destinatario', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Tentativi', 'gest-web-rent' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) { echo '<tr><td>' . esc_html( GWR_Bookings::format_datetime( $row['created_at'] ) ) . '</td><td>' . esc_html( $row['booking_code'] ) . '</td><td>' . esc_html( self::events()[ $row['event_type'] ] ?? $row['event_type'] ) . '</td><td>' . esc_html( self::mask_email( $row['recipient'] ) ) . '</td><td>' . esc_html( $row['status'] ) . '</td><td>' . esc_html( $row['attempts'] ) . '</td></tr>'; }
		echo '</tbody></table></div></section>';
		GWR_Admin::page_end();
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) ); }
		check_admin_referer( 'gwr_notifications_save' );
		$raw = is_array( $_POST['settings'] ?? null ) ? wp_unslash( $_POST['settings'] ) : array();
		$old = self::settings();
		$out = $old;
		$out['enabled'] = ! empty( $raw['enabled'] ) ? 1 : 0;
		$out['from_name'] = sanitize_text_field( $raw['from_name'] ?? '' );
		$out['from_email'] = sanitize_email( $raw['from_email'] ?? '' );
		$out['reply_to'] = sanitize_email( $raw['reply_to'] ?? '' );
		$out['portal_url'] = esc_url_raw( $raw['portal_url'] ?? home_url( '/prenotazione/' ) );
		$out['portal_token_days'] = max( 1, min( 730, absint( $raw['portal_token_days'] ?? 365 ) ) );
		$out['max_attempts'] = max( 1, min( 10, absint( $raw['max_attempts'] ?? 3 ) ) );
		$out['batch_size'] = max( 1, min( 100, absint( $raw['batch_size'] ?? 20 ) ) );
		$out['reminders']['enabled'] = ! empty( $raw['reminders']['enabled'] ) ? 1 : 0;
		foreach ( array( 'unpaid_hours', 'pickup_hours', 'return_hours' ) as $key ) { $out['reminders'][ $key ] = max( 1, min( 720, absint( $raw['reminders'][ $key ] ?? $old['reminders'][ $key] ) ) ); }
		foreach ( self::events() as $event => $label ) {
			$posted = $raw['templates'][ $event ] ?? array();
			$out['templates'][ $event ] = array( 'enabled' => ! empty( $posted['enabled'] ) ? 1 : 0, 'version' => absint( $old['templates'][ $event ]['version'] ?? 1 ) + 1, 'language' => sanitize_text_field( $posted['language'] ?? 'it_IT' ), 'subject' => sanitize_text_field( $posted['subject'] ?? '' ), 'title' => $label, 'html' => wp_kses_post( $posted['html'] ?? '' ), 'text' => sanitize_textarea_field( $posted['text'] ?? '' ) );
		}
		update_option( self::OPTION_NAME, $out, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'gwr-communications', 'gwr_message' => rawurlencode( __( 'Comunicazioni salvate.', 'gest-web-rent' ) ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_test() { wp_die( esc_html__( 'Usa un invio manuale dalla prenotazione per testare dati reali.', 'gest-web-rent' ) ); }
	public static function handle_queue_action() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) ); }
		$booking_id = absint( $_POST['booking_id'] ?? 0 ); check_admin_referer( 'gwr_notification_action_' . $booking_id );
		$booking = GWR_Bookings::get( $booking_id ); $event = sanitize_key( $_POST['event_type'] ?? '' );
		$result = $booking ? self::send_event( $booking, $event, array( 'manual' => true, 'reference' => 'manual:' . current_time( 'mysql' ) ) ) : new WP_Error( 'gwr_booking_missing', __( 'Prenotazione non trovata.', 'gest-web-rent' ) );
		$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Comunicazione inviata.', 'gest-web-rent' );
		wp_safe_redirect( add_query_arg( array( 'page' => 'gwr-booking-detail', 'booking_id' => $booking_id, 'gwr_message' => rawurlencode( $message ), 'gwr_error' => is_wp_error( $result ) ? 1 : 0 ), admin_url( 'admin.php' ) ) ); exit;
	}
}
