<?php
/**
 * REST API integration tests.
 *
 * @package Simple_Jwt_Auth
 */

use Simple_Jwt_Auth\OpenSSL\Crypto;
use Simple_Jwt_Auth\Database\DBManager;
use Simple_Jwt_Auth\Database\RefreshStore;

/**
 * @group rest
 */
class Test_Rest_Api extends WP_UnitTestCase {

	/**
	 * The test user id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * The test user password.
	 */
	private const PASSWORD = 'test-password-123';

	/**
	 * Set up schema, config, routes and a test user.
	 */
	public function setUp(): void {
		parent::setUp();

		Simple_Jwt_Auth_Activator::activate();

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM {$wpdb->prefix}simplejwt_refresh_tokens" );

		// Enable auth and configure an HS256 secret key.
		DBManager::save_config(
			array(
				'enable_auth' => '1',
				'algorithm'   => 'HS256',
				'secret_key'  => Crypto::encrypt( '0123456789abcdef0123456789abcdef' ),
			)
		);

		// Register the REST routes.
		do_action( 'rest_api_init' );

		$this->user_id = wp_insert_user(
			array(
				'user_login' => 'jwt_test_user',
				'user_pass'  => self::PASSWORD,
				'user_email' => 'jwt-test@example.com',
			)
		);
	}

	/**
	 * Authenticated request helper (sets the Authorization header).
	 */
	private function request( string $method, string $route, array $body = array(), string $token = '' ) {
		if ( $token ) {
			$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
		} else {
			unset( $_SERVER['HTTP_AUTHORIZATION'] );
		}

		// Reset the cached current user so `determine_current_user` re-runs.
		wp_set_current_user( 0 );
		$GLOBALS['current_user'] = null;

		$request = new WP_REST_Request( $method, '/auth/v1' . $route );

		if ( ! empty( $body ) ) {
			$request->set_body_params( $body );
		}

		return rest_do_request( $request );
	}

	/**
	 * Valid credentials return an access + refresh token.
	 */
	public function test_generate_token_success() {
		$response = $this->request(
			'POST',
			'/token',
			array(
				'username' => 'jwt_test_user',
				'password' => self::PASSWORD,
			)
		);

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'simplejwt_auth_credential', $data['code'] );
		$this->assertNotEmpty( $data['data']['token'] );
		$this->assertNotEmpty( $data['data']['refresh_token'] );
		$this->assertSame( 900, $data['data']['token_expires_in'] );
		$this->assertSame( 1209600, $data['data']['refresh_expires_in'] );
	}

	/**
	 * Wrong credentials return a 403.
	 */
	public function test_generate_token_wrong_password() {
		$response = $this->request(
			'POST',
			'/token',
			array(
				'username' => 'jwt_test_user',
				'password' => 'wrong-password',
			)
		);

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * A valid access token validates successfully.
	 */
	public function test_validate_token() {
		$token = $this->issue_token();

		$response = $this->request( 'POST', '/token/validate', array(), $token );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'simplejwt_valid_token', $response->get_data()['code'] );
	}

	/**
	 * A valid refresh token yields a rotated pair.
	 */
	public function test_refresh_flow() {
		$refresh = $this->issue_token_and_get_refresh();

		$response = $this->request(
			'POST',
			'/token/refresh',
			array(
				'refresh_token' => $refresh,
			)
		);

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertNotEmpty( $data['data']['token'] );
		$this->assertNotEmpty( $data['data']['refresh_token'] );
		$this->assertNotSame( $refresh, $data['data']['refresh_token'] );
	}

	/**
	 * Revoking a refresh token prevents further refresh.
	 */
	public function test_revoke_flow() {
		$refresh = $this->issue_token_and_get_refresh();

		$revoke = $this->request(
			'POST',
			'/token/revoke',
			array(
				'refresh_token' => $refresh,
			)
		);

		$this->assertSame( 200, $revoke->get_status() );

		$refresh_again = $this->request(
			'POST',
			'/token/refresh',
			array(
				'refresh_token' => $refresh,
			)
		);

		$this->assertSame( 403, $refresh_again->get_status() );
	}

	/**
	 * The /me endpoint returns the authenticated user.
	 */
	public function test_me_endpoint() {
		$token    = $this->issue_token();
		$response = $this->request( 'GET', '/me', array(), $token );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( (string) $this->user_id, (string) $data['data']['id'] );
	}

	/**
	 * Disabled auth rejects token generation.
	 */
	public function test_disabled_auth_returns_bad_config() {
		DBManager::save_config( array( 'enable_auth' => '0' ) );

		$response = $this->request(
			'POST',
			'/token',
			array(
				'username' => 'jwt_test_user',
				'password' => self::PASSWORD,
			)
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'simplejwt_bad_config', $response->get_data()['code'] );
	}

	/**
	 * Helper: issue a token and return the access token.
	 */
	private function issue_token() {
		$response = $this->request(
			'POST',
			'/token',
			array(
				'username' => 'jwt_test_user',
				'password' => self::PASSWORD,
			)
		);

		return $response->get_data()['data']['token'];
	}

	/**
	 * Helper: issue a token and return the refresh token.
	 */
	private function issue_token_and_get_refresh() {
		$response = $this->request(
			'POST',
			'/token',
			array(
				'username' => 'jwt_test_user',
				'password' => self::PASSWORD,
			)
		);

		return $response->get_data()['data']['refresh_token'];
	}
}
