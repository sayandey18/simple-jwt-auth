<?php
/**
 * Unit tests for the AES-256-GCM crypto helper.
 *
 * @package Simple_Jwt_Auth
 */

use Simple_Jwt_Auth\OpenSSL\Crypto;

/**
 * @coversDefaultClass \Simple_Jwt_Auth\OpenSSL\Crypto
 */
class Test_Crypto extends WP_UnitTestCase {

	/**
	 * Encrypt/decrypt round-trips the original value.
	 */
	public function test_round_trip() {
		$plain = 'my-super-secret-signing-key';

		$encrypted = Crypto::encrypt( $plain );

		$this->assertIsString( $encrypted );
		$this->assertStringStartsWith( 'v1:', $encrypted );

		$this->assertSame( $plain, Crypto::decrypt( $encrypted ) );
	}

	/**
	 * Decrypting ciphertext produced with a different KEK reports a KEK mismatch.
	 */
	public function test_kek_mismatch_is_detected() {
		$other_key = 'fedcba9876543210fedcba9876543210';
		$cipher    = 'aes-256-gcm';
		$iv_length = openssl_cipher_iv_length( $cipher );
		$iv        = random_bytes( $iv_length );
		$tag       = '';

		$ciphertext = openssl_encrypt( 'hello', $cipher, $other_key, 0, $iv, $tag );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Test helper building raw ciphertext, not obfuscation.
		$result = Crypto::decrypt( 'v1:' . base64_encode( $iv . $tag . $ciphertext ) );

		$this->assertWPError( $result );
		$this->assertSame( 'simplejwt_kek_mismatch', $result->get_error_code() );
	}

	/**
	 * Decrypting garbage returns a decryption failure (not a KEK mismatch).
	 */
	public function test_malformed_input_returns_decryption_failed() {
		$result = Crypto::decrypt( 'v1:not-base64!!' );

		$this->assertWPError( $result );
		$this->assertSame( 'simplejwt_decryption_failed', $result->get_error_code() );
	}

	/**
	 * Two encryptions of the same value produce different ciphertext (random IV).
	 */
	public function test_encryption_is_non_deterministic() {
		$plain = 'value';

		$this->assertNotSame( Crypto::encrypt( $plain ), Crypto::encrypt( $plain ) );
	}
}
