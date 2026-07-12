<?php
/**
 * Shared booking document templates.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders professional printable A4 HTML documents.
 */
class GWR_Document_Template_Service {
	const TEMPLATE_VERSION = '2026-07-phase7-html';

	/**
	 * Render document HTML.
	 *
	 * @param string $type Document type.
	 * @param array  $data Normalized snapshot data.
	 * @param array  $settings Document settings.
	 * @param array  $meta Document metadata.
	 * @return string
	 */
	public static function render( $type, $data, $settings, $meta ) {
		$types = GWR_Document_Service::document_types();
		$label = $types[ $type ]['label'] ?? __( 'Documento prenotazione', 'gest-web-rent' );
		$title = $label . ' - ' . ( $data['booking']['code'] ?? '' );
		$logo = ! empty( $settings['logo_id'] ) ? wp_get_attachment_image_url( absint( $settings['logo_id'] ), 'medium' ) : '';

		ob_start();
		echo '<!doctype html><html><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $title ) . '</title>';
		echo '<style>' . self::css() . '</style></head><body><main class="gwr-doc">';
		echo '<button type="button" class="gwr-doc-print" onclick="window.print()">' . esc_html__( 'Stampa / salva PDF', 'gest-web-rent' ) . '</button>';
		echo '<header class="gwr-doc-head"><div class="gwr-doc-brand">';
		if ( $logo ) {
			echo '<img src="' . esc_url( $logo ) . '" alt="" />';
		}
		echo '<div><strong>' . esc_html( self::company_name( $settings ) ) . '</strong>' . self::company_lines( $settings ) . '</div></div>';
		echo '<div class="gwr-doc-meta"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $meta['document_number'] ?? '' ) . '</strong><small>' . esc_html( sprintf( __( 'Versione %s - generato il %s', 'gest-web-rent' ), $meta['version'] ?? '1', $meta['generated_at'] ?? '' ) ) . '</small></div></header>';
		echo '<section class="gwr-doc-title"><p>' . esc_html( GWR_Document_Service::document_intro( $type, $settings, $data ) ) . '</p><h1>' . esc_html( $label ) . '</h1></section>';

		self::booking_block( $data );
		self::people_block( $data );
		self::vehicle_block( $data );
		self::pricing_block( $data, $type );
		self::operation_block( $data, $type );
		self::payments_block( $data, $type );
		self::terms_block( $data, $type, $settings );
		self::signature_block( $type, $data );

		echo '<footer class="gwr-doc-foot"><p>' . esc_html( $settings['documents_footer'] ?? '' ) . '</p><p>' . esc_html__( 'Documento non fiscale. Non sostituisce fattura, ricevuta fiscale o documento contabile.', 'gest-web-rent' ) . '</p><p>' . esc_html( sprintf( __( 'Template %s - hash snapshot %s', 'gest-web-rent' ), self::TEMPLATE_VERSION, substr( (string) ( $meta['content_hash'] ?? '' ), 0, 12 ) ) ) . '</p></footer>';
		echo '</main></body></html>';
		return ob_get_clean();
	}

	/**
	 * Compact print CSS.
	 *
	 * @return string
	 */
	private static function css() {
		return 'body{margin:0;background:#eef2f7;color:#111827;font:12px/1.5 Arial,Helvetica,sans-serif}.gwr-doc{width:210mm;min-height:297mm;margin:18px auto;padding:18mm;background:#fff;box-sizing:border-box;box-shadow:0 18px 45px rgba(15,23,42,.12)}.gwr-doc-print{position:fixed;right:18px;top:18px;z-index:5;padding:9px 14px;border:0;border-radius:6px;background:#173787;color:#fff;font-weight:700;cursor:pointer}.gwr-doc-head{display:flex;justify-content:space-between;gap:20px;padding-bottom:14px;border-bottom:2px solid #173787}.gwr-doc-brand{display:flex;gap:12px;align-items:flex-start}.gwr-doc-brand img{max-width:110px;max-height:70px;object-fit:contain}.gwr-doc-brand strong{display:block;font-size:18px;color:#102a63}.gwr-doc-brand small{display:block;color:#526070}.gwr-doc-meta{text-align:right}.gwr-doc-meta span{display:block;color:#526070;text-transform:uppercase;letter-spacing:.08em;font-size:10px;font-weight:700}.gwr-doc-meta strong{display:block;font-size:18px;color:#102a63}.gwr-doc-meta small{display:block;color:#526070}.gwr-doc-title{margin:18px 0}.gwr-doc-title p{margin:0 0 5px;color:#526070}.gwr-doc-title h1{margin:0;color:#111827;font-size:26px;line-height:1.1}.gwr-doc-section{break-inside:avoid;margin:13px 0;padding:13px;border:1px solid #dbe4f0;border-radius:7px}.gwr-doc-section h2{margin:0 0 10px;color:#102a63;font-size:15px}.gwr-doc-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px}.gwr-doc-row{display:flex;justify-content:space-between;gap:14px;padding:7px 0;border-bottom:1px solid #edf1f6}.gwr-doc-row:last-child{border-bottom:0}.gwr-doc-row span{color:#526070}.gwr-doc-row strong{text-align:right;color:#111827}.gwr-doc-table{width:100%;border-collapse:collapse}.gwr-doc-table th,.gwr-doc-table td{padding:8px;border-bottom:1px solid #edf1f6;text-align:left;vertical-align:top}.gwr-doc-table th{color:#102a63;background:#f7faff}.gwr-doc-note{padding:10px;border-radius:6px;background:#f6f8fc;color:#384255;white-space:pre-wrap}.gwr-doc-signatures{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px}.gwr-doc-signature{min-height:70px;border-bottom:1px solid #111827;padding-top:38px;color:#526070}.gwr-doc-foot{margin-top:18px;padding-top:12px;border-top:1px solid #dbe4f0;color:#667085;font-size:10px}.gwr-doc-foot p{margin:4px 0}@media print{body{background:#fff}.gwr-doc{width:auto;min-height:0;margin:0;padding:12mm;box-shadow:none}.gwr-doc-print{display:none}@page{size:A4;margin:0}}';
	}

	private static function company_name( $settings ) {
		return trim( (string) ( $settings['company_trade_name'] ?: $settings['company_legal_name'] ?: get_bloginfo( 'name' ) ) );
	}

	private static function company_lines( $settings ) {
		$lines = array_filter( array(
			trim( (string) ( $settings['address'] ?? '' ) . ' ' . ( $settings['postal_code'] ?? '' ) . ' ' . ( $settings['city'] ?? '' ) . ' ' . ( $settings['province'] ?? '' ) ),
			trim( __( 'P. IVA', 'gest-web-rent' ) . ' ' . ( $settings['vat_number'] ?? '' ) . ' ' . __( 'CF', 'gest-web-rent' ) . ' ' . ( $settings['tax_code'] ?? '' ) ),
			trim( ( $settings['phone'] ?? '' ) . ' ' . ( $settings['email'] ?? '' ) . ' ' . ( $settings['website'] ?? '' ) ),
		) );
		$html = '';
		foreach ( $lines as $line ) {
			$html .= '<small>' . esc_html( $line ) . '</small>';
		}
		return $html;
	}

	private static function booking_block( $data ) {
		$booking = $data['booking'] ?? array();
		self::section( __( 'Prenotazione', 'gest-web-rent' ), array(
			__( 'Codice', 'gest-web-rent' ) => $booking['code'] ?? '',
			__( 'Stato', 'gest-web-rent' ) => $booking['status_label'] ?? '',
			__( 'Periodo', 'gest-web-rent' ) => trim( ( $booking['pickup_datetime'] ?? '' ) . ' - ' . ( $booking['return_datetime'] ?? '' ) ),
			__( 'Localita', 'gest-web-rent' ) => trim( ( $booking['pickup_location'] ?? '' ) . ' - ' . ( $booking['return_location'] ?? '' ) ),
			__( 'Creata il', 'gest-web-rent' ) => $booking['created_at'] ?? '',
		) );
	}

	private static function people_block( $data ) {
		echo '<section class="gwr-doc-section"><h2>' . esc_html__( 'Cliente e conducente', 'gest-web-rent' ) . '</h2><div class="gwr-doc-grid">';
		echo '<div>' . self::rows( $data['customer'] ?? array(), array( 'name', 'email', 'phone', 'tax_code', 'company', 'vat_number', 'address' ) ) . '</div>';
		echo '<div>' . self::rows( $data['driver'] ?? array(), array( 'name', 'birth_date', 'license_type', 'license_number', 'license_country', 'license_expiry_date' ) ) . '</div>';
		echo '</div></section>';
	}

	private static function vehicle_block( $data ) {
		$vehicle = $data['vehicle'] ?? array();
		self::section( __( 'Veicolo', 'gest-web-rent' ), array(
			__( 'Veicolo', 'gest-web-rent' ) => $vehicle['title'] ?? '',
			__( 'Targa', 'gest-web-rent' ) => $vehicle['plate'] ?? '',
			__( 'Categoria', 'gest-web-rent' ) => $vehicle['category'] ?? '',
			__( 'Alimentazione', 'gest-web-rent' ) => $vehicle['fuel'] ?? '',
			__( 'Cambio', 'gest-web-rent' ) => $vehicle['transmission'] ?? '',
			__( 'Km inclusi', 'gest-web-rent' ) => $vehicle['included_km'] ?? '',
			__( 'Cauzione', 'gest-web-rent' ) => $data['pricing']['deposit'] ?? '',
		) );
	}

	private static function pricing_block( $data, $type ) {
		$pricing = $data['pricing'] ?? array();
		echo '<section class="gwr-doc-section"><h2>' . esc_html__( 'Importi prenotazione', 'gest-web-rent' ) . '</h2><table class="gwr-doc-table"><tbody>';
		foreach ( array( 'base' => __( 'Noleggio base', 'gest-web-rent' ), 'extras' => __( 'Extra', 'gest-web-rent' ), 'coverages' => __( 'Coperture', 'gest-web-rent' ), 'supplements' => __( 'Supplementi', 'gest-web-rent' ), 'discounts' => __( 'Sconti', 'gest-web-rent' ), 'taxes' => __( 'Tasse', 'gest-web-rent' ), 'total' => __( 'Totale', 'gest-web-rent' ), 'pay_now' => __( 'Pagato / da pagare ora', 'gest-web-rent' ), 'pay_later' => __( 'Saldo al ritiro', 'gest-web-rent' ) ) as $key => $label ) {
			if ( '' !== (string) ( $pricing[ $key ] ?? '' ) ) {
				echo '<tr><td>' . esc_html( $label ) . '</td><td><strong>' . esc_html( $pricing[ $key ] ) . '</strong></td></tr>';
			}
		}
		echo '</tbody></table>';
		if ( 'payment_summary' === $type ) {
			echo '<p class="gwr-doc-note">' . esc_html__( 'Riepilogo operativo dei pagamenti della prenotazione. Non e un documento fiscale.', 'gest-web-rent' ) . '</p>';
		}
		if ( ! empty( $data['items'] ) ) {
			echo '<table class="gwr-doc-table"><thead><tr><th>' . esc_html__( 'Voce', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Tipo', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Quantita', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Totale', 'gest-web-rent' ) . '</th></tr></thead><tbody>';
			foreach ( $data['items'] as $item ) {
				echo '<tr><td>' . esc_html( $item['name'] ?? '' ) . '</td><td>' . esc_html( $item['type'] ?? '' ) . '</td><td>' . esc_html( $item['quantity'] ?? '' ) . '</td><td>' . esc_html( $item['total'] ?? '' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section>';
	}

	private static function operation_block( $data, $type ) {
		if ( ! in_array( $type, array( 'pickup_report', 'return_report' ), true ) ) {
			return;
		}
		$key = 'pickup_report' === $type ? 'delivery' : 'return';
		$title = 'pickup_report' === $type ? __( 'Verbale consegna', 'gest-web-rent' ) : __( 'Verbale riconsegna', 'gest-web-rent' );
		$operation = $data[ $key ] ?? array();
		self::section( $title, array(
			__( 'Data effettiva', 'gest-web-rent' ) => $operation['effective_datetime'] ?? '',
			__( 'Km', 'gest-web-rent' ) => $operation['odometer'] ?? '',
			__( 'Carburante', 'gest-web-rent' ) => $operation['fuel_level'] ?? '',
			__( 'Deposito verificato', 'gest-web-rent' ) => $operation['deposit_verified'] ?? '',
			__( 'Danni / rilievi', 'gest-web-rent' ) => $operation['damage'] ?? '',
			__( 'Note', 'gest-web-rent' ) => $operation['notes'] ?? '',
		) );
	}

	private static function payments_block( $data, $type ) {
		if ( 'payment_summary' !== $type && empty( $data['payment'] ) ) {
			return;
		}
		$payment = $data['payment'] ?? array();
		self::section( __( 'Pagamento', 'gest-web-rent' ), array(
			__( 'Metodo', 'gest-web-rent' ) => $payment['method'] ?? '',
			__( 'Stato', 'gest-web-rent' ) => $payment['status'] ?? '',
			__( 'Tipo', 'gest-web-rent' ) => $payment['payment_type'] ?? '',
			__( 'Importo', 'gest-web-rent' ) => $payment['amount'] ?? '',
			__( 'Tentativo', 'gest-web-rent' ) => $payment['attempt'] ?? '',
			__( 'Aggiornato', 'gest-web-rent' ) => $payment['updated_at'] ?? '',
		) );
	}

	private static function terms_block( $data, $type, $settings ) {
		$notes = array_filter( array(
			$settings['legal_text'] ?? '',
			$data['booking']['customer_notes'] ?? '',
			$data['booking']['admin_notes'] ?? '',
		) );
		$clauses = isset( $settings['contract_clauses'] ) && is_array( $settings['contract_clauses'] ) ? array_filter( $settings['contract_clauses'] ) : array();
		if ( empty( $notes ) && empty( $clauses ) && ! in_array( $type, array( 'rental_contract', 'cancellation_confirmation' ), true ) ) {
			return;
		}
		echo '<section class="gwr-doc-section"><h2>' . esc_html__( 'Condizioni e note', 'gest-web-rent' ) . '</h2>';
		if ( 'rental_contract' === $type ) {
			echo '<p class="gwr-doc-note">' . esc_html__( 'Bozza contrattuale operativa: verificare sempre testi, clausole e conformita con un professionista prima dell uso.', 'gest-web-rent' ) . '</p>';
		}
		foreach ( $notes as $note ) {
			echo '<p class="gwr-doc-note">' . esc_html( $note ) . '</p>';
		}
		if ( ! empty( $clauses ) ) {
			echo '<ol>';
			foreach ( $clauses as $clause ) {
				echo '<li>' . esc_html( $clause ) . '</li>';
			}
			echo '</ol>';
		}
		echo '</section>';
	}

	private static function signature_block( $type, $data ) {
		if ( ! in_array( $type, array( 'rental_contract', 'pickup_report', 'return_report' ), true ) ) {
			return;
		}
		echo '<section class="gwr-doc-section"><h2>' . esc_html__( 'Firme', 'gest-web-rent' ) . '</h2><div class="gwr-doc-signatures"><div class="gwr-doc-signature">' . esc_html__( 'Firma cliente/conducente', 'gest-web-rent' ) . '</div><div class="gwr-doc-signature">' . esc_html__( 'Firma operatore', 'gest-web-rent' ) . '</div></div></section>';
	}

	private static function section( $title, $rows ) {
		echo '<section class="gwr-doc-section"><h2>' . esc_html( $title ) . '</h2>' . self::rows_from_labels( $rows ) . '</section>';
	}

	private static function rows_from_labels( $rows ) {
		$html = '';
		foreach ( $rows as $label => $value ) {
			if ( '' === trim( (string) $value ) ) {
				continue;
			}
			$html .= '<div class="gwr-doc-row"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
		}
		return $html ?: '<p class="gwr-doc-note">' . esc_html__( 'Dato non disponibile nello snapshot.', 'gest-web-rent' ) . '</p>';
	}

	private static function rows( $source, $keys ) {
		$labels = array(
			'name' => __( 'Nome', 'gest-web-rent' ),
			'email' => __( 'Email', 'gest-web-rent' ),
			'phone' => __( 'Telefono', 'gest-web-rent' ),
			'tax_code' => __( 'Codice fiscale', 'gest-web-rent' ),
			'company' => __( 'Azienda', 'gest-web-rent' ),
			'vat_number' => __( 'P. IVA', 'gest-web-rent' ),
			'address' => __( 'Indirizzo', 'gest-web-rent' ),
			'birth_date' => __( 'Nascita', 'gest-web-rent' ),
			'license_type' => __( 'Patente', 'gest-web-rent' ),
			'license_number' => __( 'Numero patente', 'gest-web-rent' ),
			'license_country' => __( 'Rilascio', 'gest-web-rent' ),
			'license_expiry_date' => __( 'Scadenza patente', 'gest-web-rent' ),
		);
		$rows = array();
		foreach ( $keys as $key ) {
			$rows[ $labels[ $key ] ?? $key ] = $source[ $key ] ?? '';
		}
		return self::rows_from_labels( $rows );
	}
}
