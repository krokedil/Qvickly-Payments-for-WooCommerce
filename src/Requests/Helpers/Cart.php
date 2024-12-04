<?php
namespace Krokedil\Qvickly\Payments\Requests\Helpers;

use Krokedil\Qvickly\Payments\Callback;
use KrokedilQvicklyPaymentsDeps\Krokedil\WooCommerce\Cart\Cart as CartBase;

/**
 * Class Cart
 *
 * @package Krokedil\Qvickly\Payments\Requests\Helpers
 */
class Cart extends CartBase {

	/**
	 * Cart constructor.
	 */
	public function __construct() {
		$config = array(
			'slug'         => 'qvickly_payments',
			'price_format' => 'minor',
		);

		parent::__construct( WC()->cart, $config );
	}

	public function get_articles() {
		$articles = array();

		foreach ( $this->get_line_items() as $item ) {
			$articles[] = array(
				'taxrate'    => $item->get_tax_rate(),
				'withouttax' => $item->get_subtotal_amount(),
				'artnr'      => $item->get_sku(),
				'title'      => $item->get_name(),
				'quantity'   => $item->get_quantity(),
			);
		}

		return $articles;
	}

	public function get_cart() {
		$cart = array(
			'Total' => array(
				'withouttax' => absint( $this->get_total() - $this->get_total_tax() ),
				'tax'        => $this->get_total_tax(),
				'withtax'    => $this->get_total(),
			),
		);

		$shippings             = $this->get_line_shipping();
		$maybe_chosen_shipping = array_values( WC()->session->get( 'chosen_shipping_methods' ) ?? array() );
		if ( ! empty( $maybe_chosen_shipping ) ) {
			$chosen_shipping = reset( $maybe_chosen_shipping );
			foreach ( $shippings as $shipping ) {
				if ( $chosen_shipping === $shipping->get_sku() ) {
					$cart['Shipping'] = array(
						'withouttax' => $shipping->get_subtotal_amount(),
						'taxrate'    => $shipping->get_tax_rate(),
					);

					break;
				}
			}
		}

		return $cart;
	}

	/**
	 * Get the country.
	 *
	 * @return string
	 */
	public function get_country() {
		/* The billing country selected on the checkout page is to prefer over the store's base location. It makes more sense that we check for available payment methods based on the customer's country. */
		if ( method_exists( 'WC_Customer', 'get_billing_country' ) && ! empty( WC()->customer ) ) {
			$country = WC()->customer->get_billing_country();
			if ( ! empty( $country ) ) {
				return apply_filters( 'qvickly_payments_country', $country );
			}
		}

		/* Ignores whatever country the customer selects on the checkout page, and always uses the store's base location. Only used as fallback. */
		$base_location = wc_get_base_location();
		$country       = $base_location['country'];
		return apply_filters( 'qvickly_payments_country', $country );
	}

	/**
	 * Get the customer address intended for Qvickly API consumption.
	 *
	 * @return array `[billing, shipping]`
	 */
	public function get_address() {
		$customer = $this->cart->get_customer();

		$billing = array(
			'email'     => $customer->get_email(),
			'firstname' => $customer->get_first_name(),
			'lastname'  => $customer->get_last_name(),
			'zip'       => $customer->get_postcode(),
			'city'      => $customer->get_city(),
			'phone'     => $customer->get_billing_phone(),
			'country'   => $customer->get_country(),
			'street'    => $customer->get_address(),
			'street2'   => $customer->get_address_2(),
		);

		$shipping = array(
			'firstname' => $customer->get_first_name(),
			'lastname'  => $customer->get_last_name(),
			'zip'       => $customer->get_postcode(),
			'city'      => $customer->get_city(),
			'phone'     => $customer->get_billing_phone(),
			'country'   => $customer->get_country(),
			'street'    => $customer->get_address(),
			'street2'   => $customer->get_address_2(),
		);

		return array(
			'billing'  => $billing,
			'shipping' => $shipping,
		);
	}

	/**
	 * Get the confirmation URL.
	 *
	 * This is the URL the customer is redirected to after a successful payment.
	 *
	 * @return float
	 */
	public function get_confirmation_url() {
		$url = add_query_arg(
			array(
				'session_id' => Qvickly_Payments()->session()->get_reference(),
				'order_id'   => '{order_id}',
			),
			wc_get_checkout_url()
		);

		return apply_filters( 'qvickly_payments_confirmation_url', $url );
	}

	/**
	 * Get the notification URL.
	 *
	 * This is the URL Qvickly will send the payment status to (i.e., the callback URL).
	 *
	 * @return float
	 */
	public function get_notification_url() {
		return apply_filters( 'qvickly_payments_notification_url', home_url( Callback::API_ENDPOINT ) );
	}
}
