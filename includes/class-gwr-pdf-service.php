<?php
/**
 * Document cache and printable HTML streaming.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores generated documents as protected printable HTML.
 *
 * The plugin intentionally does not bundle a PDF engine. If a server-side PDF
 * library is added later this class is the single integration point.
 */
class GWR_PDF_Service {
	const DIR_NAME = 'gest-web-rent-documents';

	/**
	 * Cache rendered HTML in a protected upload subdirectory.
	 *
	 * @param int    $document_id Document ID.
	 * @param string $document_number Public document number.
	 * @param string $html Rendered HTML.
	 * @return array|WP_Error
	 */
	public static function cache_html( $document_id, $document_number, $html ) {
		$dir = self::storage_dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$basename = sanitize_file_name( strtolower( $document_number ) );
		if ( '' === $basename ) {
			$basename = 'document-' . absint( $document_id );
		}

		$path = trailingslashit( $dir ) . $basename . '-' . absint( $document_id ) . '.html';
		$written = file_put_contents( $path, (string) $html );
		if ( false === $written ) {
			return new WP_Error( 'gwr_document_cache_failed', __( 'Impossibile salvare la cache del documento.', 'gest-web-rent' ) );
		}

		return array(
			'path' => $path,
			'hash' => hash_file( 'sha256', $path ),
			'mime' => 'text/html',
		);
	}

	/**
	 * Stream printable HTML inline or as download.
	 *
	 * @param array  $document Document row.
	 * @param string $html Fallback HTML if the cache file is missing.
	 * @param bool   $download Send attachment headers.
	 * @return void
	 */
	public static function stream_html( $document, $html = '', $download = false ) {
		$path = isset( $document['file_path'] ) ? (string) $document['file_path'] : '';
		$content = '';
		if ( $path && self::is_path_allowed( $path ) && file_exists( $path ) ) {
			$content = file_get_contents( $path );
		}
		if ( '' === (string) $content ) {
			$content = (string) $html;
		}
		if ( '' === $content ) {
			wp_die( esc_html__( 'Documento non disponibile.', 'gest-web-rent' ) );
		}

		$filename = sanitize_file_name( ( $document['document_number'] ?? 'documento' ) . '.html' );
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		if ( $download ) {
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		}
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Create and protect storage directory.
	 *
	 * @return string|WP_Error
	 */
	public static function storage_dir() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'gwr_upload_dir_failed', $upload['error'] );
		}
		$dir = trailingslashit( $upload['basedir'] ) . self::DIR_NAME;
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'gwr_document_dir_failed', __( 'Impossibile creare la cartella documenti.', 'gest-web-rent' ) );
		}

		self::write_protection_files( $dir );
		return $dir;
	}

	/**
	 * Check that a cached file is inside the protected directory.
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	public static function is_path_allowed( $path ) {
		$dir = self::storage_dir();
		if ( is_wp_error( $dir ) ) {
			return false;
		}
		$real_dir = realpath( $dir );
		$real_path = realpath( $path );
		return $real_dir && $real_path && 0 === strpos( $real_path, $real_dir );
	}

	/**
	 * Write simple web-server deny files.
	 *
	 * @param string $dir Directory.
	 * @return void
	 */
	private static function write_protection_files( $dir ) {
		$files = array(
			'.htaccess' => "Deny from all\n",
			'index.html' => '',
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
		);
		foreach ( $files as $file => $content ) {
			$path = trailingslashit( $dir ) . $file;
			if ( ! file_exists( $path ) ) {
				file_put_contents( $path, $content );
			}
		}
	}
}
