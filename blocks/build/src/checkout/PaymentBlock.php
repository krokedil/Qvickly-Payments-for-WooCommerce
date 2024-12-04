<?php
/**
 * Qvickly Checkout Block.
 *
 * @package Qvickly_Payments/Blocks
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;

/**
 * Class Qvickly_Checkout_Block.
 */
class Qvickly_Checkout_Block extends AbstractPaymentMethodType {
	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {
		$this->name     = 'qvickly_payments';
		$this->settings = get_option( 'woocommerce_qvickly_payments_settings', array() );

		$assets_path = dirname( __DIR__, 2 ) . '/build/checkout.asset.php';
		if ( file_exists( $assets_path ) ) {
			$assets = require $assets_path;
			wp_register_script( 'qvickly-checkout-block', QVICKLY_PAYMENTS_PLUGIN_URL . '/blocks/build/payments.js', $assets['dependencies'], $assets['version'], true );
		}
	}

	/**
	 * Loads the payment method scripts.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		return array( 'qvickly-checkout-block' );
	}

	/**
	 * Checks if we are currently on the admin pages when loading the blocks.
	 *
	 * @return boolean
	 */
	public function is_admin() {
		// If we are on the block render endpoint, then this is an admin request.
		$is_edit_context = isset( $_GET['action'] ) && 'edit' === $_GET['action'];
		$is_admin        = $is_edit_context;

		return $is_admin;
	}

	/**
	 * Gets the payment method data to load into the frontend.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {

		if ( $this->is_admin() ) {
			return array(
				'title'                 => __( 'Qvickly Payments title', 'qvickly-payments-for-woocommerce' ),
				'description'           => __( 'Qvickly Payments description', 'qvickly-payments-for-woocommerce' ),
				'iconurl'               => QVICKLY_PAYMENTS_PLUGIN_URL . '/src/assets/img/qvickly-darkgray.svg',
				'enabled'               => true,
				'qvicklypaymentsparams' => array(),
			);
		}

		// The reference is stored in the session. Create the session if necessary.
		Qvickly_Payments()->session()->get_session();
		$reference  = Qvickly_Payments()->session()->get_reference();
		$session_id = Qvickly_Payments()->session()->get_id();

		return array(
			'title'                 => __( 'Qvickly Payments title', 'qvickly-payments-for-woocommerce' ),
			'description'           => __( 'Qvickly Payments description', 'qvickly-payments-for-woocommerce' ),
			'iconurl'               => QVICKLY_PAYMENTS_PLUGIN_URL . '/src/assets/img/qvickly-darkgray.svg',
			'enabled'               => true,
			'qvicklypaymentsparams' =>
			array(
				'sessionId'                => $session_id,
				'changePaymentMethodNonce' => wp_create_nonce( 'qvickly_payments_change_payment_method' ),
				'changePaymentMethodUrl'   => \WC_AJAX::get_endpoint( 'qvickly_payments_change_payment_method' ),
				'logToFileNonce'           => wp_create_nonce( 'qvickly_payments_wc_log_js' ),
				'logToFileUrl'             => \WC_AJAX::get_endpoint( 'qvickly_payments_wc_log_js' ),
				'createOrderNonce'         => wp_create_nonce( 'qvickly_payments_create_order' ),
				'createOrderUrl'           => \WC_AJAX::get_endpoint( 'qvickly_payments_create_order' ),
				'pendingPaymentNonce'      => wp_create_nonce( 'qvickly_payments_pending_payment' ),
				'pendingPaymentUrl'        => \WC_AJAX::get_endpoint( 'qvickly_payments_pending_payment' ),
				'submitOrderUrl'           => \WC_AJAX::get_endpoint( 'checkout' ),
				'gatewayId'                => 'qvickly_payments',
				'reference'                => $reference,
				'companyNumberPlacement'   => Qvickly_Payments()->settings( 'company_number_placement' ),
				'i18n'                     => array(
					'companyNumberMissing' => __( 'Please enter a company number.', 'qvickly-payments-for-woocommerce' ),
				),
			),
		);
	}
}
