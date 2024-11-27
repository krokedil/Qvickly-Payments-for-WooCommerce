<?php
namespace Krokedil\Qvickly\Payments\Requests\POST;

use Krokedil\Qvickly\Payments\Requests\POSTRequest;

/**
 * Retrieve the payment info for a session.
 */
class GetSession extends POSTRequest {
	/**
	 * CreateSession constructor.
	 *
	 * @param string $session_id The Qvickly session ID.
	 */
	public function __construct( $session_id ) {
		$args = get_defined_vars();

		parent::__construct( $args );
		$this->log_title             = 'Get session';
		$this->arguments['function'] = 'getPaymentinfo';
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	public function get_body() {
		return array(
			'number' => $this->arguments['session_id'],
		);
	}
}
