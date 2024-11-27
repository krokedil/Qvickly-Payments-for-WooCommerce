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
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	abstract protected function get_body();
}
