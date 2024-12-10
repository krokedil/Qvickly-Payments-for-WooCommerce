<?php //phpcs:ignore -- PCR-4 compliant
/**
 * Class AJAX.
 *
 * AJAX endpoints.
 */

namespace Krokedil\Qvickly\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJAX
 */
class AJAX {

	/**
	 * AJAX constructor.
	 */
	public function __construct() {
		$ajax_events = array(
			'qvickly_payments_wc_log_js'       => true,
			'qvickly_payments_create_order'    => true,
			'qvickly_payments_pending_payment' => true,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( $this, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( $this, $ajax_event ) );
				add_action( 'wc_ajax_' . $ajax_event, array( $this, $ajax_event ) );
			}
		}
	}

	/**
	 * Logs messages from the JavaScript to the server log.
	 *
	 * @return void
	 */
	public static function qvickly_payments_wc_log_js() {
		check_ajax_referer( 'qvickly_payments_wc_log_js', 'nonce' );

		$message = '[AJAX]: ' . sanitize_text_field( filter_input( INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$prefix  = sanitize_text_field( filter_input( INPUT_POST, 'reference', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$level   = sanitize_text_field( filter_input( INPUT_POST, 'level', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) ?? 'notice';
		if ( ! empty( $message ) ) {
			Qvickly_Payments()->logger()->log( $message, $level, array( 'prefix' => $prefix ) );
		}

		wp_send_json_success();
	}

	/**
	 * Acknowledges the Qvickly order.
	 *
	 * @return void
	 */
	public static function qvickly_payments_create_order() {
		check_ajax_referer( 'qvickly_payments_create_order', 'nonce' );

		$session_id = filter_input( INPUT_POST, 'session_id', FILTER_SANITIZE_NUMBER_INT );
		$order_key  = filter_input( INPUT_POST, 'order_key', FILTER_SANITIZE_SPECIAL_CHARS );

		if ( empty( $session_id ) || empty( $order_key ) ) {
			wp_send_json_error( 'Missing params. Received: ' . wp_json_encode( $session_id ) );
		}

		$order_id = wc_get_order_id_by_order_key( $order_key );
		$order    = wc_get_order( $order_id );

		$result = Qvickly_Payments()->api()->create_order( $session_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result );
		}

		$payment_id = $result['orderId'];
		$order->update_meta_data( 'qvickly_payments_payment_id', $payment_id );
		$order->save();

		$redirect_to = add_query_arg(
			array(
				'gateway' => 'qvickly_payments',
				'key'     => $order_key,
			),
			$order->get_checkout_order_received_url()
		);

		$context = array(
			'function'   => __FUNCTION__,
			'order_id'   => $order_id,
			'order_key'  => $order_key,
			'payment_id' => $result['orderId'],
		);
		Qvickly_Payments()->logger()->debug( '[AJAX]: Redirecting to ' . $redirect_to, $context );

		wp_send_json_success( array( 'location' => $redirect_to ) );
	}
}
