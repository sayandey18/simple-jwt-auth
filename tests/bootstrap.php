<?php
/**
 * PHPUnit bootstrap for the Simple JWT Auth test suite.
 *
 * Requires the WordPress test suite. Point the WP_TESTS_DIR environment variable
 * at the installed test library, or place it at the default temp location.
 *
 * @package Simple_Jwt_Auth
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo "Could not find {$_tests_dir}/includes/functions.php" . PHP_EOL;
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin and define the key-encryption-key for tests.
 */
function simplejwt_manually_load_plugin() {
	// Must be defined before the plugin loads its crypto classes.
	if ( ! defined( 'SIMPLE_JWT_AUTH_ENCRYPT_KEY' ) ) {
		define( 'SIMPLE_JWT_AUTH_ENCRYPT_KEY', '0123456789abcdef0123456789abcdef' );
	}

	require dirname( __DIR__ ) . '/simple-jwt-auth.php';
}
tests_add_filter( 'muplugins_loaded', 'simplejwt_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
