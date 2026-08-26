<?php

/**
 * Simple JWT Auth - Secure and Protect REST endpoints using JSON Web Tokens.
 *
 * @link              https://github.com/sayandey18
 * @since             1.0.0
 * @package           Simple_Jwt_Auth
 *
 * @wordpress-plugin
 * Plugin Name:       Simple JWT Auth
 * Plugin URI:        https://github.com/sayandey18/simple-jwt-auth
 * Description:       Extends the WordPress REST API using JSON Web Tokens for robust authentication and authorization. It provides a secure and reliable way to access and manage WordPress data from external applications, making it ideal for building headless CMS solutions.
 * Version:           2.0.0
 * Requires at least: 7.0
 * Tested up to:      7.1
 * Requires PHP:      8.2
 * Author:            Sayan Dey
 * Author URI:        https://github.com/sayandey18
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       simple-jwt-auth
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'SIMPLE_JWT_AUTH_VERSION', '2.0.0' );

/**
 * Define the REST API namespace version. Decoupled from the plugin version so
 * that a semver bump (e.g. 2.0.0) does not silently change the `auth/v1` route
 * namespace. Bump only when a breaking route change is introduced.
 */
define( 'SIMPLE_JWT_AUTH_API_VERSION', '1' );

/**
 * Define the other required options.
 */
define( 'SIMPLE_JWT_AUTH_ENDPOINT', 'auth' );
define( 'SIMPLE_JWT_AUTH_TEXT_DOMAIN', 'simple-jwt-auth' );
define( 'SIMPLE_JWT_AUTH_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-simple-jwt-auth-activator.php
 */
function simplejwt_auth_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-simple-jwt-auth-activator.php';
	Simple_Jwt_Auth_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-simple-jwt-auth-deactivator.php
 */
function simplejwt_auth_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-simple-jwt-auth-deactivator.php';
	Simple_Jwt_Auth_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'simplejwt_auth_activate' );
register_deactivation_hook( __FILE__, 'simplejwt_auth_deactivate' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-simple-jwt-auth.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since   1.0.0
 */
function simplejwt_auth_run() {
	$plugin = new Simple_Jwt_Auth();
	$plugin->run();
}

simplejwt_auth_run();
