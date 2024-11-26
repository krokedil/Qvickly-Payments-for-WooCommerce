<?php
namespace Krokedil\Qvickly\Payments\Requests\POST;

use Krokedil\Qvickly\Payments\Requests\POSTRequest;
use Krokedil\Qvickly\Payments\Requests\Helpers\Order;
use Krokedil\Qvickly\Payments\Requests\Helpers\Store;

/**
 * Create order request class.
 *
 * Acknowledges an order.
 */
class CreateOrder extends POSTRequest {

	/**
	 * CreateSession constructor.
	 *
	 * @param int $order_id   The Qvickly Payments session ID.
	 */
	public function __construct( $session_id ) {
		$args = get_defined_vars();

		parent::__construct( $args );
		$this->log_title             = 'Create order';
		$this->arguments['function'] = 'activatePayment';
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
