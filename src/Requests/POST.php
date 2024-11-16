<?php
namespace Krokedil\Qvickly\Payments\Requests;

/**
 * POST request class.
 */
abstract class POST extends BaseRequest {

	/**
	 * POST constructor.
	 *
	 * @param array $args Arguments that should be accessible from within the request.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( $args );
		$this->method = 'POST';
	}

	/**
	 * The args second parameter in wp_remote_request.
	 *
	 * @return array
	 */
	public function get_request_args() {
		// Apply any filters before we calculate the hash.
		$body = apply_filters( "{$this->config['slug']}_request_args", $this->get_body() );

		// The content of get_body() must be assigned to the 'data' key in the body array.
		$body = array( 'data' => $this->get_body() );
		$hash = hash_hmac( 'sha512', wp_json_encode( $body ), $this->settings['api_key'] );

		$is_test_mode = $this->settings['test_mode'] ? 'true' : 'false';
		$credentials  = array(
			'id'     => "{$this->settings['api_id']}",
			'hash'   => $hash,
			'client' => 'QvicklyPaymentsForWooCommerce:Qvickly:' . QVICKLY_PAYMENTS_VERSION,
			'test'   => $is_test_mode,
		);

		$body = wp_json_encode(
			array(
				'credentials' => $credentials,
				'function'    => $this->arguments['function'],
			) + $body
		);

		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => $this->method,
			'body'       => $body,
		);
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	abstract protected function get_body();
}
