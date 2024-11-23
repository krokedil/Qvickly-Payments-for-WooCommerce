<?php
namespace Krokedil\Qvickly\Payments\Requests;

use Krokedil\Qvickly\Payments\Requests\Helpers\Store;

/**
 * POST request class.
 */
abstract class POSTRequest extends BaseRequest {
	/**
	 * The request method.
	 *
	 * @var string
	 */
	public $method = 'POST';

	/**
	 * The args second parameter in wp_remote_request.
	 *
	 * @return array
	 */
	public function get_request_args() {
		// Apply any filters before we calculate the hash.
		$body = apply_filters( "{$this->config['slug']}_request_args", $this->get_body() );

		$language    = Store::get_language();
		$credentials = array(
			'id'       => $this->api_id,
			'hash'     => hash_hmac( 'sha512', wp_json_encode( $body ), $this->api_key ),
			'client'   => 'QvicklyPaymentsForWooCommerce:Qvickly:' . QVICKLY_PAYMENTS_VERSION,
			'language' => false !== $language ? $language : 'en',
			'test'     => $this->is_test_mode,
		);

		$body = wp_json_encode(
			array(
				'credentials' => $credentials,
				'data'        => $body,
				'function'    => $this->arguments['function'],
			)
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
