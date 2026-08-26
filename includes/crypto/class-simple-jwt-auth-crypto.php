<?php

/* The namespace to avoid class name collisions. */
namespace Simple_Jwt_Auth\OpenSSL;

/* Used the required class and library. */
use Simple_Jwt_Auth\Notice\JWTNotice;
use WP_Error;

/**
 * Handles encryption and decryption of data using AES-256-GCM.
 *
 * This class depends on the availability of an encryption key, which should be defined
 * in the system as the `SIMPLE_JWT_AUTH_ENCRYPT_KEY` constant. If the key is not defined,
 * the class will return an error when attempting to encrypt or decrypt data.
 *
 * Ciphertext is versioned with a `v1:` prefix so future key-rotation schemes can be
 * introduced without breaking existing rows. Rotating `SIMPLE_JWT_AUTH_ENCRYPT_KEY`
 * invalidates all previously stored ciphertext (AES-GCM authentication will fail) and
 * is reported distinctly as `simplejwt_kek_mismatch`; the signing keys must then be
 * re-entered via the plugin settings.
 *
 * @link       https://github.com/sayandey18/simple-jwt-auth
 * @since      1.0.0
 *
 * @package    Simple_Jwt_Auth
 * @subpackage Simple_Jwt_Auth\OpenSSL
 * @author     Sayan Dey <mr.sayandey18@outlook.com>
 */
class Crypto {
	/**
	 * Ciphertext format version.
	 */
	private const VERSION = 'v1:';

	public function __construct() {
		// Constructor code if needed
	}

	/**
	 * Encrypts the provided data using the AES-256-GCM algorithm.
	 *
	 * @since 1.0.0
	 *
	 * @param string $decrypted
	 * @return string|WP_Error
	 */
	public static function encrypt( string $decrypted ) {
		$secret = self::get_kek();

		if ( is_wp_error( $secret ) ) {
			return $secret;
		}

		$cipher    = 'aes-256-gcm';
		$iv_length = openssl_cipher_iv_length( $cipher );
		$iv_key    = random_bytes( $iv_length ); // Generate a secure IV.
		$tag       = ''; // Will be filled after encryption.
		$option    = 0;

		// Encrypt the data.
		$encrypted = openssl_encrypt( $decrypted, $cipher, $secret, $option, $iv_key, $tag );

		if ( false === $encrypted ) {
			return new WP_Error(
				'simplejwt_encryption_failed',
				JWTNotice::get_notice( 'encryption_failed' ),
				array( 'status' => 500 )
			);
		}

		// Return the versioned ciphertext: version + base64(iv || tag || ciphertext).
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64 is used to store raw binary ciphertext, not for obfuscation.
		return self::VERSION . base64_encode( $iv_key . $tag . $encrypted );
	}

	/**
	 * Decrypts the provided encrypted data using the AES-256-GCM algorithm.
	 *
	 * Accepts both versioned (`v1:`) and legacy (unversioned) ciphertext for
	 * backward compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param string $encrypted
	 * @return string|WP_Error
	 */
	public static function decrypt( string $encrypted ) {
		$secret = self::get_kek();

		if ( is_wp_error( $secret ) ) {
			return $secret;
		}

		// Strip the version prefix, if present.
		if ( 0 === strpos( $encrypted, self::VERSION ) ) {
			$encrypted = substr( $encrypted, strlen( self::VERSION ) );
		}

		$cipher    = 'aes-256-gcm';
		$iv_length = openssl_cipher_iv_length( $cipher );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Base64 is used to decode raw binary ciphertext, not for obfuscation.
		$raw    = base64_decode( $encrypted, true );
		$option = 0;

		// Malformed ciphertext: not valid base64 or too short to contain iv+tag.
		if ( false === $raw || strlen( $raw ) <= ( $iv_length + 16 ) ) {
			return new WP_Error(
				'simplejwt_decryption_failed',
				JWTNotice::get_notice( 'decryption_failed' ),
				array( 'status' => 500 )
			);
		}

		// Extract the IV, tag, and ciphertext from the encrypted data.
		$iv_key     = substr( $raw, 0, $iv_length );
		$tag        = substr( $raw, $iv_length, 16 ); // GCM tag is always 16 bytes.
		$ciphertext = substr( $raw, $iv_length + 16 );

		// Decrypt the data.
		$decrypted = openssl_decrypt( $ciphertext, $cipher, $secret, $option, $iv_key, $tag );

		if ( false === $decrypted ) {
			// Well-formed ciphertext failing GCM authentication is almost always a
			// key-encryption-key (KEK) mismatch after rotating the constant.
			return new WP_Error(
				'simplejwt_kek_mismatch',
				JWTNotice::get_notice( 'kek_mismatch' ),
				array( 'status' => 500 )
			);
		}

		// Return the decrypted data.
		return $decrypted;
	}

	/**
	 * Resolve and validate the key-encryption-key (KEK).
	 *
	 * @since 2.0.0
	 * @return string|WP_Error
	 */
	private static function get_kek() {
		$secret = defined( 'SIMPLE_JWT_AUTH_ENCRYPT_KEY' ) ? SIMPLE_JWT_AUTH_ENCRYPT_KEY : false;

		// Check the encryption key, if not exists return an error.
		if ( ! $secret ) {
			return new WP_Error(
				'simplejwt_bad_encryption_key',
				JWTNotice::get_notice( 'bad_encryption_key' ),
				array( 'status' => 403 )
			);
		}

		// Check if the key length is exactly 32 bytes.
		if ( 32 !== strlen( $secret ) ) {
			return new WP_Error(
				'simplejwt_invalid_enckey_length',
				JWTNotice::get_notice( 'invalid_enckey_length' ),
				array( 'status' => 400 )
			);
		}

		return $secret;
	}
}
