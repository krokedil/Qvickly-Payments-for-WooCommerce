<?php
/**
 * Class API.
 *
 * API gateway.
 */

namespace Krokedil\Qvickly\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class API.
 */
class API {

	/**
	 * Create a new Qvickly session.
	 *
	 * @return \WP_Error|array
	 */
	public function create_session() {
		$request  = new Requests\POST\CreateSession();
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Update a Qvickly session.
	 *
	 * @return \WP_Error|array
	 */
	public function update_session() {
		$request  = new Requests\POST\UpdateSession();
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Create an order in Qvickly.
	 *
	 * This can be considered acknowledging an order.
	 *
	 * @param int $session_id   The Qvickly payment number.
	 *
	 * @return \WP_Error|array
	 */
	public function create_order( $session_id ) {
		$request  = new Requests\POST\CreateOrder( $session_id );
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Get a session from Qvickly.
	 *
	 * @param string $session_id The Qvickly payment number.
	 *
	 * @return \WP_Error|array
	 */
	public function get_session( $session_id ) {
		$request  = new Requests\POST\GetSession( $session_id );
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Checks if an API error occurred.
	 *
	 * Qvickly Payments always return a `200` respond when a request is received, even if the request body is invalid.
	 *
	 * @param array|\WP_Error $body The response body.
	 * @return bool
	 */
	public static function is_api_error( $body ) {
		// Since we cannot rely on `is_wp_error` or HTTP codes, we have to check if the `code` property is set in the response body which indicates an error has occurred.
		if ( isset( $body['code'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks for API errors, and determines whether these should be printed to the customer.
	 *
	 * @param array $response The API response.
	 * @return array The API response.
	 */
	public function check_for_api_error( $response ) {
		if ( is_wp_error( $response ) ) {
			if ( ! is_admin() ) {
				$this->print_error( $response );
			}
		}

		return $response;
	}

	/**
	 * Prints error message as notices.
	 *
	 * Sometimes an error message cannot be printed (e.g., in a cronjob environment) where there is
	 * no front end to display the error message, or otherwise irrelevant for human consumption. For that reason, we have to check if the print functions are undefined.
	 *
	 * @param \WP_Error $wp_error The error object.
	 * @return void
	 */
	private function print_error( $wp_error ) {
		if ( is_ajax() && function_exists( 'wc_add_notice' ) ) {
			$print = 'wc_add_notice';
		} elseif ( function_exists( 'wc_print_notice' ) ) {
			$print = 'wc_print_notice';
		}

		if ( isset( $print ) ) {
			foreach ( $wp_error->get_error_messages() as $error ) {
				$message = $error;
				if ( is_array( $error ) ) {
					$error = array_filter( $error );
					foreach ( $error as $message ) {
						$print( $message, 'error' );
					}
				} else {
					$print( $message, 'error' );
				}
			}
		}
	}
}
