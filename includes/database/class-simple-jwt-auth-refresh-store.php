<?php

/* The namespace to avoid class name collisions. */
namespace Simple_Jwt_Auth\Database;

/**
 * Persistence layer for opaque refresh tokens.
 *
 * Refresh tokens are stored by their SHA-256 hash only; the raw token is never
 * written to the database. Each token belongs to a rotation "family" so that a
 * detected replay can revoke the whole family. A `rotated_at` timestamp anchors
 * a short grace window that absorbs benign concurrent client retries.
 *
 * @link       https://github.com/sayandey18/simple-jwt-auth
 * @since      2.0.0
 *
 * @package    Simple_Jwt_Auth
 * @subpackage Simple_Jwt_Auth\Database
 * @author     Sayan Dey <mr.sayandey18@outlook.com>
 */
class RefreshStore {

	/**
	 * Create the refresh-token table (idempotent).
	 *
	 * @since 2.0.0
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name = $wpdb->prefix . 'simplejwt_refresh_tokens';

		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate .= "DEFAULT CHARACTER SET {$wpdb->charset}";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE {$wpdb->collate}";
		}

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			token_hash CHAR(64) NOT NULL,
			family_id CHAR(32) NOT NULL,
			expires_at DATETIME NOT NULL,
			revoked TINYINT(1) NOT NULL DEFAULT 0,
			rotated_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY user_id (user_id),
			KEY family_id (family_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Store a brand new refresh token.
	 *
	 * @since 2.0.0
	 * @param int    $user_id    The user id.
	 * @param string $token_hash SHA-256 hash of the raw token.
	 * @param string $family_id  Rotation family id.
	 * @param int    $ttl        Lifetime in seconds.
	 * @return bool
	 */
	public static function store( int $user_id, string $token_hash, string $family_id, int $ttl ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'simplejwt_refresh_tokens';
		$now        = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (bool) $wpdb->insert(
			$table_name,
			array(
				'user_id'    => $user_id,
				'token_hash' => $token_hash,
				'family_id'  => $family_id,
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + $ttl ),
				'revoked'    => 0,
				'created_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Rotate a refresh token: mark the presented token as rotated and insert
	 * its successor in the same family.
	 *
	 * @since 2.0.0
	 * @param string $old_hash     SHA-256 hash of the presented token.
	 * @param string $new_hash     SHA-256 hash of the successor token.
	 * @param string $family_id    Rotation family id.
	 * @param int    $ttl          Lifetime of the successor, in seconds.
	 * @return bool
	 */
	public static function rotate( string $old_hash, string $new_hash, string $family_id, int $ttl ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'simplejwt_refresh_tokens';
		$now        = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->update(
			$table_name,
			array(
				'revoked'    => 1,
				'rotated_at' => $now,
			),
			array( 'token_hash' => $old_hash ),
			array( '%d', '%s' ),
			array( '%s' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'user_id'    => self::get_user_id_by_hash( $old_hash ),
				'token_hash' => $new_hash,
				'family_id'  => $family_id,
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + $ttl ),
				'revoked'    => 0,
				'created_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		return ( false !== $updated && false !== $inserted );
	}

	/**
	 * Find a refresh-token row by its hash.
	 *
	 * @since 2.0.0
	 * @param string $token_hash SHA-256 hash of the raw token.
	 * @return array|null
	 */
	public static function find_by_hash( string $token_hash ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'simplejwt_refresh_tokens';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table_name} WHERE token_hash = %s",
				$token_hash
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Revoke every token in a rotation family.
	 *
	 * @since 2.0.0
	 * @param string $family_id The family id.
	 * @return int|false Rows affected, or false on failure.
	 */
	public static function revoke_family( string $family_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'simplejwt_refresh_tokens';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->update(
			$table_name,
			array( 'revoked' => 1 ),
			array( 'family_id' => $family_id ),
			array( '%d' ),
			array( '%s' )
		);
	}

	/**
	 * Revoke every token issued to a user.
	 *
	 * @since 2.0.0
	 * @param int $user_id The user id.
	 * @return int|false Rows affected, or false on failure.
	 */
	public static function revoke_all_for_user( int $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'simplejwt_refresh_tokens';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->update(
			$table_name,
			array( 'revoked' => 1 ),
			array( 'user_id' => $user_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Purge expired refresh tokens.
	 *
	 * @since 2.0.0
	 * @return int|false Rows deleted, or false on failure.
	 */
	public static function purge_expired() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'simplejwt_refresh_tokens';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$table_name} WHERE expires_at < %s",
				current_time( 'mysql', true )
			)
		);
	}

	/**
	 * Resolve the user id associated with a token hash.
	 *
	 * @since 2.0.0
	 * @param string $token_hash SHA-256 hash of the raw token.
	 * @return int
	 */
	private static function get_user_id_by_hash( string $token_hash ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'simplejwt_refresh_tokens';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT user_id FROM {$table_name} WHERE token_hash = %s",
				$token_hash
			)
		);

		return $user_id ? (int) $user_id : 0;
	}
}
