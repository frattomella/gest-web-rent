<?php
/**
 * Protected booking attachments.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles private booking attachments.
 */
class GWR_Attachment_Service {
	const DB_VERSION = '1.9.0';
	const DB_OPTION = 'gwr_attachments_db_version';

	/**
	 * Runtime hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 6 );
		add_action( 'admin_post_gwr_booking_attachment_upload', array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_gwr_customer_attachment_upload', array( __CLASS__, 'handle_customer_upload' ) );
		add_action( 'admin_post_nopriv_gwr_customer_attachment_upload', array( __CLASS__, 'handle_customer_upload' ) );
		add_action( 'admin_post_gwr_booking_attachment_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_gwr_booking_attachment_verify', array( __CLASS__, 'handle_verify' ) );
		add_action( 'admin_post_gwr_booking_attachment_download', array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_nopriv_gwr_booking_attachment_download', array( __CLASS__, 'handle_download' ) );
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'gwr_booking_attachments';
	}

	/**
	 * Create table.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE " . self::table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			document_id bigint(20) unsigned NULL,
			file_path text NOT NULL,
			file_hash varchar(128) NULL,
			mime_type varchar(120) NOT NULL,
			file_size bigint(20) unsigned NOT NULL DEFAULT 0,
			description varchar(255) NULL,
			visibility varchar(32) NOT NULL DEFAULT 'private',
			verification_status varchar(32) NOT NULL DEFAULT 'pending',
			source varchar(32) NOT NULL DEFAULT 'admin',
			uploaded_by bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			deleted_at datetime NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY document_id (document_id),
			KEY visibility (visibility),
			KEY deleted_at (deleted_at)
		) $charset;" );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
		GWR_PDF_Service::storage_dir();
	}

	/**
	 * Lazy upgrade.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}
		self::create_tables();
	}

	/**
	 * Attachments for booking.
	 *
	 * @param int  $booking_id Booking ID.
	 * @param bool $include_private Include private rows.
	 * @return array
	 */
	public static function attachments_for_booking( $booking_id, $include_private = true ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table() . ' WHERE booking_id=%d AND deleted_at IS NULL';
		$values = array( absint( $booking_id ) );
		if ( ! $include_private ) {
			$sql .= " AND visibility='customer'";
		}
		$sql .= ' ORDER BY created_at DESC, id DESC';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null
	 */
	public static function get( $attachment_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id=%d AND deleted_at IS NULL', absint( $attachment_id ) ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Download URL.
	 *
	 * @param array $attachment Attachment.
	 * @param array $args Extra args.
	 * @return string
	 */
	public static function download_url( $attachment, $args = array() ) {
		$params = array_merge( array( 'action' => 'gwr_booking_attachment_download', 'attachment_id' => absint( $attachment['id'] ) ), $args );
		$url = admin_url( 'admin-post.php?' . http_build_query( $params ) );
		if ( current_user_can( 'manage_options' ) && empty( $args ) ) {
			$url = wp_nonce_url( $url, 'gwr_booking_attachment_download_' . absint( $attachment['id'] ) );
		}
		return $url;
	}

	/**
	 * Signed public attachment URL.
	 *
	 * @param array $attachment Attachment.
	 * @param int   $ttl TTL.
	 * @return string
	 */
	public static function signed_download_url( $attachment, $ttl = DAY_IN_SECONDS ) {
		$expires = time() + absint( $ttl );
		return self::download_url(
			$attachment,
			array(
				'gwr_expires' => $expires,
				'gwr_signature' => self::signature_for_url( $attachment, $expires ),
			)
		);
	}

	/**
	 * Upload handler.
	 *
	 * @return void
	 */
	public static function handle_upload() {
		self::require_admin();
		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		check_admin_referer( 'gwr_booking_attachment_upload_' . $booking_id );
		$result = self::upload( $booking_id, $_FILES['gwr_attachment_file'] ?? array(), $_POST );
		self::redirect_to_booking( $booking_id, $result, __( 'Allegato caricato.', 'gest-web-rent' ) );
	}

	/** Secure upload from the customer portal. */
	public static function handle_customer_upload() {
		global $wpdb;
		$code = sanitize_text_field( wp_unslash( $_POST['booking_code'] ?? '' ) );
		$token = sanitize_text_field( wp_unslash( $_POST['gwr_token'] ?? '' ) );
		$booking_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GWR_Bookings::table() . ' WHERE booking_code=%s', $code ) );
		$booking = $booking_id ? GWR_Bookings::get( $booking_id ) : null;
		if ( ! $booking || ! GWR_Bookings::verify_public_token( $booking, $token ) ) {
			wp_die( esc_html__( 'Accesso prenotazione non valido.', 'gest-web-rent' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'gwr_customer_attachment_upload_' . $booking_id, 'gwr_upload_nonce' );
		$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
		$rate_key = 'gwr_upload_rate_' . substr( hash_hmac( 'sha256', $ip . '|' . $booking_id, wp_salt( 'nonce' ) ), 0, 32 );
		$count = absint( get_transient( $rate_key ) );
		if ( $count >= 10 ) { $result = new WP_Error( 'gwr_upload_rate', __( 'Limite upload raggiunto. Riprova piu tardi.', 'gest-web-rent' ) ); }
		else {
			set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );
			$active_count = count( self::attachments_for_booking( $booking_id, true ) );
			$result = $active_count >= 20 ? new WP_Error( 'gwr_upload_count', __( 'Numero massimo di allegati raggiunto.', 'gest-web-rent' ) ) : self::upload( $booking_id, $_FILES['gwr_attachment_file'] ?? array(), array( 'description' => sanitize_text_field( wp_unslash( $_POST['description'] ?? __( 'Documento cliente', 'gest-web-rent' ) ) ), 'visibility' => 'private', 'source' => 'customer' ) );
		}
		$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Documento inviato e in attesa di verifica.', 'gest-web-rent' );
		$url = add_query_arg( array( 'booking_code' => $code, 'gwr_token' => $token, 'gwr_portal_message' => rawurlencode( $message ), 'gwr_portal_error' => is_wp_error( $result ) ? 1 : 0 ), wp_get_referer() ?: home_url( '/prenotazione/' ) );
		wp_safe_redirect( $url ); exit;
	}

	/**
	 * Soft-delete handler.
	 *
	 * @return void
	 */
	public static function handle_delete() {
		self::require_admin();
		$attachment_id = absint( $_GET['attachment_id'] ?? 0 );
		check_admin_referer( 'gwr_booking_attachment_delete_' . $attachment_id );
		$attachment = self::get( $attachment_id );
		$result = self::delete( $attachment_id );
		self::redirect_to_booking( $attachment['booking_id'] ?? 0, $result, __( 'Allegato eliminato.', 'gest-web-rent' ) );
	}

	public static function handle_verify() {
		self::require_admin();
		$attachment_id = absint( $_GET['attachment_id'] ?? 0 );
		check_admin_referer( 'gwr_booking_attachment_verify_' . $attachment_id );
		$attachment = self::get( $attachment_id );
		$status = sanitize_key( $_GET['verification_status'] ?? '' );
		if ( $attachment && in_array( $status, array( 'pending', 'reviewing', 'approved', 'rejected' ), true ) ) {
			global $wpdb; $wpdb->update( self::table(), array( 'verification_status' => $status ), array( 'id' => $attachment_id ) );
			GWR_Bookings::add_system_log( $attachment['booking_id'], 'attachment_' . $status, $attachment['description'] );
			if ( 'rejected' === $status && class_exists( 'GWR_Notification_Service' ) ) { GWR_Notification_Service::send_event( GWR_Bookings::get( $attachment['booking_id'] ), 'document_rejected', array( 'reference' => 'attachment:' . $attachment_id . ':rejected' ) ); }
		}
		self::redirect_to_booking( $attachment['booking_id'] ?? 0, (bool) $attachment, __( 'Stato allegato aggiornato.', 'gest-web-rent' ) );
	}

	/**
	 * Download handler.
	 *
	 * @return void
	 */
	public static function handle_download() {
		$attachment = self::get( absint( $_GET['attachment_id'] ?? 0 ) );
		if ( ! $attachment || ! self::authorize_access( $attachment ) ) {
			wp_die( esc_html__( 'Allegato non autorizzato.', 'gest-web-rent' ) );
		}
		if ( empty( $attachment['file_path'] ) || ! GWR_PDF_Service::is_path_allowed( $attachment['file_path'] ) || ! file_exists( $attachment['file_path'] ) ) {
			wp_die( esc_html__( 'File non disponibile.', 'gest-web-rent' ) );
		}
		$filename = sanitize_file_name( $attachment['description'] ?: basename( $attachment['file_path'] ) );
		if ( false === strpos( $filename, '.' ) ) {
			$filename .= self::extension_from_mime( $attachment['mime_type'] );
		}
		nocache_headers();
		header( 'Content-Type: ' . sanitize_text_field( $attachment['mime_type'] ) );
		header( 'Content-Length: ' . absint( filesize( $attachment['file_path'] ) ) );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		readfile( $attachment['file_path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Store uploaded attachment in protected directory.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $file File array.
	 * @param array $raw Raw request.
	 * @return int|WP_Error
	 */
	public static function upload( $booking_id, $file, $raw = array() ) {
		global $wpdb;
		$booking = GWR_Bookings::get( absint( $booking_id ) );
		if ( ! $booking ) {
			return new WP_Error( 'gwr_attachment_booking_missing', __( 'Prenotazione non trovata.', 'gest-web-rent' ) );
		}
		$settings = GWR_Document_Service::get_settings();
		if ( empty( $settings['attachments_enabled'] ) ) {
			return new WP_Error( 'gwr_attachments_disabled', __( 'Allegati non abilitati.', 'gest-web-rent' ) );
		}
		if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) ) {
			return new WP_Error( 'gwr_attachment_upload_failed', __( 'Upload non valido.', 'gest-web-rent' ) );
		}
		$max_bytes = max( 1, absint( $settings['max_upload_mb'] ?? 8 ) ) * MB_IN_BYTES;
		if ( absint( $file['size'] ?? 0 ) > $max_bytes ) {
			return new WP_Error( 'gwr_attachment_too_big', __( 'File troppo grande.', 'gest-web-rent' ) );
		}
		$allowed = array(
			'pdf' => 'application/pdf',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
		);
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], sanitize_file_name( $file['name'] ?? '' ), $allowed );
		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			return new WP_Error( 'gwr_attachment_type_invalid', __( 'Sono ammessi solo PDF, JPG e PNG.', 'gest-web-rent' ) );
		}
		$sample = file_get_contents( $file['tmp_name'], false, null, 0, 4096 );
		if ( false === $sample || preg_match( '/<\?(?:php|=)|<script|__halt_compiler|^MZ/i', $sample ) ) {
			return new WP_Error( 'gwr_attachment_content_invalid', __( 'Il contenuto del file non e sicuro.', 'gest-web-rent' ) );
		}
		$dir = GWR_PDF_Service::storage_dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}
		$filename = 'attachment-' . absint( $booking_id ) . '-' . wp_generate_uuid4() . '.' . sanitize_key( $checked['ext'] );
		$path = trailingslashit( $dir ) . $filename;
		if ( ! move_uploaded_file( $file['tmp_name'], $path ) ) {
			return new WP_Error( 'gwr_attachment_move_failed', __( 'Impossibile salvare allegato.', 'gest-web-rent' ) );
		}
		$visibility = 'customer' === sanitize_key( $raw['visibility'] ?? '' ) ? 'customer' : 'private';
		$wpdb->insert(
			self::table(),
			array(
				'booking_id' => absint( $booking_id ),
				'document_id' => absint( $raw['document_id'] ?? 0 ) ?: null,
				'file_path' => $path,
				'file_hash' => hash_file( 'sha256', $path ),
				'mime_type' => sanitize_text_field( $checked['type'] ),
				'file_size' => absint( filesize( $path ) ),
				'description' => sanitize_text_field( wp_unslash( $raw['description'] ?? '' ) ),
				'visibility' => $visibility,
				'verification_status' => 'customer' === sanitize_key( $raw['source'] ?? '' ) ? 'pending' : 'approved',
				'source' => 'customer' === sanitize_key( $raw['source'] ?? '' ) ? 'customer' : 'admin',
				'uploaded_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql' ),
			)
		);
		GWR_Bookings::add_system_log( $booking_id, 'attachment_uploaded', sanitize_text_field( $raw['description'] ?? $filename ) );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Soft delete.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool|WP_Error
	 */
	public static function delete( $attachment_id ) {
		global $wpdb;
		$attachment = self::get( $attachment_id );
		if ( ! $attachment ) {
			return new WP_Error( 'gwr_attachment_not_found', __( 'Allegato non trovato.', 'gest-web-rent' ) );
		}
		$wpdb->update( self::table(), array( 'deleted_at' => current_time( 'mysql' ) ), array( 'id' => absint( $attachment_id ) ) );
		GWR_Bookings::add_system_log( $attachment['booking_id'], 'attachment_deleted', $attachment['description'] ?: basename( (string) $attachment['file_path'] ) );
		return true;
	}

	/**
	 * Access control.
	 *
	 * @param array $attachment Attachment.
	 * @return bool
	 */
	private static function authorize_access( $attachment ) {
		if ( current_user_can( 'manage_options' ) ) {
			if ( ! empty( $_GET['_wpnonce'] ) ) {
				return (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gwr_booking_attachment_download_' . absint( $attachment['id'] ) );
			}
			return true;
		}
		$expires = absint( $_GET['gwr_expires'] ?? 0 );
		$sig = sanitize_text_field( wp_unslash( $_GET['gwr_signature'] ?? '' ) );
		if ( $expires && $sig && $expires >= time() && hash_equals( self::signature_for_url( $attachment, $expires ), $sig ) ) {
			return true;
		}
		if ( 'customer' !== $attachment['visibility'] ) {
			return false;
		}
		$booking = GWR_Bookings::get( $attachment['booking_id'] );
		$code = sanitize_text_field( wp_unslash( $_GET['booking_code'] ?? '' ) );
		$token = sanitize_text_field( wp_unslash( $_GET['gwr_token'] ?? ( $_GET['booking_token'] ?? '' ) ) );
		return $booking && $code === $booking['booking_code'] && GWR_Bookings::verify_public_token( $booking, $token );
	}

	private static function signature_for_url( $attachment, $expires ) {
		return hash_hmac( 'sha256', absint( $attachment['id'] ) . '|' . absint( $expires ) . '|' . ( $attachment['file_hash'] ?? '' ), wp_salt( 'auth' ) );
	}

	private static function extension_from_mime( $mime ) {
		$map = array( 'application/pdf' => '.pdf', 'image/jpeg' => '.jpg', 'image/png' => '.png' );
		return $map[ $mime ] ?? '';
	}

	private static function require_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) );
		}
	}

	private static function redirect_to_booking( $booking_id, $result, $success_message ) {
		$error = is_wp_error( $result );
		$message = $error ? $result->get_error_message() : $success_message;
		wp_safe_redirect( add_query_arg( array( 'page' => 'gwr-booking-detail', 'booking_id' => absint( $booking_id ), 'gwr_message' => $message, 'gwr_error' => $error ? 1 : 0 ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
