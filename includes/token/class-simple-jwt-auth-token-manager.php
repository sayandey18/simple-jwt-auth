<?php

/* The namespace to avoid class name collisions. */
namespace Simple_Jwt_Auth\Token;

/* Wrapper namespace to prevent conflicts with other plugins bundling the library. */
use Simple_Jwt_Auth\Firebase\JWT\JWT;
use Simple_Jwt_Auth\Firebase\JWT\Key;
use Simple_Jwt_Auth\Database\DBManager;
use Simple_Jwt_Auth\OpenSSL\Crypto;
use Simple_Jwt_Auth\Notice\JWTNotice;

/**
 * Central authority for access/refresh token issuance and verification.
 *
 * This class is the single source of truth for the supported algorithm list,
 * key selection (symmetric vs. asymmetric), access-token signing/verification,
 * and refresh-token material generation. It uses the wrapper `Simple_Jwt_Auth\Firebase\JWT`
 * namespace to avoid collisions with other plugins bundling firebase/php-jwt.
 *
 * @link       https://github.com/sayandey18/simple-jwt-auth
 * @since      2.0.0
 *
 * @package    Simple_Jwt_Auth
 * @subpackage Simple_Jwt_Auth\Token
 * @author     Sayan Dey <mr.sayandey18@outlook.com>
 */
class TokenManager {

	/**
	 * Algorithms the plugin can sign/verify with firebase/php-jwt + OpenSSL only.
	 *
	 * NOTE: ES512, PS256, PS384 and PS512 are intentionally absent — ES512/PS384/
	 * PS512 are not implemented by firebase/php-jwt (as of 7.x), and PS256 needs
	 * the optional phpseclib dependency. Listing them previously caused a runtime
	 * error. Adding any of these requires a corresponding dependency bump.
	 *
	 * @var array<int,string>
	 */
	public const ALGORITHMS = array(
		'HS256',
		'HS384',
		'HS512',
		'RS256',
		'RS384',
		'RS512',
		'ES256',
		'ES384',
	);

	/**
	 * Symmetric (shared-secret) algorithms.
	 *
	 * @var array<int,string>
	 */
	public const SYMMETRIC = array( 'HS256', 'HS384', 'HS512' );

	/**
	 * Default access-token lifetime, in seconds (15 minutes).
	 */
	public const DEFAULT_ACCESS_TTL = 900;

	/**
	 * Default refresh-token lifetime, in seconds (14 days).
	 */
	public const DEFAULT_REFRESH_TTL = 1209600;

	/**
	 * Refresh-token rotation grace window, in seconds.
	 */
	public const GRACE_WINDOW = 5;

	/**
	 * wp-config.php constant overrides for each signing key.
	 *
	 * @var array<string,string>
	 */
	public const KEY_CONSTANTS = array(
		'secret_key'  => 'SIMPLE_JWT_AUTH_SECRET_KEY',
		'private_key' => 'SIMPLE_JWT_AUTH_PRIVATE_KEY',
		'public_key'  => 'SIMPLE_JWT_AUTH_PUBLIC_KEY',
	);

	/**
	 * Retrieve the configured, validated signing algorithm.
	 *
	 * @since 2.0.0
	 * @return string|false The algorithm, or false if unset/unsupported.
	 */
	public static function get_algorithm() {
		if ( defined( 'SIMPLE_JWT_AUTH_ALGORITHM' ) && in_array( SIMPLE_JWT_AUTH_ALGORITHM, self::ALGORITHMS, true ) ) {
			return SIMPLE_JWT_AUTH_ALGORITHM;
		}

		$algorithm = DBManager::get_config( 'algorithm' );

		if ( empty( $algorithm ) || ! in_array( $algorithm, self::ALGORITHMS, true ) ) {
			return false;
		}

		return $algorithm;
	}

	/**
	 * Retrieve the configured access-token lifetime.
	 *
	 * @since 2.0.0
	 * @return int Lifetime in seconds.
	 */
	public static function get_access_ttl() {
		$ttl = DBManager::get_config( 'token_expiration' );

		return ( false !== $ttl && is_numeric( $ttl ) && (int) $ttl > 0 )
			? (int) $ttl
			: self::DEFAULT_ACCESS_TTL;
	}

	/**
	 * Retrieve the configured refresh-token lifetime.
	 *
	 * @since 2.0.0
	 * @return int Lifetime in seconds.
	 */
	public static function get_refresh_ttl() {
		$ttl = DBManager::get_config( 'refresh_expiration' );

		return ( false !== $ttl && is_numeric( $ttl ) && (int) $ttl > 0 )
			? (int) $ttl
			: self::DEFAULT_REFRESH_TTL;
	}

	/**
	 * Determine whether the given algorithm is symmetric (HS*).
	 *
	 * @since 2.0.0
	 * @param string $algorithm The JWS algorithm.
	 * @return bool
	 */
	public static function is_symmetric( string $algorithm ) {
		return in_array( $algorithm, self::SYMMETRIC, true );
	}

	/**
	 * Issue a signed access token for the given user.
	 *
	 * @since 2.0.0
	 * @param \WP_User $user The authenticated user.
	 * @return string|\WP_Error The encoded JWT, or an error.
	 */
	public static function issue_access_token( \WP_User $user ) {
		$algorithm = self::get_algorithm();

		if ( ! $algorithm ) {
			return new \WP_Error(
				'simplejwt_unsupported_algorithm',
				JWTNotice::get_notice( 'unsupported_algo' ),
				array( 'status' => 403 )
			);
		}

		$key = self::get_signing_key( $algorithm, 'sign' );

		if ( is_wp_error( $key ) ) {
			return $key;
		}

		$issued_at  = time();
		$not_before = apply_filters( 'simplejwt_not_before', $issued_at, $issued_at );
		$expire     = apply_filters( 'simplejwt_auth_expire', $issued_at + self::get_access_ttl(), $issued_at );

		$payload = array(
			'iss'  => self::get_iss(),
			'iat'  => $issued_at,
			'nbf'  => $not_before,
			'exp'  => $expire,
			'sub'  => (string) $user->ID,
			'jti'  => self::generate_jti(),
			// Legacy claim, kept for backward compatibility during 2.x.
			'data' => array(
				'user' => array(
					'id' => $user->ID,
				),
			),
		);

		$payload = apply_filters( 'simplejwt_payload_before_sign', $payload, $user );

		try {
			return JWT::encode( $payload, $key, $algorithm );
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'simplejwt_token_creation_error',
				JWTNotice::get_notice( 'unknown_error' ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Verify and decode an access token.
	 *
	 * @since 2.0.0
	 * @param string $token The raw JWT.
	 * @return int|\WP_Error The resolved user id, or an error.
	 */
	public static function verify_access_token( string $token ) {
		$algorithm = self::get_algorithm();

		if ( ! $algorithm ) {
			return new \WP_Error(
				'simplejwt_unsupported_algorithm',
				JWTNotice::get_notice( 'unsupported_algo' ),
				array( 'status' => 403 )
			);
		}

		$key = self::get_signing_key( $algorithm, 'verify' );

		if ( is_wp_error( $key ) ) {
			return $key;
		}

		try {
			$decoded = JWT::decode( $token, new Key( $key, $algorithm ) );
		} catch ( \Simple_Jwt_Auth\Firebase\JWT\ExpiredException $e ) {
			return new \WP_Error(
				'simplejwt_expired_token',
				JWTNotice::get_notice( 'expired_token' ),
				array( 'status' => 403 )
			);
		} catch ( \Simple_Jwt_Auth\Firebase\JWT\SignatureInvalidException $e ) {
			return new \WP_Error(
				'simplejwt_invalid_token',
				JWTNotice::get_notice( 'invalid_token' ),
				array( 'status' => 403 )
			);
		} catch ( \Simple_Jwt_Auth\Firebase\JWT\BeforeValidException $e ) {
			return new \WP_Error(
				'simplejwt_invalid_token',
				JWTNotice::get_notice( 'invalid_token' ),
				array( 'status' => 403 )
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'simplejwt_invalid_token',
				JWTNotice::get_notice( 'invalid_token' ),
				array( 'status' => 403 )
			);
		}

		if ( ! isset( $decoded->iss ) || $decoded->iss !== self::get_iss() ) {
			return new \WP_Error(
				'simplejwt_bad_issuer',
				JWTNotice::get_notice( 'bad_issuer' ),
				array( 'status' => 403 )
			);
		}

		// Prefer the standard `sub` claim, fall back to the legacy `data.user.id`.
		$user_id = isset( $decoded->sub ) ? (int) $decoded->sub : 0;

		if ( ! $user_id && isset( $decoded->data->user->id ) ) {
			$user_id = (int) $decoded->data->user->id;
		}

		if ( ! $user_id ) {
			return new \WP_Error(
				'simplejwt_bad_request',
				JWTNotice::get_notice( 'bad_request' ),
				array( 'status' => 403 )
			);
		}

		return $user_id;
	}

	/**
	 * Resolve and decrypt the signing key for the given algorithm/purpose.
	 *
	 * @since 2.0.0
	 * @param string $algorithm The JWS algorithm.
	 * @param string $purpose   'sign' (private/secret) or 'verify' (public/secret).
	 * @return string|\WP_Error The decrypted key material, or an error.
	 */
	public static function get_signing_key( string $algorithm, string $purpose ) {
		if ( self::is_symmetric( $algorithm ) ) {
			$key_name = 'secret_key';
		} else {
			$key_name = ( 'sign' === $purpose ) ? 'private_key' : 'public_key';
		}

		$const_name = self::KEY_CONSTANTS[ $key_name ];

		if ( defined( $const_name ) && is_string( constant( $const_name ) ) && '' !== constant( $const_name ) ) {
			return constant( $const_name );
		}

		$stored = DBManager::get_config( $key_name );

		if ( false === $stored || '' === $stored ) {
			return new \WP_Error(
				'simplejwt_bad_' . $key_name,
				JWTNotice::get_notice( 'bad_' . $key_name ),
				array( 'status' => 403 )
			);
		}

		$decrypted = Crypto::decrypt( $stored );

		if ( is_wp_error( $decrypted ) ) {
			return $decrypted;
		}

		return $decrypted;
	}

	/**
	 * Generate a new opaque refresh token (32 random bytes, base64url).
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public static function generate_refresh_token() {
		return self::base64url_encode( random_bytes( 32 ) );
	}

	/**
	 * Hash a refresh token for storage (SHA-256, hex).
	 *
	 * @since 2.0.0
	 * @param string $token The raw refresh token.
	 * @return string
	 */
	public static function hash_refresh_token( string $token ) {
		return hash( 'sha256', $token );
	}

	/**
	 * Generate a new token-family identifier (16 random bytes, hex).
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public static function generate_family_id() {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Generate a random JWT id (jti).
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public static function generate_jti() {
		return self::base64url_encode( random_bytes( 16 ) );
	}

	/**
	 * Resolve the token issuer (iss).
	 *
	 * Kept as `get_bloginfo( 'url' )` for backward compatibility with 1.0.x
	 * tokens; filterable via `simplejwt_auth_iss`.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public static function get_iss() {
		return apply_filters( 'simplejwt_auth_iss', get_bloginfo( 'url' ) );
	}

	/**
	 * URL-safe base64 encoding without padding.
	 *
	 * @since 2.0.0
	 * @param string $data Raw bytes.
	 * @return string
	 */
	private static function base64url_encode( string $data ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64url encodes random bytes into URL-safe token material, not obfuscation.
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
