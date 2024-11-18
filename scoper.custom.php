<?php //phpcs:disable

function customize_php_scoper_config( array $config ): array {
    // Ignore the abspath constant when scoping.
	$config['exclude-constants'][] = 'ABSPATH';
	$config['exclude-constants'][] = 'QVICKLY_PAYMENTS_VERSION';
	$config['exclude-constants'][] = 'QVICKLY_PAYMENTS_MAIN_FILE';
	$config['exclude-constants'][] = 'QVICKLY_PAYMENTS_PLUGIN_PATH';
	$config['exclude-constants'][] = 'QVICKLY_PAYMENTS_PLUGIN_URL';
	$config['exclude-classes'][] = 'WooCommerce';
	$config['exclude-classes'][] = 'WC_Product';
	$config['exclude-classes'][] = 'WP_Error';

	$functions = array(
		'Qvickly_Payments',
	);

	$config['exclude-functions'] = array_merge( $config['exclude-functions'] ?? array(), $functions );
	$config['exclude-namespaces'][] = 'Automattic';

	return $config;
}
