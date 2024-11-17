<?php
namespace Krokedil\Qvickly\Payments\Requests;

use KrokedilQvicklyPaymentsDeps\Krokedil\WpApi\Request;

/**
 * Class BaseRequest
 *
 * Base request class.
 */
abstract class BaseRequest extends Request {

	protected $is_test_mode;
	protected $api_id;
	protected $api_key;

	/**
	 * BaseRequest constructor.
	 *
	 * @param array $args The request args.
	 */
	public function __construct( $args = array() ) {
		$this->settings = get_option( 'woocommerce_qvickly_payments_settings', array() );
		$config         = array(
			'slug'               => 'qvickly_payments',
			'plugin_version'     => QVICKLY_PAYMENTS_VERSION,
			'plugin_short_name'  => 'QP',
			'logging_enabled'    => wc_string_to_bool( $this->settings['logging'] ),
			'extended_debugging' => wc_string_to_bool( $this->settings['extended_logging'] ),
			'base_url'           => 'https://api.qvickly.io',
		);

		$this->is_test_mode = $this->settings['test_mode'] ? 'true' : 'false';
		$this->api_id       = $this->settings['api_id'];
		$this->api_key      = $this->settings['api_key'];

		parent::__construct( $config, $this->settings, $args );
	}

	/**
	 * Retrieve the auth header.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
		return array(
			'Content-Type' => 'application/json',
		);
	}

	/**
	 * Calculate the auth headers. Has to be implemented by the child class.
	 *
	 * @return string
	 */
	protected function calculate_auth() {
		return ''; // noop.
	}

	/**
	 * Get the error message.
	 *
	 * @param array $response The response.
	 *
	 * @return \WP_Error
	 */
	protected function get_error_message( $response ) {
		$error_message = '';
		$errors        = json_decode( $response['body'], true );
		if ( ! empty( $errors ) ) {
			foreach ( $errors['errors'] as $i => $error ) {
				$error_message .= "[$i] " . implode( ' ', $error );
			}
		}
		$code          = wp_remote_retrieve_response_code( $response );
		$error_message = empty( $error_message ) ? $response['response']['message'] : $error_message;
		return new \WP_Error( $code, $error_message );
	}
}
