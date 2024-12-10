<?php
/**
 * Class Gateway.
 *
 * Register the Qvickly Payments payment gateway.
 */

namespace Krokedil\Qvickly\Payments;

use Krokedil\Qvickly\Payments\Requests\Helpers\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Gateway.
 */
class Gateway extends \WC_Payment_Gateway {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id                 = 'qvickly_payments';
		$this->method_title       = __( 'Qvickly Payments', 'qvickly-payments-for-woocommerce' );
		$this->method_description = __( 'Qvickly Payments', 'qvickly-payments-for-woocommerce' );
		$this->supports           = apply_filters(
			$this->id . '_supports',
			array(
				'products',
			)
		);
		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->has_fields  = true;

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

		add_filter( 'wc_get_template', array( $this, 'payment_categories' ), 10, 3 );
		add_action( 'init', array( $this, 'maybe_confirm_order' ), 999 );

	}

	/**
	 * Initialize settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = Settings::setting_fields();

		// Delete the access token whenever the settings are modified.
		add_action( 'update_option_woocommerce_qvickly_payments_settings', array( Settings::class, 'maybe_update_access_token' ) );
	}

	/**
	 * Get order by reference (session ID).
	 *
	 * For orders awaiting signatory, the order reference is used as the payment ID. Otherwise, the orderId from Qvickly.
	 *
	 * @param string $payment_number Qvickly payment number.
	 * @return \WC_Order|bool The WC_Order or false if not found.
	 */
	public function get_order_by_payment_number( $payment_number ) {
		$key    = '_qvickly_session_id';
		$orders = wc_get_orders(
			array(
				'meta_query' => array(
					array(
						'key'     => $key,
						'value'   => $payment_number,
						'compare' => '=',
					),
				),
				'limit'      => '1',
				'orderby'    => 'date',
				'order'      => 'DESC',
			)
		);

		$order = reset( $orders );
		if ( empty( $order ) || $payment_number !== $order->get_meta( $key ) ) {
			return false;
		}

		return $order ?? false;
	}

	/**
	 * The payment gateway icon that will appear on the checkout page.
	 *
	 * @return string
	 */
	public function get_icon() {
		$image_path = plugin_dir_url( __FILE__ ) . 'assets/img/gateway-icon.png';
		return "<img src='{$image_path}' style='max-width: 90%' alt='Qvickly Payments logo' />";
	}

	/**
	 * Whether the payment gateway is available.
	 *
	 * @filter qvickly_payments_is_available
	 *
	 * @return boolean
	 */
	public function is_available() {
		return apply_filters( 'qvickly_payments_is_available', $this->check_availability() );
	}

	/**
	 * Check if the gateway should be available.
	 *
	 * This function is extracted to create the 'qvickly_payments_is_available' filter.
	 *
	 * @return bool
	 */
	private function check_availability() {
		return wc_string_to_bool( $this->enabled );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id WooCommerced order id.
	 * @return array An associative array containing the success status and redirect URl.
	 */
	public function process_payment( $order_id ) {
		$helper   = new Order( wc_get_order( $order_id ) );
		$customer = $helper->get_customer();

		// Update the nonce only if WordPress determines it necessary, such as when a guest becomes signed in.
		$nonce = array(
			'changePaymentMethodNonce' => wp_create_nonce( 'qvickly_payments_change_payment_method' ),
			'logToFileNonce'           => wp_create_nonce( 'qvickly_payments_wc_log_js' ),
			'createOrderNonce'         => wp_create_nonce( 'qvickly_payments_create_order' ),
		);

		$session = Qvickly_Payments()->session()->get_session();

		$redirect = $session['url'] ?? false;
		if ( empty( $redirect ) ) {
			return array(
				'result' => 'error',
			);
		}

		$order = $helper->order;
		$order->update_meta_data( '_qvickly_session_id', Qvickly_Payments()->session()->get_reference() );
		$order->update_meta_data( '_qvickly_payment_number', Qvickly_Payments()->session()->get_payment_number() );
		$order->save();

		return array(
			'order_key' => $order->get_order_key(),
			'customer'  => $customer,
			'redirect'  => $redirect,
			'nonce'     => $nonce,
			'result'    => 'success',
		);
	}

	/**
	 * This plugin doesn't handle order management, but it allows the Qvickly Order Management plugin to process refunds
	 * and then return true or false whether it was successful.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param float  $amount The amount to refund.
	 * @param string $reason The reason for the refund.
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return apply_filters( 'qvickly_payments_process_refund', false, $order_id, $amount, $reason );
	}

	/**
	 * Display the payment categories under the gateway on the checkout page.
	 *
	 * @param string $located Target template file location.
	 * @param string $template_name The name of the template.
	 * @param array  $args Arguments for the template.
	 * @return string
	 */
	public function payment_categories( $located, $template_name, $args ) {
		if ( ! is_checkout() ) {
			return $located;
		}

		if ( ( 'checkout/payment-method.php' !== $template_name ) || ( 'qvickly_payments' !== $args['gateway']->id ) ) {
			return $located;
		}

		return untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/payment-categories.php';
	}

	/**
	 * Processes the order confirmation if the required parameters are set.
	 *
	 * Since the `woocommerce_thankyou` hook might be omitted by certain themes, we've opted to use the init hook instead.
	 *
	 * @return void
	 */
	public function maybe_confirm_order() {
		$key        = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$gateway    = filter_input( INPUT_GET, 'gateway', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$session_id = filter_input( INPUT_GET, 'session_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( $this->id !== $gateway ) {
			return;
		}

		$order_id = wc_get_order_id_by_order_key( $key );
		$context  = array(
			'filter'   => current_filter(),
			'function' => __FUNCTION__,
			'order_id' => $order_id,
			'key'      => $key,
		);
		Qvickly_Payments()->logger()->debug( '[MAYBE_CONFIRM]: Customer refreshed or redirected to thankyou page.', $context );

		$order = wc_get_order( $order_id );
		if ( ! hash_equals( $order->get_order_key(), $key ) ) {
			Qvickly_Payments()->logger()->error( '[MAYBE_CONFIRM]: Order key mismatch.', $context );
			return;
		}

		$session_id_from_order = $order->get_meta( '_qvickly_session_id' );

		// Check if the session wasn't cleared properly. This can happen if the order is successfully created, but the customer was not redirected to the checkout page.
		if ( ! empty( $order->get_date_paid() ) ) {
			$session_id_from_session = Qvickly_Payments()->session()->get_reference();
			if ( $session_id_from_order === $session_id_from_session ) {
				Qvickly_Payments()->logger()->debug( '[MAYBE_CONFIRM]: Order already paid, but session still remained. Session is now cleared.', $context );
				Qvickly_Payments()->session()->clear( $order );
			}

			Qvickly_Payments()->logger()->debug( '[MAYBE_CONFIRM]: Order already paid. Customer probably refreshed thankyou page.', $context );
			return;
		}

		// Check if the external session ID matches the order's session ID.
		if ( $session_id_from_order !== $session_id ) {
			Qvickly_Payments()->logger()->error( '[MAYBE_CONFIRM]: Session ID mismatch.', $context );
			return;
		}

		$this->confirm_order( $order, $context );
		Qvickly_Payments()->session()->clear( $order );
	}

	/**
	 * Confirm the order.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param array     $context The logging context. Optional.
	 * @return void
	 */
	public function confirm_order( $order, $context = array() ) {
		// Overwrite the context since we know the previous context was successful since it reached this point.
		$context = array(
			'filter'     => current_filter(),
			'function'   => __FUNCTION__,
			'session_id' => $order->get_meta( '_qvickly_session_id' ),
			'order_id'   => $order->get_id(),
		);

		$payment_number = $order->get_meta( '_qvickly_payment_number' );
		$qvickly_order  = Qvickly_Payments()->api()->get_session( $payment_number );
		if ( is_wp_error( $qvickly_order ) ) {
			Qvickly_Payments()->logger()->error( '[CONFIRM]: Failed to get Qvickly order. Unrecoverable error, aborting.', $context );
			return;
		}

		// Request Qvickly to proceed with creating the order in their system.
		$create_order = Qvickly_Payments()->api()->create_order( $payment_number );
		if ( is_wp_error( $qvickly_order ) ) {
			Qvickly_Payments()->logger()->error( '[CONFIRM]: Qvickly could not create the order. Unrecoverable error, aborting.', $context );
			return;
		}

		$qvickly_order_id = $create_order['orderid'];
		$status           = $create_order['status'];
		$order->update_meta_data( '_qvickly_order_id', $qvickly_order_id );
		$order->save();

		if ( 'Paid' === strtolower( $status ) ) {
			$order->payment_complete( $qvickly_order_id );
		} else {
			Qvickly_Payments()->logger()->warning( "[CONFIRM]: Unknown order status: {$status}", $context );
		}

		$order->set_payment_method( $this->id );
		$order->set_transaction_id( $qvickly_order_id );

		$env = wc_string_to_bool( Qvickly_Payments()->settings( 'test_mode' ) ?? 'no' ) ? 'sandbox' : 'production';
		$order->update_meta_data( '_qvickly_environment', $env );
		$order->save();
	}
}
