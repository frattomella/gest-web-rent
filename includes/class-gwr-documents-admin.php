<?php
/**
 * Admin UI for booking documents.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Document settings, archive and booking panel.
 */
class GWR_Documents_Admin {
	/**
	 * Runtime hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_gwr_save_document_settings', array( __CLASS__, 'handle_save_settings' ) );
	}

	/**
	 * Archive/settings page.
	 *
	 * @return void
	 */
	public static function page() {
		self::require_admin();
		$tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'archive' ) );
		GWR_Admin::page_start(
			'documents',
			__( 'Documenti prenotazione', 'gest-web-rent' ),
			__( 'Voucher, conferme, contratti, verbali, allegati e impostazioni dei documenti generati dalle prenotazioni.', 'gest-web-rent' ),
			array(
				array( 'label' => __( 'Prenotazioni', 'gest-web-rent' ), 'url' => admin_url( 'admin.php?page=gwr-bookings' ) ),
			)
		);
		self::notice();
		echo '<nav class="gwr-terms-tabs"><a class="' . esc_attr( 'archive' === $tab ? 'is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=gwr-documents&tab=archive' ) ) . '">' . esc_html__( 'Archivio', 'gest-web-rent' ) . '</a><a class="' . esc_attr( 'settings' === $tab ? 'is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=gwr-documents&tab=settings' ) ) . '">' . esc_html__( 'Impostazioni', 'gest-web-rent' ) . '</a></nav>';
		if ( 'settings' === $tab ) {
			self::settings_form();
		} else {
			self::archive_table();
		}
		GWR_Admin::page_end();
	}

	/**
	 * Booking detail panel.
	 *
	 * @param array $booking Booking row.
	 * @return void
	 */
	public static function booking_documents_panel( $booking ) {
		if ( ! $booking ) {
			return;
		}
		$documents = GWR_Document_Service::documents_for_booking( $booking['id'] );
		$latest_by_type = array();
		foreach ( $documents as $document ) {
			if ( empty( $latest_by_type[ $document['document_type'] ] ) ) {
				$latest_by_type[ $document['document_type'] ] = $document;
			}
		}

		echo '<section id="gwr-booking-documents" class="gwr-data-grid-card gwr-documents-panel"><div class="gwr-data-grid-card__head"><div><h2>' . esc_html__( 'Documenti', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Genera documenti versionati da snapshot, scaricali in modo protetto e registra firme/allegati della pratica.', 'gest-web-rent' ) . '</p></div></div>';
		echo '<div class="gwr-document-type-grid">';
		foreach ( GWR_Document_Service::document_types() as $type => $config ) {
			self::document_type_card( $booking, $type, $config, $latest_by_type[ $type ] ?? null );
		}
		echo '</div>';
		self::attachments_panel( $booking );
		echo '</section>';
	}

	/**
	 * Save document settings.
	 *
	 * @return void
	 */
	public static function handle_save_settings() {
		self::require_admin();
		check_admin_referer( 'gwr_save_document_settings', 'gwr_document_settings_nonce' );
		GWR_Document_Service::save_settings( $_POST['gwr_document_settings'] ?? array() );
		wp_safe_redirect( add_query_arg( array( 'page' => 'gwr-documents', 'tab' => 'settings', 'gwr_message' => __( 'Impostazioni documenti salvate.', 'gest-web-rent' ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render one document card on booking detail.
	 *
	 * @param array      $booking Booking.
	 * @param string     $type Type.
	 * @param array      $config Config.
	 * @param array|null $document Latest document.
	 * @return void
	 */
	private static function document_type_card( $booking, $type, $config, $document ) {
		$status_label = $document ? ( GWR_Document_Service::statuses()[ $document['status'] ] ?? $document['status'] ) : __( 'Non generato', 'gest-web-rent' );
		echo '<article class="gwr-document-card"><header><div><h3>' . esc_html( $config['label'] ) . '</h3>';
		if ( $document ) {
			echo '<p><code>' . esc_html( $document['document_number'] ) . '</code> v' . esc_html( $document['version'] ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Pronto per la prima generazione.', 'gest-web-rent' ) . '</p>';
		}
		echo '</div><span class="gwr-inline-status is-' . esc_attr( $document['status'] ?? 'draft' ) . '">' . esc_html( $status_label ) . '</span></header>';
		echo '<div class="gwr-document-actions">';
		self::generate_form( $booking['id'], $type, $document );
		if ( $document ) {
			echo '<a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="' . esc_url( GWR_Document_Service::document_url( $document, 'preview' ) ) . '">' . esc_html__( 'Anteprima', 'gest-web-rent' ) . '</a>';
			echo '<a class="button button-secondary" href="' . esc_url( GWR_Document_Service::document_url( $document, 'download' ) ) . '">' . esc_html__( 'Download', 'gest-web-rent' ) . '</a>';
			self::document_action_form( 'gwr_document_send', $document, __( 'Invia', 'gest-web-rent' ) );
			if ( ! in_array( $document['status'], array( 'signed', 'invalidated', 'archived' ), true ) ) {
				self::document_action_form( 'gwr_document_invalidate', $document, __( 'Invalida', 'gest-web-rent' ), true );
			}
			if ( 'archived' !== $document['status'] ) {
				self::document_action_form( 'gwr_document_archive', $document, __( 'Archivia', 'gest-web-rent' ) );
			}
		}
		echo '</div>';
		if ( $document && ! empty( $config['signable'] ) ) {
			self::signature_form( $document );
		}
		echo '</article>';
	}

	/**
	 * Generate/regenerate form.
	 *
	 * @param int        $booking_id Booking ID.
	 * @param string     $type Type.
	 * @param array|null $document Existing document.
	 * @return void
	 */
	private static function generate_form( $booking_id, $type, $document = null ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gwr_generate_document" /><input type="hidden" name="booking_id" value="' . esc_attr( $booking_id ) . '" /><input type="hidden" name="document_type" value="' . esc_attr( $type ) . '" />';
		if ( $document ) {
			echo '<input type="hidden" name="force" value="1" />';
		}
		wp_nonce_field( 'gwr_generate_document_' . absint( $booking_id ) . '_' . sanitize_key( $type ) );
		echo '<button class="button button-primary">' . esc_html( $document ? __( 'Rigenera', 'gest-web-rent' ) : __( 'Genera', 'gest-web-rent' ) ) . '</button></form>';
	}

	/**
	 * Generic document action form.
	 *
	 * @param string $action Action.
	 * @param array  $document Document.
	 * @param string $label Label.
	 * @param bool   $danger Danger.
	 * @return void
	 */
	private static function document_action_form( $action, $document, $label, $danger = false ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $action ) . '" /><input type="hidden" name="document_id" value="' . esc_attr( $document['id'] ) . '" />';
		wp_nonce_field( 'gwr_document_action_' . absint( $document['id'] ) );
		echo '<button class="button ' . esc_attr( $danger ? 'button-link-delete' : 'button-secondary' ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Signature capture form.
	 *
	 * @param array $document Document.
	 * @return void
	 */
	private static function signature_form( $document ) {
		if ( 'signed' === $document['status'] ) {
			echo '<p class="gwr-muted">' . esc_html__( 'Documento firmato: genera una nuova versione per modifiche successive.', 'gest-web-rent' ) . '</p>';
			return;
		}
		echo '<details class="gwr-signature-details"><summary>' . esc_html__( 'Acquisisci firma semplice', 'gest-web-rent' ) . '</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-signature-form" data-gwr-signature-pad><input type="hidden" name="action" value="gwr_document_sign" /><input type="hidden" name="document_id" value="' . esc_attr( $document['id'] ) . '" />';
		wp_nonce_field( 'gwr_document_action_' . absint( $document['id'] ) );
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Nome firmatario', 'gest-web-rent' ) . '</span><input type="text" name="signer_name" required /></label><label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Ruolo', 'gest-web-rent' ) . '</span><select name="signer_role"><option value="customer">' . esc_html__( 'Cliente/conducente', 'gest-web-rent' ) . '</option><option value="operator">' . esc_html__( 'Operatore', 'gest-web-rent' ) . '</option></select></label><canvas width="620" height="180" class="gwr-signature-canvas"></canvas><input type="hidden" name="signature_data" data-gwr-signature-data /><div class="gwr-inline-actions"><button type="button" class="button button-secondary" data-gwr-clear-signature>' . esc_html__( 'Pulisci firma', 'gest-web-rent' ) . '</button><button class="button button-primary">' . esc_html__( 'Salva firma', 'gest-web-rent' ) . '</button></div><p class="gwr-muted">' . esc_html__( 'Firma elettronica semplice collegata a hash documento e snapshot. Non e firma digitale qualificata.', 'gest-web-rent' ) . '</p></form></details>';
	}

	/**
	 * Attachments panel.
	 *
	 * @param array $booking Booking.
	 * @return void
	 */
	private static function attachments_panel( $booking ) {
		if ( ! class_exists( 'GWR_Attachment_Service' ) ) {
			return;
		}
		$settings = GWR_Document_Service::get_settings();
		$attachments = GWR_Attachment_Service::attachments_for_booking( $booking['id'] );
		echo '<div class="gwr-attachments-panel"><h3>' . esc_html__( 'Allegati pratica', 'gest-web-rent' ) . '</h3>';
		if ( ! empty( $settings['attachments_enabled'] ) ) {
			echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-attachment-upload"><input type="hidden" name="action" value="gwr_booking_attachment_upload" /><input type="hidden" name="booking_id" value="' . esc_attr( $booking['id'] ) . '" />';
			wp_nonce_field( 'gwr_booking_attachment_upload_' . absint( $booking['id'] ) );
			echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'File', 'gest-web-rent' ) . '</span><input type="file" name="gwr_attachment_file" accept=".pdf,.jpg,.jpeg,.png" required /></label><label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Descrizione', 'gest-web-rent' ) . '</span><input type="text" name="description" /></label><label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Visibilita', 'gest-web-rent' ) . '</span><select name="visibility"><option value="private">' . esc_html__( 'Solo admin', 'gest-web-rent' ) . '</option><option value="customer">' . esc_html__( 'Cliente con token', 'gest-web-rent' ) . '</option></select></label><button class="button button-primary">' . esc_html__( 'Carica allegato', 'gest-web-rent' ) . '</button></form>';
		}
		echo '<div class="gwr-table-wrap"><table class="widefat"><thead><tr><th>' . esc_html__( 'File', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Tipo', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Visibilita', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Caricato', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Azioni', 'gest-web-rent' ) . '</th></tr></thead><tbody>';
		if ( empty( $attachments ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Nessun allegato caricato.', 'gest-web-rent' ) . '</td></tr>';
		}
		foreach ( $attachments as $attachment ) {
			echo '<tr><td><strong>' . esc_html( $attachment['description'] ?: basename( (string) $attachment['file_path'] ) ) . '</strong></td><td>' . esc_html( $attachment['mime_type'] ) . '</td><td>' . esc_html( $attachment['visibility'] ) . '</td><td>' . esc_html( GWR_Bookings::format_datetime( $attachment['created_at'] ) ) . '</td><td><div class="gwr-row-actions"><a href="' . esc_url( GWR_Attachment_Service::download_url( $attachment ) ) . '">' . esc_html__( 'Download', 'gest-web-rent' ) . '</a>';
			echo '<a class="is-danger" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gwr_booking_attachment_delete&attachment_id=' . absint( $attachment['id'] ) ), 'gwr_booking_attachment_delete_' . absint( $attachment['id'] ) ) ) . '">' . esc_html__( 'Elimina', 'gest-web-rent' ) . '</a></div></td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/**
	 * Settings form.
	 *
	 * @return void
	 */
	private static function settings_form() {
		$settings = GWR_Document_Service::get_settings();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-settings-form"><input type="hidden" name="action" value="gwr_save_document_settings" />';
		wp_nonce_field( 'gwr_save_document_settings', 'gwr_document_settings_nonce' );
		self::settings_section_company( $settings );
		self::settings_section_texts( $settings );
		self::settings_section_storage( $settings );
		echo '<div class="gwr-form-submit"><button class="button button-primary button-hero">' . esc_html__( 'Salva impostazioni documenti', 'gest-web-rent' ) . '</button></div></form>';
	}

	private static function settings_section_company( $settings ) {
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><div><h2>' . esc_html__( 'Dati aziendali', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Usati nell intestazione dei documenti. Verifica sempre i dati legali prima di inviarli.', 'gest-web-rent' ) . '</p></div></div><div class="gwr-field-grid gwr-field-grid--triple">';
		foreach ( array(
			'company_legal_name' => __( 'Ragione sociale', 'gest-web-rent' ),
			'company_trade_name' => __( 'Insegna', 'gest-web-rent' ),
			'vat_number' => __( 'Partita IVA', 'gest-web-rent' ),
			'tax_code' => __( 'Codice fiscale', 'gest-web-rent' ),
			'rea_number' => __( 'REA', 'gest-web-rent' ),
			'address' => __( 'Indirizzo', 'gest-web-rent' ),
			'postal_code' => __( 'CAP', 'gest-web-rent' ),
			'city' => __( 'Citta', 'gest-web-rent' ),
			'province' => __( 'Provincia', 'gest-web-rent' ),
			'country' => __( 'Paese', 'gest-web-rent' ),
			'phone' => __( 'Telefono', 'gest-web-rent' ),
			'email' => __( 'Email', 'gest-web-rent' ),
			'pec' => __( 'PEC', 'gest-web-rent' ),
			'website' => __( 'Sito web', 'gest-web-rent' ),
		) as $key => $label ) {
			self::setting_field( $key, $label, $settings[ $key ] ?? '' );
		}
		echo '<label class="gwr-field gwr-field--wide" data-gwr-doc-logo-field><span class="gwr-field__label">' . esc_html__( 'Logo documenti', 'gest-web-rent' ) . '</span><input type="hidden" name="gwr_document_settings[logo_id]" value="' . esc_attr( $settings['logo_id'] ) . '" data-gwr-doc-logo-id /><div class="gwr-document-logo-preview" data-gwr-doc-logo-preview>';
		if ( ! empty( $settings['logo_id'] ) ) {
			echo wp_get_attachment_image( absint( $settings['logo_id'] ), 'medium' );
		}
		echo '</div><button type="button" class="button button-secondary" data-gwr-doc-logo-button>' . esc_html__( 'Scegli logo', 'gest-web-rent' ) . '</button></label></div></section>';
	}

	private static function settings_section_texts( $settings ) {
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><div><h2>' . esc_html__( 'Testi documenti', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'Testi operativi personalizzabili. Le clausole contrattuali devono essere validate dal consulente legale.', 'gest-web-rent' ) . '</p></div></div><div class="gwr-field-grid">';
		foreach ( array(
			'voucher_intro' => __( 'Introduzione voucher', 'gest-web-rent' ),
			'confirmation_intro' => __( 'Introduzione conferma/riepilogo', 'gest-web-rent' ),
			'contract_intro' => __( 'Introduzione contratto', 'gest-web-rent' ),
			'pickup_text' => __( 'Testo verbale consegna', 'gest-web-rent' ),
			'return_text' => __( 'Testo verbale riconsegna', 'gest-web-rent' ),
			'payment_note' => __( 'Nota pagamenti', 'gest-web-rent' ),
			'cancellation_text' => __( 'Testo annullamento', 'gest-web-rent' ),
			'legal_text' => __( 'Nota legale generale', 'gest-web-rent' ),
			'documents_footer' => __( 'Footer documenti', 'gest-web-rent' ),
		) as $key => $label ) {
			self::setting_textarea( $key, $label, $settings[ $key ] ?? '' );
		}
		echo '<label class="gwr-field gwr-field--wide"><span class="gwr-field__label">' . esc_html__( 'Clausole contratto (una per riga)', 'gest-web-rent' ) . '</span><textarea name="gwr_document_settings[contract_clauses]" rows="8">' . esc_textarea( implode( "\n", $settings['contract_clauses'] ?? array() ) ) . '</textarea></label></div></section>';
	}

	private static function settings_section_storage( $settings ) {
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><div><h2>' . esc_html__( 'Sicurezza e allegati', 'gest-web-rent' ) . '</h2><p>' . esc_html__( 'I file sono serviti tramite endpoint autorizzati, mai tramite percorso diretto.', 'gest-web-rent' ) . '</p></div></div><div class="gwr-field-grid gwr-field-grid--triple">';
		echo '<label class="gwr-check-card"><input type="checkbox" name="gwr_document_settings[attachments_enabled]" value="1" ' . checked( ! empty( $settings['attachments_enabled'] ), true, false ) . ' /> <span>' . esc_html__( 'Abilita allegati pratica', 'gest-web-rent' ) . '</span></label>';
		self::setting_field( 'max_upload_mb', __( 'Upload massimo MB', 'gest-web-rent' ), $settings['max_upload_mb'] ?? 8, 'number' );
		self::setting_field( 'retention_days', __( 'Conservazione giorni', 'gest-web-rent' ), $settings['retention_days'] ?? 3650, 'number' );
		echo '</div></section>';
	}

	/**
	 * Archive table.
	 *
	 * @return void
	 */
	private static function archive_table() {
		$args = array(
			'search' => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
			'type' => sanitize_key( wp_unslash( $_GET['document_type'] ?? '' ) ),
			'status' => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
			'page' => max( 1, absint( $_GET['paged'] ?? 1 ) ),
			'per_page' => 20,
		);
		$result = GWR_Document_Service::query( $args );
		echo '<form method="get" class="gwr-booking-filters"><input type="hidden" name="page" value="gwr-documents" /><input type="hidden" name="tab" value="archive" /><label><span>' . esc_html__( 'Ricerca', 'gest-web-rent' ) . '</span><input type="search" name="s" value="' . esc_attr( $args['search'] ) . '" placeholder="' . esc_attr__( 'Numero, booking, email', 'gest-web-rent' ) . '" /></label><label><span>' . esc_html__( 'Tipo', 'gest-web-rent' ) . '</span><select name="document_type"><option value="">' . esc_html__( 'Tutti', 'gest-web-rent' ) . '</option>';
		foreach ( GWR_Document_Service::document_types() as $key => $type ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $args['type'], $key, false ) . '>' . esc_html( $type['label'] ) . '</option>';
		}
		echo '</select></label><label><span>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</span><select name="status"><option value="">' . esc_html__( 'Tutti', 'gest-web-rent' ) . '</option>';
		foreach ( GWR_Document_Service::statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $args['status'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label><button class="button button-primary">' . esc_html__( 'Filtra', 'gest-web-rent' ) . '</button></form>';
		echo '<section class="gwr-data-grid-card"><div class="gwr-table-wrap"><table class="widefat"><thead><tr><th>' . esc_html__( 'Documento', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Prenotazione', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Cliente', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Generato', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Azioni', 'gest-web-rent' ) . '</th></tr></thead><tbody>';
		if ( empty( $result['items'] ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'Nessun documento trovato.', 'gest-web-rent' ) . '</td></tr>';
		}
		foreach ( $result['items'] as $document ) {
			self::archive_row( $document );
		}
		echo '</tbody></table></div></section>';
		if ( $result['pages'] > 1 ) {
			$base = remove_query_arg( 'paged' );
			echo '<nav class="gwr-pagination">';
			for ( $page = 1; $page <= $result['pages']; $page++ ) {
				echo '<a class="button' . ( $page === $args['page'] ? ' button-primary' : '' ) . '" href="' . esc_url( add_query_arg( 'paged', $page, $base ) ) . '">' . esc_html( $page ) . '</a>';
			}
			echo '</nav>';
		}
	}

	private static function archive_row( $document ) {
		$types = GWR_Document_Service::document_types();
		$statuses = GWR_Document_Service::statuses();
		$booking_url = admin_url( 'admin.php?page=gwr-booking-detail&booking_id=' . absint( $document['booking_id'] ) );
		echo '<tr><td><strong>' . esc_html( $document['document_number'] ) . '</strong><small>' . esc_html( ( $types[ $document['document_type'] ]['label'] ?? $document['document_type'] ) . ' v' . $document['version'] ) . '</small></td><td><a href="' . esc_url( $booking_url ) . '">' . esc_html( $document['booking_code'] ?? $document['booking_id'] ) . '</a><small>' . esc_html( $document['vehicle_title'] ?? '' ) . '</small></td><td>' . esc_html( $document['customer_email'] ?? '' ) . '</td><td><span class="gwr-inline-status is-' . esc_attr( $document['status'] ) . '">' . esc_html( $statuses[ $document['status'] ] ?? $document['status'] ) . '</span></td><td>' . esc_html( GWR_Bookings::format_datetime( $document['generated_at'] ) ) . '</td><td><div class="gwr-row-actions"><a target="_blank" rel="noopener noreferrer" href="' . esc_url( GWR_Document_Service::document_url( $document, 'preview' ) ) . '">' . esc_html__( 'Anteprima', 'gest-web-rent' ) . '</a><a href="' . esc_url( GWR_Document_Service::document_url( $document, 'download' ) ) . '">' . esc_html__( 'Download', 'gest-web-rent' ) . '</a></div></td></tr>';
	}

	private static function setting_field( $key, $label, $value, $type = 'text' ) {
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="gwr_document_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" /></label>';
	}

	private static function setting_textarea( $key, $label, $value ) {
		echo '<label class="gwr-field gwr-field--wide"><span class="gwr-field__label">' . esc_html( $label ) . '</span><textarea name="gwr_document_settings[' . esc_attr( $key ) . ']" rows="5">' . esc_textarea( $value ) . '</textarea></label>';
	}

	private static function notice() {
		if ( empty( $_GET['gwr_message'] ) ) {
			return;
		}
		$error = ! empty( $_GET['gwr_error'] ) || ( isset( $_GET['gwr_notice'] ) && 'error' === $_GET['gwr_notice'] );
		echo '<div class="notice ' . esc_attr( $error ? 'notice-error' : 'notice-success' ) . ' inline is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['gwr_message'] ) ) ) . '</p></div>';
	}

	private static function require_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'gest-web-rent' ) );
		}
	}
}
