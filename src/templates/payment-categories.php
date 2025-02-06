<?php
/**
 * Replace the template checkout/payment-method.php with Qvickly's payment categories.
 */

$order_id = absint( get_query_var( 'order-pay', 0 ) );
if ( ! empty( $order_id ) ) {
	$_order = wc_get_order( $order_id );
}

Qvickly_Payments()->session()->get_session( isset( $order ) && ! empty( $order ) ? $order : null );

$payment_categories = array(
	array(
		'id'          => 'card',
		'name'        => __( 'Card', 'qvickly-payments-for-woocommerce' ),
		'description' => __( 'Pay with card', 'qvickly-payments-for-woocommerce' ),
		'logo'        => Qvickly_Payments()->gateway()->get_icon(),
	),
	array(
		'id'          => 'myqvickly',
		'name'        => __( 'myQvickly', 'qvickly-payments-for-woocommerce' ),
		'description' => __( 'Pay with Qvickly', 'qvickly-payments-for-woocommerce' ),
		'logo'        => Qvickly_Payments()->gateway()->get_icon(),
	),
);

$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
$gateway            = $available_gateways['qvickly_payments'];
$chosen_gateway     = $available_gateways[ array_key_first( $available_gateways ) ];

foreach ( apply_filters( 'qvickly_payments_available_payment_categories', $payment_categories ) as $payment_category ) {
	$category_id = "qvickly_payments_{$payment_category['id']}";

	$gateway              = $available_gateways['qvickly_payments'] ?? $available_gateways[ $category_id ];
	$gateway->id          = $category_id;
	$gateway->icon        = $payment_category['logo'] ?? null;
	$gateway->title       = $payment_category['name'];
	$gateway->description = $payment_category['description'];

	// Make sure the first payment category is chosen by default.
	if ( false !== strpos( $chosen_gateway->id, 'qvickly_payments' ) || $gateway->chosen ) {
		$gateway->chosen = false;
		if ( $gateway->title === $payment_categories[ array_key_first( $payment_categories ) ]['type'] ) {
			$gateway->chosen = true;
		}
	}

	// For "Linear Checkout for WooCommerce by Cartimize" to work, we cannot output any HTML.
	if ( did_action( 'cartimize_get_payment_methods_html' ) === 0 ) {
		wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
	}
}
