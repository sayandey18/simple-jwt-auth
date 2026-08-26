<?php
/**
 * Unit tests for the token manager (pure helpers + algorithm allowlist).
 *
 * @package Simple_Jwt_Auth
 */

use Simple_Jwt_Auth\Token\TokenManager;

/**
 * @coversDefaultClass \Simple_Jwt_Auth\Token\TokenManager
 */
class Test_Token_Manager extends WP_UnitTestCase {

	/**
	 * The allowlist reflects only algorithms the bundled JWT library supports.
	 */
	public function test_algorithms_allowlist() {
		$algos = TokenManager::ALGORITHMS;

		$this->assertContains( 'HS256', $algos );
		$this->assertContains( 'RS256', $algos );
		$this->assertContains( 'ES256', $algos );
		$this->assertContains( 'ES384', $algos );

		// Unsupported by firebase/php-jwt 7.x (or requiring optional deps).
		$this->assertNotContains( 'ES512', $algos );
		$this->assertNotContains( 'PS256', $algos );
		$this->assertNotContains( 'PS384', $algos );
		$this->assertNotContains( 'PS512', $algos );
	}

	/**
	 * Symmetric detection.
	 */
	public function test_is_symmetric() {
		$this->assertTrue( TokenManager::is_symmetric( 'HS256' ) );
		$this->assertTrue( TokenManager::is_symmetric( 'HS512' ) );
		$this->assertFalse( TokenManager::is_symmetric( 'RS256' ) );
		$this->assertFalse( TokenManager::is_symmetric( 'ES256' ) );
	}

	/**
	 * Refresh tokens are 32 random bytes, base64url-encoded (43 chars).
	 */
	public function test_generate_refresh_token() {
		$token = TokenManager::generate_refresh_token();

		$this->assertIsString( $token );
		$this->assertSame( 43, strlen( $token ) );
		$this->assertNotSame( $token, TokenManager::generate_refresh_token() );
	}

	/**
	 * Refresh-token hashing is a deterministic 64-char hex digest.
	 */
	public function test_hash_refresh_token() {
		$hash = TokenManager::hash_refresh_token( 'abc' );

		$this->assertSame( 64, strlen( $hash ) );
		$this->assertSame( $hash, TokenManager::hash_refresh_token( 'abc' ) );
	}

	/**
	 * Family ids are 32-char hex.
	 */
	public function test_generate_family_id() {
		$id = TokenManager::generate_family_id();

		$this->assertSame( 32, strlen( $id ) );
		$this->assertNotSame( $id, TokenManager::generate_family_id() );
	}

	/**
	 * Access/refresh TTL fall back to safe defaults when unset.
	 */
	public function test_ttl_defaults() {
		$this->assertSame( 900, TokenManager::get_access_ttl() );
		$this->assertSame( 1209600, TokenManager::get_refresh_ttl() );
	}
}
