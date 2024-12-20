<?php
namespace Krokedil\Qvickly\Payments\Requests\GET;

use Krokedil\Qvickly\Payments\Requests\GET;

/**
 * Create order request class.
 *
 * Authorizes a checkout payment. This happens when the customer has completed the payment while still on the checkout page.
 */
class GetSession extends GET {

	/**
	 * CreateSession constructor.
	 *
	 * @param string $session_id The Qvickly session ID.
	 */
	public function __construct( $session_id ) {
		$args = get_defined_vars();

		parent::__construct( $args );
		$this->log_title = 'Get session';
		$this->endpoint  = "/v1/payment-sessions/{$session_id}";
	}
}
