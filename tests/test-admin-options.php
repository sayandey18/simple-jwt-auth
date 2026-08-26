<?php
/**
 * Integration tests for the options admin callback.
 *
 * @package Simple_Jwt_Auth
 */

use Simple_Jwt_Auth\Database\DBManager;
use Simple_Jwt_Auth\Notice\JWTNotice;

/**
 * @covers \Simple_Jwt_Auth_Admin::simplejwt_options_callback
 */
class Test_Admin_Options extends WP_UnitTestCase {

	/**
	 * An administrator user id.
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Set up the schema, config and an administrator user.
	 */
	public function setUp(): void {
		parent::setUp();

		Simple_Jwt_Auth_Activator::activate();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	/**
	 * Build a partial mock of the admin class that captures the redirect
	 * instead of exiting.
	 *
	 * @return Simple_Jwt_Auth_Admin
	 */
	private function get_admin_mock() {
		return $this->getMockBuilder( Simple_Jwt_Auth_Admin::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'simplejwt_admin_redirect' ) )
			->getMock();
	}

	/**
	 * Toggling XML-RPC while "Remove configs data" is left at its default
	 * (enabled) must not report an error.
	 */
	public function test_xmlrpc_toggle_with_unchanged_drop_configs_succeeds() {
		update_option( 'simplejwt_drop_configs', true );

		$_POST['simplejwt_nonce']          = wp_create_nonce( 'simplejwt_nonce' );
		$_POST['simplejwt_drop_configs']   = 'on';
		$_POST['simplejwt_disable_xmlrpc'] = 'on';

		$admin = $this->get_admin_mock();
		$admin->expects( $this->once() )
			->method( 'simplejwt_admin_redirect' )
			->with( true, JWTNotice::get_notice( 'success' ), 'options' );

		$admin->simplejwt_options_callback();

		$this->assertSame( '1', DBManager::get_config( 'disable_xmlrpc' ) );
	}

	/**
	 * Unchecking "Remove configs data" persists the change and succeeds.
	 */
	public function test_drop_configs_toggle_updates_option() {
		update_option( 'simplejwt_drop_configs', true );

		$_POST['simplejwt_nonce']          = wp_create_nonce( 'simplejwt_nonce' );
		$_POST['simplejwt_disable_xmlrpc'] = 'on';
		// `simplejwt_drop_configs` is absent from the payload when unchecked.

		$admin = $this->get_admin_mock();
		$admin->expects( $this->once() )
			->method( 'simplejwt_admin_redirect' )
			->with( true, JWTNotice::get_notice( 'success' ), 'options' );

		$admin->simplejwt_options_callback();

		// `update_option` stores a boolean `false` as an empty string.
		$this->assertEmpty( get_option( 'simplejwt_drop_configs' ) );
		$this->assertSame( '1', DBManager::get_config( 'disable_xmlrpc' ) );
	}
}
