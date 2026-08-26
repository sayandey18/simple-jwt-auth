<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow of control:
 *
 * - This method should be static.
 * - Check if the $_REQUEST content actually is the plugin name.
 * - Run an admin referrer check to make sure it goes through authentication.
 * - Verify the output of $_GET makes sense.
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * @link       https://github.com/sayandey18
 * @since      1.0.0
 *
 * @package    Simple_Jwt_Auth
 */

/**
 * If the `uninstall.php` is not called by WordPress, die this.
 *
 * @since   1.0.0
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

/**
 * Check if the option is set for remove plugin's config data.
 *
 * @since   1.0.0
 */
$config_status = filter_var(
	get_option( 'simplejwt_drop_configs' ),
	FILTER_VALIDATE_BOOLEAN
);

/**
 * Drop a custom database table for JWT configs and option data.
 *
 * @since   1.0.0
 */
global $wpdb;

// Set the table name for configs data.
$table_name = $wpdb->prefix . 'simplejwt_config';

// Set the table name for refresh tokens.
$refresh_table_name = $wpdb->prefix . 'simplejwt_refresh_tokens';

// If the option is true, remove the table and the option.
if ( $config_status ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$refresh_table_name}" );

	// Delete the previously defined option.
	delete_option( 'simplejwt_drop_configs' );
}

// Remove upgrade/version markers.
delete_option( 'simplejwt_db_version' );
delete_option( 'simplejwt_upgrade_notice' );
