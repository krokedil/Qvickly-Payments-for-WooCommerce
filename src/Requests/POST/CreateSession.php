<?php
namespace Krokedil\Qvickly\Payments\Requests\POST;

use Krokedil\Qvickly\Payments\Requests\POSTRequest;
use Krokedil\Qvickly\Payments\Requests\Helpers\Cart;
use Krokedil\Qvickly\Payments\Requests\Helpers\Store;

/**
 * Create checkout session request class.
 */
class CreateSession extends POSTRequest {

	/**
	 * CreateSession constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->log_title             = 'Create session';
		$this->arguments['function'] = 'addPayment';
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	public function get_body() {
		$cart = new Cart();

		$address        = $cart->get_address();
		$language       = Store::get_language();
		$payment_method = absint( $this->settings['payment_method'] );

		return array(
			'PaymentData' => array(
				'method'      => $payment_method,
				'currency'    => get_woocommerce_currency(),
				'language'    => false !== $language ? $language : 'en',
				'country'     => $cart->get_country(),
				'orderid'     => Qvickly_Payments()->session()->get_reference(),
				'accepturl'   => $cart->get_confirmation_url(),
				'cancelurl'   => wc_get_checkout_url(),
				'callbackurl' => $cart->get_notification_url(),
			),
			'PaymentInfo' => array(
				'paymentterms' => get_permalink( wc_terms_and_conditions_page_id() ),
			),
			'Card'        => array(),
			'Customer'    => array(
				'Billing'  => $address['billing'],
				'Shipping' => $address['shipping'],
			),
			'Articles'    => $cart->get_articles(),
			'Cart'        => $cart->get_cart(),
		);
	}
}
