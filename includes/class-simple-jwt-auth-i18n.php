<?php

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 *
 * @package    Simple_Jwt_Auth
 * @subpackage Simple_Jwt_Auth/includes
 * @author     Sayan Dey <mr.sayandey18@outlook.com>
 */
class Simple_Jwt_Auth_i18n {
	/**
	 * The domain specified for this plugin.
	 *
	 * @since   1.0.0
	 */
	private string $domain;

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since   1.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			$this->domain,
			false,
			dirname( plugin_basename( __FILE__ ), 2 ) . '/languages/'
		);
	}

	/**
	 * Set the domain equal to that of the specified domain.
	 * The domain that represents the locale of this plugin.
	 *
	 * @since   1.0.0
	 * @param string $domain The domain that represents the locale of this plugin.
	 */
	public function set_domain( string $domain ) {
		$this->domain = $domain;
	}
}
