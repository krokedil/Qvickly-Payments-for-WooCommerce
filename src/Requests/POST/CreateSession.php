<?php
namespace Krokedil\Qvickly\Payments\Requests\POST;

use Krokedil\Qvickly\Payments\Requests\POST;
use Krokedil\Qvickly\Payments\Requests\Helpers\Cart;

/**
 * Create checkout session request class.
 */
class CreateSession extends POST {

	/**
	 * CreateSession constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->log_title             = 'Create session';
		$this->arguments['function'] = 'initCheckout';
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	public function get_body() {
		$cart = new Cart();

		return array(
			'CheckoutData' => array(
				'terms'         => get_permalink( wc_terms_and_conditions_page_id() ),
				'privacyPolicy' => get_permalink( wc_privacy_policy_page_id() ),
			),
			'PaymentData'  => array(
				'currency'    => get_woocommerce_currency(),
				'language'    => explode( '_', get_locale() )[0] ?? 'en',
				'country'     => $cart->get_country(),
				'orderid'     => Qvickly_Payments()->session()->get_reference(),
				'accepturl'   => $cart->get_confirmation_url(),
				'cancelurl'   => wc_get_checkout_url(),
				'callbackurl' => $cart->get_notification_url(),
			),
			'Articles'     => $cart->get_articles(),
			'Cart'         => $cart->get_cart(),
		);
	}
}
