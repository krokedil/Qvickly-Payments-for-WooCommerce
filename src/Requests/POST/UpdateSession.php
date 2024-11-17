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
		$this->log_title             = 'Update session';
		$this->arguments['function'] = 'updateCheckout';
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
				'currency' => get_woocommerce_currency(),
				'language' => explode( '_', get_locale() )[0] ?? 'en',
				'country'  => $cart->get_country(),
				'orderid'  => Qvickly_Payments()->session()->get_reference(),
			),
			'Articles'     => $cart->get_articles(),
			'Cart'         => $cart->get_cart(),
		);
	}
}
