<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 * The primary work of the function is to create and migrate the default JWT
 * config options into your WordPress database.
 *
 * @link       https://github.com/sayandey18
 * @since      1.0.0
 *
 * @package    Simple_Jwt_Auth
 * @subpackage Simple_Jwt_Auth/includes
 * @author     Sayan Dey <mr.sayandey18@outlook.com>
 */
class Simple_Jwt_Auth_Activator {
	public static function activate() {
		require_once plugin_dir_path( __DIR__ ) . 'includes/token/class-simple-jwt-auth-token-manager.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/database/class-simple-jwt-auth-refresh-store.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-simple-jwt-auth-upgrader.php';

		// Init the private function.
		self::create_table();
		self::create_option();
		self::migrate_table();
		self::create_refresh_table();
		self::migrate();
	}

	/**
	 * Create a private function that will create `{wp_prefix}_simplejwt_config`
	 * table in your WordPress database.
	 *
	 * @since   1.0.0
	 */
	private static function create_table() {
		global $wpdb;

		// Include the WordPress upgrade file for dbDelta function.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Set table prefix and name.
		$table_name = $wpdb->prefix . 'simplejwt_config';

		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate .= "DEFAULT CHARACTER SET {$wpdb->charset}";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE {$wpdb->collate}";
		}

		// Check if the table name does not already exist on the database.
		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			config_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			config_name VARCHAR(255) NOT NULL DEFAULT '',
			config_value LONGTEXT NOT NULL,
			PRIMARY KEY  (config_id),
			UNIQUE KEY config_name (config_name)
		) {$charset_collate};";

		// Execute the SQL statement to create table.
		dbDelta( $sql );
	}

	/**
	 * Create a private function that will create required plugin options in the
	 * WordPress `wp_options` table.
	 *
	 * @since   1.0.0
	 */
	private static function create_option() {
		// Create an option for uninstallation.
		add_option( 'simplejwt_drop_configs', true );
	}

	/**
	 * Create the refresh-token table.
	 *
	 * @since   2.0.0
	 */
	private static function create_refresh_table() {
		\Simple_Jwt_Auth\Database\RefreshStore::create_table();
	}

	/**
	 * Run versioned upgrade routines (e.g. the `enable_auth` migration).
	 *
	 * @since   2.0.0
	 */
	private static function migrate() {
		Simple_Jwt_Auth_Upgrader::maybe_upgrade();
	}

	/**
	 * Create a private function that will insert the plugin default config
	 * data into `{wp_prefix}_simplejwt_config` table.
	 *
	 * @since   1.0.0
	 */
	private static function migrate_table() {
		global $wpdb;

		// Set the table name.
		$table_name = $wpdb->prefix . 'simplejwt_config';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$db_result = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( is_null( $db_result ) ) {
			return;
		}

		// Define supported algorithms from the single source of truth.
		$supported_algo = maybe_serialize( \Simple_Jwt_Auth\Token\TokenManager::ALGORITHMS );

		// Default config to insert.
		$default_config = array(
			array(
				'config_name'  => 'enable_auth',
				'config_value' => '0',
			),
			array(
				'config_name'  => 'algorithm',
				'config_value' => 'HS256',
			),
			array(
				'config_name'  => 'secret_key',
				'config_value' => '',
			),
			array(
				'config_name'  => 'public_key',
				'config_value' => '',
			),
			array(
				'config_name'  => 'private_key',
				'config_value' => '',
			),
			array(
				'config_name'  => 'enable_cors',
				'config_value' => '0',
			),
			array(
				'config_name'  => 'disable_xmlrpc',
				'config_value' => '0',
			),
			array(
				'config_name'  => 'supported_algo',
				'config_value' => $supported_algo,
			),
			array(
				'config_name'  => 'token_expiration',
				'config_value' => '900',
			),
			array(
				'config_name'  => 'refresh_expiration',
				'config_value' => '1209600',
			),
		);

		// Insert default config data if it does not already exist.
		foreach ( $default_config as $config ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$column_exists = $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT config_id FROM {$table_name} WHERE config_name = %s",
					$config['config_name']
				)
			);

			if ( is_null( $column_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$table_name,
					array(
						'config_name'  => $config['config_name'],
						'config_value' => $config['config_value'],
					),
					array( '%s', '%s' )
				);
			}
		}
	}
}
