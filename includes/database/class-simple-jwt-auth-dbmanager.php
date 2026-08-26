<?php

/* The namespace to avoid class name collisions. */
namespace Simple_Jwt_Auth\Database;

/**
 * Responsible for managing the plugin config data.
 *
 * It allows updating or inserting config values into the database
 * while ensuring that the `config_name` remains unchanged.
 * Only `config_value` is updated or inserted based on the provided
 * configuration data.
 *
 * @link       https://github.com/sayandey18
 * @since      1.0.0
 *
 * @package    Simple_Jwt_Auth
 * @subpackage Simple_Jwt_Auth\Database
 * @author     Sayan Dey <mr.sayandey18@outlook.com>
 */
class DBManager {
	public function __construct() {
		// Constructor code if needed
	}

	/**
	 * Get the JWT config (key|value) as an array and insert or update
	 * into the database.
	 *
	 * @since   1.0.0
	 * @param   string $configs The config key and value.
	 * @return  bool
	 */
	public static function save_config( array $configs ) {
		global $wpdb;

		// Set the config table name.
		$table_name = $wpdb->prefix . 'simplejwt_config';

		foreach ( $configs as $config_name => $config_value ) {
			if ( ! isset( $config_value ) || $config_value === '' ) {
				continue;
			}

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$db_result = $wpdb->get_var(
				$wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT config_id FROM {$table_name} WHERE config_name = %s",
					$config_name
				)
			);

			if ( $db_result ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
				$result = $wpdb->update(
					$table_name,
					array( 'config_value' => $config_value ),
					array( 'config_name' => $config_name ),
					array( '%s' ),
					array( '%s' )
				);
			} else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
				$result = $wpdb->insert(
					$table_name,
					array(
						'config_name'  => $config_name,
						'config_value' => $config_value,
					),
					array( '%s', '%s' )
				);
			}

			if ( $result === false ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Read the `{wp_prefix}_simplejwt_config` table and return the
	 * config value to used in authenticate.
	 *
	 * @since   1.0.0
	 * @param   string $config_name
	 * @return  string|false
	 */
	public static function get_config( string $config_name ) {
		global $wpdb;

		// Set the table name.
		$table_name = $wpdb->prefix . 'simplejwt_config';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->get_var(
			$wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT config_value FROM {$table_name} WHERE config_name = %s",
				$config_name
			)
		);

		// Check if the result is null return false.
		if ( is_null( $result ) ) {
			return false;
		}

		return $result;
	}
}
