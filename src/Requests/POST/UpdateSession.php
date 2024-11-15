<?php
namespace Krokedil\Qvickly\Payments\Requests\POST;

use Krokedil\Qvickly\Payments\Requests\POST;
use Krokedil\Qvickly\Payments\Requests\Helpers\Cart;

/**
 * Update checkout session request class.
 */
class UpdateSession extends POST {

	/**
	 * UpdateSession constructor.
	 *
	 * @param string $session_id The Qvickly session ID.
	 */
	public function __construct( $session_id ) {
		parent::__construct();
		$this->log_title = 'Update session';
		$this->endpoint  = "/v1/payment-sessions/$session_id";
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	public function get_body() {
		$cart = new Cart();

		return array(
			'country'                 => WC()->customer->get_billing_country(),
			'currency'                => get_woocommerce_currency(),
			'locale'                  => str_replace( '_', '-', get_locale() ),
			'orderLines'              => $cart->get_order_lines(),
			'reference'               => Qvickly_Payments()->session()->get_reference(),
			'totalOrderAmount'        => $cart->get_total(),
			'totalOrderAmountExclVat' => $cart->get_total() - $cart->get_total_tax(),
			'totalOrderVatAmount'     => $cart->get_total_tax(),
		);
	}
}
