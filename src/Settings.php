<?php
/**
 * Class Settings
 *
 * Defines the plugin's settings.
 */

namespace Krokedil\Qvickly\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
class Settings {

	/**
	 * Returns the settings fields.
	 *
	 * @static
	 * @return array List of filtered setting fields.
	 */
	public static function setting_fields() {
		$settings = array(
			'enabled'              => array(
				'title'       => __( 'Enable', 'qvickly-payments-for-woocommerce' ),
				'label'       => __( 'Enable payment gateway', 'qvickly-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			),
			'account_settings'     => array(
				'title' => __( 'Account settings', 'qvickly-payments-for-woocommerce' ),
				'type'  => 'title',
			),
			'api_id'               => array(
				'title'             => __( 'API ID', 'qvickly-payments-for-woocommerce' ),
				'type'              => 'text',
				'default'           => '',
				'description'       => __( 'Can be found or generated in the merchant portal.', 'qvickly-payments-for-woocommerce' ),
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'api_key'              => array(
				'title'             => __( 'API Key', 'qvickly-payments-for-woocommerce' ),
				'type'              => 'password',
				'default'           => '',
				'description'       => __( 'Can be found or generated in the merchant portal.', 'qvickly-payments-for-woocommerce' ),
				'custom_attributes' => array(
					'autocomplete' => 'off new-password',
				),
			),
			'test_mode'            => array(
				'title'       => __( 'Test mode', 'qvickly-payments-for-woocommerce' ),
				'label'       => 'Enable',
				'type'        => 'checkbox',
				'description' => __( 'While in test mode, the customer will NOT be charged. Test mode is useful for testing and debugging purposes.', 'qvickly-payments-for-woocommerce' ),
				'default'     => 'no',
			),
			'checkout_settings'    => array(
				'title' => __( 'Checkout settings', 'qvickly-payments-for-woocommerce' ),
				'type'  => 'title',
			),
			'title'                => array(
				'title'       => __( 'Title', 'qvickly-payments-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The payment gateway title (appears on checkout page if more than one payment method is available).', 'qvickly-payments-for-woocommerce' ),
				'default'     => 'Qvickly Payments',
			),
			'redirect_description' => array(
				'title'       => __( 'Description', 'qvickly-payments-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'The payment gateway method description (appears on checkout page if more than one payment method is available).', 'qvickly-payments-for-woocommerce' ),
				'default'     => __( 'Betala med Qvickly', 'qvickly-payments-for-woocommerce' ),
				'placeholder' => __( 'Choose your payment method in our checkout.', 'qvickly-payments-for-woocommerce' ),
				'class'       => 'redirect-only',
			),
			'troubleshooting'      => array(
				'title' => __( 'Troubleshooting', 'qvickly-payments-for-woocommerce' ),
				'type'  => 'title',
			),
			'logging'              => array(
				'title'       => __( 'Logging', 'qvickly-payments-for-woocommerce' ),
				'label'       => 'Enable',
				'type'        => 'checkbox',
				'description' => __( 'Logging is required for troubleshooting any issues related to the plugin. It is recommended that you always have it enabled.', 'qvickly-payments-for-woocommerce' ),
				'default'     => 'yes',
			),
			'extended_logging'     => array(
				'title'       => __( 'Detailed logging', 'qvickly-payments-for-woocommerce' ),
				'label'       => __( 'Enable', 'qvickly-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable detailed logging to capture extra data. Use this only when needed for debugging hard-to-replicate issues, as it generates significantly more log entries.', 'qvickly-payments-for-woocommerce' ),
				'default'     => 'no',
			),
		);

		return apply_filters( 'qvickly_payments_settings', $settings );
	}

	/**
	 * Delete the payment gateway's access token transient.
	 *
	 * This should be called whenever the plugin settings are updated.
	 *
	 * @return void
	 */
	public static function maybe_update_access_token() {
		// Always renew the access token when the settings is updated.
		delete_transient( 'qvickly_payments_access_token' );
	}
}
