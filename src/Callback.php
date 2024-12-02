<?php
/**
 * Class Callback.
 *
 * Handles callbacks (also known as "notifications") from Qvickly.
 */

namespace Krokedil\Qvickly\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Callback.
 */
class Callback {

	public const REST_API_NAMESPACE = 'krokedil/qvickly/payments/v1';
	public const REST_API_ENDPOINT  = '/callback';
	public const API_ENDPOINT       = 'wp-json/' . self::REST_API_NAMESPACE . self::REST_API_ENDPOINT;


	/**
	 * Callback constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'qvickly_payments_scheduled_callback', array( $this, 'handle_scheduled_callback' ) );
	}

	/**
	 * Register the REST API route(s).
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_API_NAMESPACE,
			self::REST_API_ENDPOINT,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'callback_handler' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handles a callback ("notification") from Qvickly.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function callback_handler( $request ) {
		$context = array(
			'filter'   => current_filter(),
			'function' => __FUNCTION__,
		);

		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			Qvickly_Payments()->logger()->debug( '[CALLBACK]: Received callback without parameters.', $context );
			return new \WP_Error( 'missing_params', 'Missing parameters.', array( 'status' => 400 ) );
		}

		$params             = filter_var_array( $params, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$context['request'] = $params;

		$payment_number = wc_get_var( $params['number'] );
		$event_type     = wc_get_var( $params['eventType'] );

		Qvickly_Payments()->logger()->debug( '[CALLBACK]: Received callback.', $context );

		if ( 'com.qvickly.authorization.create' !== $event_type ) {
			Qvickly_Payments()->logger()->debug( '[CALLBACK]: Unsupported event type.', $context );
			return new \WP_REST_Response( array(), 200 );
		}

		if ( empty( $payment_number ) ) {
			Qvickly_Payments()->logger()->error( '[CALLBACK]: Missing payment ID.', $context );
			return new \WP_Error( 'missing_session_id', 'Missing session ID.', array( 'status' => 404 ) );
		}

		$order = Qvickly_Payments()->gateway()->get_order_by_payment_number( $payment_number );
		if ( empty( $order ) ) {
			Qvickly_Payments()->logger()->error( "[CALLBACK]: Order '{$payment_number}' not found.", $context );
			return new \WP_Error( 'order_not_found', 'Order not found.', array( 'status' => 404 ) );
		}

		$status = $this->schedule_callback( $payment_number ) ? 200 : 500;
		if ( $status >= 500 ) {
			return new \WP_Error( 'scheduling_failed', __( 'Failed to schedule callback.', 'qvickly-payments-for-woocommerce' ), array( 'status' => $status ) );
		}
		return new \WP_REST_Response( array(), $status );
	}

	/**
	 * Handles a scheduled callback.
	 *
	 * @param string $payment_number The session ID.
	 * @return void
	 */
	public function handle_scheduled_callback( $payment_number ) {
		$context = array(
			'filter'     => current_filter(),
			'function'   => __FUNCTION__,
			'session_id' => $payment_number,
		);

		$order = Qvickly_Payments()->gateway()->get_order_by_payment_number( $payment_number );
		if ( empty( $order ) ) {
			Qvickly_Payments()->logger()->error( '[CALLBACK]: Order not found.', $context );
			return;
		}

		Qvickly_Payments()->gateway()->confirm_order( $order, $context );
	}

	/**
	 * Schedule a callback for later processing.
	 *
	 * @param string $payment_number The session ID.
	 * @return bool True if the callback was scheduled, false otherwise.
	 */
	private function schedule_callback( $payment_number ) {
		$context = array(
			'filter'     => current_filter(),
			'function'   => __FUNCTION__,
			'session_id' => $payment_number,
		);

		$hook              = 'qvickly_payments_scheduled_callback';
		$as_args           = array(
			'hook'   => $hook,
			'status' => \ActionScheduler_Store::STATUS_PENDING,
		);
		$scheduled_actions = as_get_scheduled_actions( $as_args, OBJECT );

		/**
		 * Loop all actions to check if this one has been scheduled already.
		 *
		 * @var \ActionScheduler_Action $action The action from the Action scheduler.
		 */
		foreach ( $scheduled_actions as $action ) {
			$action_args = $action->get_args();
			if ( $payment_number === $action_args['session_id'] ) {
				Qvickly_Payments()->logger()->debug( '[CALLBACK]: The order is already scheduled for processing.', $context );
				return true;
			}
		}

		// If we get here, we should be good to create a new scheduled action, since none are currently scheduled for this order.
		$did_schedule = as_schedule_single_action(
			time() + 60,
			$hook,
			array(
				'session_id' => $payment_number,
			)
		);

		$context['schedule_id'] = $did_schedule;
		Qvickly_Payments()->logger()->debug( '[CALLBACK]: Successfully scheduled callback.', $context );

		return 0 !== $did_schedule;
	}
}
