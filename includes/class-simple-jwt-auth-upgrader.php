<?php

/**
 * Handles versioned upgrade routines when the plugin is activated.
 *
 * The plugin has historically never stored a database version marker, so this
 * class introduces `simplejwt_db_version` and runs one-off migrations against
 * it. It is deliberately self-contained (uses `$wpdb` directly, no autoloader
 * dependency) so it can run reliably during the activation hook.
 *
 * @link       https://github.com/sayandey18/simple-jwt-auth
 * @since      2.0.0
 *
 * @package    Simple_Jwt_Auth
 * @subpackage Simple_Jwt_Auth/includes
 * @author     Sayan Dey <mr.sayandey18@outlook.com>
 */
class Simple_Jwt_Auth_Upgrader {

	/**
	 * Run any pending migrations.
	 *
	 * @since 2.0.0
	 */
	public static function maybe_upgrade() {
		$stored = get_option( 'simplejwt_db_version', '' );

		if ( version_compare( $stored, SIMPLE_JWT_AUTH_VERSION, '>=' ) ) {
			return;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'simplejwt_config';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$secret_key = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT config_value FROM {$table_name} WHERE config_name = %s",
				'secret_key'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$private_key = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT config_value FROM {$table_name} WHERE config_name = %s",
				'private_key'
			)
		);

		// A pre-2.0.0 install that has signing keys configured has been running
		// with JWT auth effectively always-on (the `enable_auth` toggle was never
		// read). Preserve that behavior by enabling auth explicitly.
		$has_keys = ( ! is_null( $secret_key ) && '' !== $secret_key )
			|| ( ! is_null( $private_key ) && '' !== $private_key );

		if ( $has_keys ) {
			self::set_enable_auth( true );

			// Flag for the admin upgrade notice.
			update_option( 'simplejwt_upgrade_notice', '1', false );
		}

		update_option( 'simplejwt_db_version', SIMPLE_JWT_AUTH_VERSION, false );
	}

	/**
	 * Upsert the `enable_auth` config row.
	 *
	 * @since 2.0.0
	 * @param bool $enabled Whether authentication should be enabled.
	 */
	private static function set_enable_auth( bool $enabled ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'simplejwt_config';
		$value      = $enabled ? '1' : '0';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT config_id FROM {$table_name} WHERE config_name = %s",
				'enable_auth'
			)
		);

		if ( ! is_null( $exists ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update(
				$table_name,
				array( 'config_value' => $value ),
				array( 'config_name' => 'enable_auth' ),
				array( '%s' ),
				array( '%s' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table_name,
				array(
					'config_name'  => 'enable_auth',
					'config_value' => $value,
				),
				array( '%s', '%s' )
			);
		}
	}
}
