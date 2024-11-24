<?php
namespace Krokedil\Qvickly\Payments\Requests\Helpers;

use KrokedilQvicklyPaymentsDeps\Krokedil\WooCommerce\Order\Order as BaseOrder;
use KrokedilQvicklyPaymentsDeps\Krokedil\WooCommerce as KrokedilWC;
/**
 * Class Order
 *
 * @package Krokedil\Qvickly\Payments\Requests\Helpers
 */
class Order extends BaseOrder {
	/**
	 * Order constructor.
	 *
	 * @param \WC_Order|int|string $order The WooCommerce order or numeric id.
	 */
	public function __construct( $order ) {
		$config = array(
			'slug'         => 'qvickly_payments',
			'price_format' => 'minor',
		);

		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order );
		}

		parent::__construct( $order, $config );
	}

	/**
	 * Get the country.
	 *
	 * @return string
	 */
	public function get_country() {
		return $this->order->get_billing_country();
	}

	/**
	 * Get the currency code.
	 *
	 * @return string The currency code.
	 */
	public function get_currency() {
		return $this->order->get_currency();
	}

	/**
	 * Get the order ID.
	 *
	 * @return string The order id.
	 */
	public function get_reference() {
		return $this->order->get_order_number();
	}

	/**
	 * Get the customer data, and format to match the Qvickly client SDK.
	 *
	 * @return array
	 */
	public function get_customer() {
		$customer_data = parent::get_customer();

		$customer = array(
			'companyId'       => $this->order->get_meta( '_billing_company_number' ),
			'email'           => $customer_data->get_billing_email(),
			'firstName'       => $customer_data->get_billing_first_name(),
			'lastName'        => $customer_data->get_billing_last_name(),
			'phone'           => $customer_data->get_billing_phone(),
			'reference1'      => $this->get_reference(),
			'reference2'      => '',
			'billingAddress'  => array(
				'attentionName' => $customer_data->get_billing_first_name(),
				'city'          => $customer_data->get_billing_city(),
				'companyName'   => $customer_data->get_billing_company(),
				'country'       => $customer_data->get_billing_country(),
				'postalCode'    => $customer_data->get_billing_postcode(),
				'streetAddress' => $customer_data->get_billing_address_1(),
			),
			'shippingAddress' => array(
				'attentionName' => $customer_data->get_shipping_first_name(),
				'city'          => $customer_data->get_shipping_city(),
				'companyName'   => $customer_data->get_shipping_company(),
				'country'       => $customer_data->get_shipping_country(),
				'postalCode'    => $customer_data->get_shipping_postcode(),
				'streetAddress' => $customer_data->get_shipping_address_1(),
				'contact'       => array(
					'email'     => $customer_data->get_billing_email(),
					'firstName' => $customer_data->get_billing_first_name(),
					'lastName'  => $customer_data->get_billing_last_name(),
					'phone'     => $customer_data->get_billing_phone(),
				),
			),
		);

		return $customer;
	}
}
