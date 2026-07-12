<?php
/**
 * Admin UI for payments.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Payments admin screens.
 */
class GWR_Payments_Admin {
	/** Register menu. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 21 );
	}

	/** Admin menu entries. */
	public static function admin_menu() {
		add_submenu_page( 'gest-web-rent', __( 'Pagamenti', 'gest-web-rent' ), __( 'Pagamenti', 'gest-web-rent' ), 'manage_options', 'gwr-payments', array( __CLASS__, 'list_page' ) );
		add_submenu_page( null, __( 'Dettaglio pagamento', 'gest-web-rent' ), __( 'Dettaglio pagamento', 'gest-web-rent' ), 'manage_options', 'gwr-payment-detail', array( __CLASS__, 'detail_page' ) );
	}

	/** Payments list. */
	public static function list_page() {
		$args = array(
			'search'      => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
			'status'      => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
			'provider'    => sanitize_key( wp_unslash( $_GET['provider'] ?? '' ) ),
			'environment' => sanitize_key( wp_unslash( $_GET['environment'] ?? '' ) ),
			'page'        => max( 1, absint( $_GET['paged'] ?? 1 ) ),
		);
		$result = GWR_Payment_Service::query( $args );
		$reconcile = wp_nonce_url( admin_url( 'admin-post.php?action=gwr_reconcile_payments' ), 'gwr_reconcile_payments' );
		GWR_Admin::page_start( 'payments', __( 'Pagamenti', 'gest-web-rent' ), __( 'Pagamenti online, bonifici, rimborsi, tentativi e stati provider.', 'gest-web-rent' ), array( array( 'label' => __( 'Riconcilia pagamenti', 'gest-web-rent' ), 'url' => $reconcile, 'primary' => true ) ) );
		self::notice();
		echo '<form method="get" class="gwr-booking-filters"><input type="hidden" name="page" value="gwr-payments" />';
		echo '<label><span>' . esc_html__( 'Cerca', 'gest-web-rent' ) . '</span><input type="search" name="s" value="' . esc_attr( $args['search'] ) . '" /></label>';
		self::select_filter( 'status', __( 'Stato', 'gest-web-rent' ), $args['status'], GWR_Payment_Service::statuses() );
		self::select_filter( 'provider', __( 'Provider', 'gest-web-rent' ), $args['provider'], array( 'stripe' => 'Stripe', 'bank_transfer' => __( 'Bonifico', 'gest-web-rent' ), 'manual' => __( 'Manuale', 'gest-web-rent' ) ) );
		self::select_filter( 'environment', __( 'Ambiente', 'gest-web-rent' ), $args['environment'], array( 'test' => 'TEST', 'live' => 'LIVE', 'manual' => 'MANUAL' ) );
		echo '<button class="button button-secondary">' . esc_html__( 'Filtra', 'gest-web-rent' ) . '</button></form>';
		echo '<section class="gwr-data-grid-card"><div class="gwr-table-wrap"><table class="widefat"><thead><tr><th>' . esc_html__( 'Prenotazione', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Cliente', 'gest-web-rent' ) . '</th><th>Provider</th><th>' . esc_html__( 'Importo', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Rimborsato', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Stato', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Data', 'gest-web-rent' ) . '</th><th>' . esc_html__( 'Azioni', 'gest-web-rent' ) . '</th></tr></thead><tbody>';
		foreach ( $result['items'] as $payment ) {
			$url = admin_url( 'admin.php?page=gwr-payment-detail&payment_id=' . absint( $payment['id'] ) );
			$booking_url = admin_url( 'admin.php?page=gwr-booking-detail&booking_id=' . absint( $payment['booking_id'] ) );
			echo '<tr><td><a href="' . esc_url( $booking_url ) . '"><strong>' . esc_html( $payment['booking_code'] ?: '#' . $payment['booking_id'] ) . '</strong></a></td><td>' . esc_html( $payment['customer_email'] ) . '</td><td><span class="gwr-mini-badge">' . esc_html( strtoupper( $payment['provider'] ) . ' ' . strtoupper( $payment['environment'] ) ) . '</span></td><td>' . esc_html( GWR_Money::format_minor( $payment['amount'], $payment['currency'] ) ) . '</td><td>' . esc_html( GWR_Money::format_minor( $payment['amount_refunded'], $payment['currency'] ) ) . '</td><td><span class="gwr-inline-status is-' . esc_attr( $payment['status'] ) . '">' . esc_html( GWR_Payment_Service::statuses()[ $payment['status'] ] ?? $payment['status'] ) . '</span></td><td>' . esc_html( GWR_Bookings::format_datetime( $payment['created_at'] ) ) . '</td><td><a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html__( 'Apri', 'gest-web-rent' ) . '</a></td></tr>';
		}
		if ( empty( $result['items'] ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'Nessun pagamento trovato.', 'gest-web-rent' ) . '</td></tr>';
		}
		echo '</tbody></table></div></section>';
		self::pagination( $result['pages'], $args['page'] );
		GWR_Admin::page_end();
	}

	/** Payment detail. */
	public static function detail_page() {
		$payment_id = absint( $_GET['payment_id'] ?? 0 );
		$payment = GWR_Payment_Service::get( $payment_id );
		if ( ! $payment ) {
			wp_die( esc_html__( 'Pagamento non trovato.', 'gest-web-rent' ) );
		}
		$booking = GWR_Bookings::get( $payment['booking_id'] );
		$events = GWR_Payment_Service::events( $payment_id );
		GWR_Admin::page_start( 'payments', sprintf( __( 'Pagamento #%d', 'gest-web-rent' ), $payment_id ), __( 'Dettaglio economico, riferimenti provider, rimborsi ed eventi.', 'gest-web-rent' ), array( array( 'label' => __( 'Torna ai pagamenti', 'gest-web-rent' ), 'url' => admin_url( 'admin.php?page=gwr-payments' ) ) ) );
		self::notice();
		echo '<div class="gwr-booking-detail-grid"><main class="gwr-booking-detail-main">';
		echo '<section class="gwr-data-grid-card"><div class="gwr-data-grid-card__head"><h2>' . esc_html__( 'Pagamento', 'gest-web-rent' ) . '</h2><span class="gwr-inline-status is-' . esc_attr( $payment['status'] ) . '">' . esc_html( GWR_Payment_Service::statuses()[ $payment['status'] ] ?? $payment['status'] ) . '</span></div><dl class="gwr-booking-data-list">';
		foreach ( array( 'provider', 'environment', 'payment_type', 'provider_session_id', 'provider_payment_intent_id', 'provider_charge_id', 'provider_refund_id', 'idempotency_key', 'failure_code', 'failure_message', 'created_at', 'paid_at', 'failed_at', 'refunded_at' ) as $key ) {
			if ( '' !== (string) ( $payment[ $key ] ?? '' ) ) {
				echo '<div><dt>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . '</dt><dd>' . esc_html( $payment[ $key ] ) . '</dd></div>';
			}
		}
		echo '<div><dt>' . esc_html__( 'Importo', 'gest-web-rent' ) . '</dt><dd>' . esc_html( GWR_Money::format_minor( $payment['amount'], $payment['currency'] ) ) . '</dd></div><div><dt>' . esc_html__( 'Rimborsato', 'gest-web-rent' ) . '</dt><dd>' . esc_html( GWR_Money::format_minor( $payment['amount_refunded'], $payment['currency'] ) ) . '</dd></div></dl></section>';
		echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Eventi', 'gest-web-rent' ) . '</h2><div class="gwr-table-wrap"><table class="widefat"><thead><tr><th>ID</th><th>Tipo</th><th>Processato</th><th>Ricevuto</th><th>Errore</th></tr></thead><tbody>';
		foreach ( $events as $event ) {
			echo '<tr><td><code>' . esc_html( $event['provider_event_id'] ) . '</code></td><td>' . esc_html( $event['event_type'] ) . '</td><td>' . esc_html( $event['processed'] ? 'si' : 'no' ) . '</td><td>' . esc_html( $event['received_at'] ) . '</td><td>' . esc_html( $event['processing_error'] ) . '</td></tr>';
		}
		if ( ! $events ) { echo '<tr><td colspan="5">' . esc_html__( 'Nessun evento.', 'gest-web-rent' ) . '</td></tr>'; }
		echo '</tbody></table></div></section></main><aside class="gwr-booking-detail-side">';
		if ( $booking ) {
			$booking_url = admin_url( 'admin.php?page=gwr-booking-detail&booking_id=' . absint( $booking['id'] ) );
			echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Prenotazione', 'gest-web-rent' ) . '</h2><p><a href="' . esc_url( $booking_url ) . '"><strong>' . esc_html( $booking['booking_code'] ) . '</strong></a></p><p>' . esc_html( $booking['customer_email'] ) . '</p><p>' . esc_html( GWR_Bookings::format_money( $booking['total_amount'], $booking['currency'] ) ) . '</p></section>';
		}
		self::refund_form( $payment );
		self::bank_form( $payment );
		echo '</aside></div>';
		GWR_Admin::page_end();
	}

	private static function refund_form( $payment ) {
		$refundable = max( 0, absint( $payment['amount'] ) - absint( $payment['amount_refunded'] ) );
		if ( $refundable <= 0 || ! in_array( $payment['status'], array( 'paid', 'partially_refunded' ), true ) ) {
			return;
		}
		echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Rimborso', 'gest-web-rent' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-settings-form"><input type="hidden" name="action" value="gwr_refund_payment" /><input type="hidden" name="payment_id" value="' . esc_attr( $payment['id'] ) . '" />'; wp_nonce_field( 'gwr_refund_payment_' . $payment['id'], 'gwr_payment_nonce' );
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Importo', 'gest-web-rent' ) . '</span><input type="number" step="any" min="0" max="' . esc_attr( GWR_Money::minor_to_decimal( $refundable ) ) . '" name="amount" value="' . esc_attr( GWR_Money::minor_to_decimal( $refundable ) ) . '" /></label><label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Motivazione', 'gest-web-rent' ) . '</span><textarea name="reason"></textarea></label><button class="button button-secondary">' . esc_html__( 'Rimborsa', 'gest-web-rent' ) . '</button></form></section>';
	}

	private static function bank_form( $payment ) {
		if ( 'bank_transfer' !== $payment['provider'] || 'pending' !== $payment['status'] ) {
			return;
		}
		echo '<section class="gwr-data-grid-card"><h2>' . esc_html__( 'Bonifico', 'gest-web-rent' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gwr-settings-form"><input type="hidden" name="action" value="gwr_mark_bank_transfer" /><input type="hidden" name="payment_id" value="' . esc_attr( $payment['id'] ) . '" />'; wp_nonce_field( 'gwr_mark_bank_transfer_' . $payment['id'], 'gwr_payment_nonce' );
		echo '<label class="gwr-field"><span class="gwr-field__label">' . esc_html__( 'Riferimento', 'gest-web-rent' ) . '</span><input type="text" name="reference" /></label><button class="button button-primary">' . esc_html__( 'Segna bonifico ricevuto', 'gest-web-rent' ) . '</button></form></section>';
	}

	private static function select_filter( $name, $label, $value, $options ) {
		echo '<label><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'Tutti', 'gest-web-rent' ) . '</option>';
		foreach ( $options as $key => $option_label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></label>';
	}

	private static function pagination( $pages, $current ) {
		if ( $pages <= 1 ) { return; }
		echo '<nav class="gwr-pagination">';
		for ( $i = 1; $i <= $pages; $i++ ) {
			$url = add_query_arg( 'paged', $i );
			echo '<a class="button ' . esc_attr( $i === $current ? 'button-primary' : 'button-secondary' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $i ) . '</a>';
		}
		echo '</nav>';
	}

	private static function notice() {
		if ( empty( $_GET['gwr_message'] ) ) { return; }
		$class = ! empty( $_GET['gwr_error'] ) ? 'notice-error' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $class ) . ' inline is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['gwr_message'] ) ) ) . '</p></div>';
	}
}
