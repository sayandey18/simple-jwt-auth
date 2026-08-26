<?php
/**
 * Integration tests for the refresh-token store.
 *
 * @package Simple_Jwt_Auth
 */

use Simple_Jwt_Auth\Database\RefreshStore;
use Simple_Jwt_Auth\Token\TokenManager;

/**
 * @coversDefaultClass \Simple_Jwt_Auth\Database\RefreshStore
 * @group db
 */
class Test_Refresh_Store extends WP_UnitTestCase {

	/**
	 * Set up the schema and start from a clean refresh-token table.
	 */
	public function setUp(): void {
		parent::setUp();

		Simple_Jwt_Auth_Activator::activate();

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM {$wpdb->prefix}simplejwt_refresh_tokens" );
	}

	/**
	 * Storing a token makes it findable by hash.
	 */
	public function test_store_and_find() {
		$raw  = TokenManager::generate_refresh_token();
		$hash = TokenManager::hash_refresh_token( $raw );

		$this->assertTrue( RefreshStore::store( 42, $hash, 'family-a', 120 ) );

		$row = RefreshStore::find_by_hash( $hash );

		$this->assertNotNull( $row );
		$this->assertSame( '42', (string) $row['user_id'] );
		$this->assertSame( 0, (int) $row['revoked'] );
	}

	/**
	 * Rotation marks the old token revoked and creates a successor in the family.
	 */
	public function test_rotate_creates_successor() {
		$old_raw  = TokenManager::generate_refresh_token();
		$old_hash = TokenManager::hash_refresh_token( $old_raw );
		$family   = TokenManager::generate_family_id();

		RefreshStore::store( 42, $old_hash, $family, 120 );

		$new_raw  = TokenManager::generate_refresh_token();
		$new_hash = TokenManager::hash_refresh_token( $new_raw );

		$this->assertTrue( RefreshStore::rotate( $old_hash, $new_hash, $family, 120 ) );

		$old = RefreshStore::find_by_hash( $old_hash );
		$new = RefreshStore::find_by_hash( $new_hash );

		$this->assertSame( 1, (int) $old['revoked'] );
		$this->assertNotNull( $old['rotated_at'] );
		$this->assertSame( 0, (int) $new['revoked'] );
		$this->assertSame( $family, $new['family_id'] );
	}

	/**
	 * Revoking a family revokes all its members.
	 */
	public function test_revoke_family() {
		$family = TokenManager::generate_family_id();

		$h1 = TokenManager::hash_refresh_token( TokenManager::generate_refresh_token() );
		$h2 = TokenManager::hash_refresh_token( TokenManager::generate_refresh_token() );

		RefreshStore::store( 42, $h1, $family, 120 );
		RefreshStore::store( 42, $h2, $family, 120 );

		RefreshStore::revoke_family( $family );

		$this->assertSame( 1, (int) RefreshStore::find_by_hash( $h1 )['revoked'] );
		$this->assertSame( 1, (int) RefreshStore::find_by_hash( $h2 )['revoked'] );
	}

	/**
	 * Revoking all tokens for a user only affects that user.
	 */
	public function test_revoke_all_for_user() {
		$family_a = TokenManager::generate_family_id();
		$family_b = TokenManager::generate_family_id();

		$h_a = TokenManager::hash_refresh_token( TokenManager::generate_refresh_token() );
		$h_b = TokenManager::hash_refresh_token( TokenManager::generate_refresh_token() );

		RefreshStore::store( 1, $h_a, $family_a, 120 );
		RefreshStore::store( 2, $h_b, $family_b, 120 );

		RefreshStore::revoke_all_for_user( 1 );

		$this->assertSame( 1, (int) RefreshStore::find_by_hash( $h_a )['revoked'] );
		$this->assertSame( 0, (int) RefreshStore::find_by_hash( $h_b )['revoked'] );
	}
}
