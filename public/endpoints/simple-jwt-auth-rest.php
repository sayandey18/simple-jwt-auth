<?php

/* Require the token, database and notice libraries. */
use Simple_Jwt_Auth\Database\DBManager;
use Simple_Jwt_Auth\Database\RefreshStore;
use Simple_Jwt_Auth\Token\TokenManager;
use Simple_Jwt_Auth\Notice\JWTNotice;

/**
 * The public-facing functionality of the plugin.
 * This class will extend the Simple_Jwt_Auth_Public class for JWT auth.
 *
 * @link       https://github.com/sayandey18/simple-jwt-auth
 * @since      1.0.0
 *
 * @package    Simple_Jwt_Auth
 * @subpackage Simple_Jwt_Auth/public/endpoints
 * @author     Sayan Dey <mr.sayandey18@outlook.com>
 */

class Simple_Jwt_Auth_Api extends Simple_Jwt_Auth_Public {
	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private string $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private string $version;

	/**
	 * The endpoint of this plugin API.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $endpoint The JWT endpoint of this plugin.
	 */
	private string $endpoint;

	/**
	 * Store errors to display if the JWT is wrong.
	 *
	 * @since   1.0.0
	 * @var     WP_Error|null
	 */
	private ?WP_Error $jwt_error = null;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since   1.0.0
	 * @param   string $plugin_name
	 * @param   string $version
	 * @param   string $endpoint
	 */
	public function __construct( string $plugin_name, string $version, string $endpoint ) {
		parent::__construct( $plugin_name, $version );

		$this->endpoint = $endpoint . '/v' . intval( SIMPLE_JWT_AUTH_API_VERSION );
	}

	/**
	 * Add the endpoints to the API
	 *
	 * @since 1.0.0
	 */
	public function simplejwt_add_api_routes() {
		register_rest_route(
			$this->endpoint,
			'token',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'simplejwt_generate_token' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->endpoint,
			'token/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'simplejwt_refresh_token' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->endpoint,
			'token/revoke',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'simplejwt_revoke_token' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->endpoint,
			'token/validate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'simplejwt_validate_token' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->endpoint,
			'me',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'simplejwt_me' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Add CORS support to the request.
	 *
	 * @since 1.0.0
	 */
	public function simplejwt_add_cors_support() {
		// Check the CORS status from database.
		$enable_cors = filter_var(
			DBManager::get_config( 'enable_cors' ),
			FILTER_VALIDATE_BOOLEAN
		);

		if ( $enable_cors ) {
			$headers = apply_filters(
				'simplejwt_cors_allow_headers',
				'Access-Control-Allow-Headers, Content-Type, Authorization'
			);
			header( sprintf( 'Access-Control-Allow-Headers: %s', $headers ) );
		}
	}

	/**
	 * Get the user and password in the request body and generate a JWT token
	 * for further authentication.
	 *
	 * @param WP_REST_Request $request
	 * @return mixed|WP_Error|null
	 */
	public function simplejwt_generate_token( WP_REST_Request $request ) {
		if ( ! $this->simplejwt_is_auth_enabled() ) {
			return new WP_Error(
				'simplejwt_bad_config',
				JWTNotice::get_notice( 'auth_disabled' ),
				array( 'status' => 403 )
			);
		}

		// Get the username and password from REST request.
		$username = $request->get_param( 'username' );
		$password = $request->get_param( 'password' );

		// Check if username or password is missing and return an error.
		if ( empty( $username ) || empty( $password ) ) {
			return new WP_Error(
				'simplejwt_missing_credentials',
				JWTNotice::get_notice( 'missing_credential' ),
				array( 'status' => 400 )
			);
		}

		// Basic rate limiting against brute force.
		if ( ! $this->simplejwt_rate_limit( 'login_' . $username . '_' . $this->simplejwt_client_ip() ) ) {
			return new WP_Error(
				'simplejwt_rate_limited',
				JWTNotice::get_notice( 'rate_limited' ),
				array( 'status' => 429 )
			);
		}

		// Get defined algorithm from the database.
		$algorithm = TokenManager::get_algorithm();

		// Check algorithm if not exist return an error.
		if ( ! $algorithm ) {
			return new WP_Error(
				'simplejwt_unsupported_algorithm',
				JWTNotice::get_notice( 'unsupported_algo' ),
				array( 'status' => 403 )
			);
		}

		// Verify the signing key is configured before authenticating.
		$signing_key = TokenManager::get_signing_key( $algorithm, 'sign' );

		if ( is_wp_error( $signing_key ) ) {
			return $signing_key;
		}

		// Authenticate the user with the password cred.
		$user = wp_authenticate( $username, $password );

		// If the authentication fails return an error.
		if ( is_wp_error( $user ) ) {
			$error_code    = $user->get_error_code();
			$error_message = $user->get_error_message();

			return new WP_Error(
				'simplejwt_' . $error_code,
				wp_strip_all_tags( $error_message ),
				array( 'status' => 403 )
			);
		}

		// Issue the access token.
		$access_token = TokenManager::issue_access_token( $user );

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		// Issue and persist the refresh token.
		$refresh_token = $this->simplejwt_issue_refresh_token( $user->ID );

		if ( is_wp_error( $refresh_token ) ) {
			return $refresh_token;
		}

		return $this->simplejwt_token_response( $user, $access_token, $refresh_token );
	}

	/**
	 * Refresh an access token (with rotation) using a refresh token.
	 *
	 * @since 2.0.0
	 * @param WP_REST_Request $request
	 * @return mixed|WP_Error|null
	 */
	public function simplejwt_refresh_token( WP_REST_Request $request ) {
		if ( ! $this->simplejwt_is_auth_enabled() ) {
			return new WP_Error(
				'simplejwt_bad_config',
				JWTNotice::get_notice( 'auth_disabled' ),
				array( 'status' => 403 )
			);
		}

		$refresh = $request->get_param( 'refresh_token' );

		if ( ! $refresh ) {
			$refresh = $this->simplejwt_extract_bearer( $this->simplejwt_get_auth_header() );
		}

		if ( ! $refresh ) {
			return new WP_Error(
				'simplejwt_invalid_refresh_token',
				JWTNotice::get_notice( 'invalid_refresh_token' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $this->simplejwt_rate_limit( 'refresh_' . TokenManager::hash_refresh_token( $refresh ) ) ) {
			return new WP_Error(
				'simplejwt_rate_limited',
				JWTNotice::get_notice( 'rate_limited' ),
				array( 'status' => 429 )
			);
		}

		return $this->simplejwt_perform_refresh( $refresh );
	}

	/**
	 * Revoke a refresh token (and its rotation family).
	 *
	 * @since 2.0.0
	 * @param WP_REST_Request $request
	 * @return mixed|WP_Error|null
	 */
	public function simplejwt_revoke_token( WP_REST_Request $request ) {
		$refresh = $request->get_param( 'refresh_token' );

		if ( ! $refresh ) {
			$refresh = $this->simplejwt_extract_bearer( $this->simplejwt_get_auth_header() );
		}

		if ( ! $refresh ) {
			return new WP_Error(
				'simplejwt_invalid_refresh_token',
				JWTNotice::get_notice( 'invalid_refresh_token' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $this->simplejwt_rate_limit( 'revoke_' . TokenManager::hash_refresh_token( $refresh ) ) ) {
			return new WP_Error(
				'simplejwt_rate_limited',
				JWTNotice::get_notice( 'rate_limited' ),
				array( 'status' => 429 )
			);
		}

		$row = RefreshStore::find_by_hash( TokenManager::hash_refresh_token( $refresh ) );

		if ( ! $row ) {
			return new WP_Error(
				'simplejwt_invalid_refresh_token',
				JWTNotice::get_notice( 'invalid_refresh_token' ),
				array( 'status' => 403 )
			);
		}

		RefreshStore::revoke_family( $row['family_id'] );

		return new WP_REST_Response(
			array(
				'code'    => 'simplejwt_token_revoked',
				'message' => JWTNotice::get_notice( 'revoked_token' ),
				'data'    => array( 'status' => 200 ),
			),
			200
		);
	}

	/**
	 * Validate an access token.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return object|WP_Error|array
	 */
	public function simplejwt_validate_token( WP_REST_Request $request ) {
		$auth_header = $this->simplejwt_get_auth_header();

		// If Authorization header not exist return an error.
		if ( ! $auth_header ) {
			return new WP_Error(
				'simplejwt_no_auth_header',
				JWTNotice::get_notice( 'no_auth_header' ),
				array( 'status' => 403 )
			);
		}

		$token = $this->simplejwt_extract_bearer( $auth_header );

		// If the format is not valid return an error.
		if ( ! $token ) {
			return new WP_Error(
				'simplejwt_bad_auth_header',
				JWTNotice::get_notice( 'bad_auth_header' ),
				array( 'status' => 400 )
			);
		}

		$user_id = TokenManager::verify_access_token( $token );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Return successful response to `token/validate` endpoint.
		return new WP_REST_Response(
			array(
				'code'    => 'simplejwt_valid_token',
				'message' => JWTNotice::get_notice( 'valid_token' ),
				'data'    => array( 'status' => 200 ),
			),
			200
		);
	}

	/**
	 * Return the currently authenticated user's profile.
	 *
	 * @since 2.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function simplejwt_me( WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return new WP_Error(
				'simplejwt_no_auth_header',
				JWTNotice::get_notice( 'no_auth_header' ),
				array( 'status' => 403 )
			);
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return new WP_Error(
				'simplejwt_bad_request',
				JWTNotice::get_notice( 'bad_request' ),
				array( 'status' => 403 )
			);
		}

		return new WP_REST_Response(
			array(
				'code'    => 'simplejwt_user',
				'message' => __( 'User data retrieved successfully', 'simple-jwt-auth' ),
				'data'    => array(
					'status'       => 200,
					'id'           => $user->ID,
					'email'        => $user->user_email,
					'nicename'     => $user->user_nicename,
					'display_name' => $user->display_name,
					'roles'        => array_values( $user->roles ),
				),
			),
			200
		);
	}

	/**
	 * This Middleware to try to authenticate the user according to token send.
	 *
	 * This hook only should run on the REST API requests to authenticate
	 * if the user Token is valid, for any other normal call ex. wp-admin/.*
	 * return the user.
	 *
	 * @since   1.0.0
	 * @param   int|bool $current_user
	 * @return  int|bool
	 */
	public function simplejwt_determine_current_user( $current_user ) {
		$rest_api_slug = rest_get_url_prefix();
		$requested_uri = ! empty( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		// If already valid user, or have an invalid url, don't attempt to validate token.
		$is_rest_defined = defined( 'REST_REQUEST' ) && REST_REQUEST;
		$is_rest_request = $is_rest_defined || ( false !== strpos( $requested_uri, $rest_api_slug ) );

		if ( $is_rest_request && $current_user ) {
			return $current_user;
		}

		// The /token/validate endpoint handles its own bearer token; skip middleware auth.
		if ( $is_rest_request && false !== strpos( $requested_uri, 'token/validate' ) ) {
			return $current_user;
		}

		// Respect the enable_auth toggle.
		if ( ! $this->simplejwt_is_auth_enabled() ) {
			return $current_user;
		}

		// Get the Authorization header and check for the token.
		$auth_header = $this->simplejwt_get_auth_header();

		if ( '' === $auth_header || 0 !== strpos( $auth_header, 'Bearer' ) ) {
			return $current_user;
		}

		$token = $this->simplejwt_extract_bearer( $auth_header );

		if ( '' === $token ) {
			return $current_user;
		}

		$user_id = TokenManager::verify_access_token( $token );

		if ( is_wp_error( $user_id ) ) {
			if ( $user_id->get_error_code() !== 'simplejwt_no_auth_header' ) {
				$this->jwt_error = $user_id;
			}

			return $current_user;
		}

		// Everything is ok, return the user ID from token.
		return $user_id;
	}

	/**
	 * Filter to hook the rest_pre_dispatch, if the is an error in the request
	 * send it, if there is no error just continue with the current request.
	 *
	 * @param   $request
	 * @return  mixed|WP_Error|null
	 */
	public function simplejwt_rest_pre_dispatch( $request ) {
		if ( is_wp_error( $this->jwt_error ) ) {
			return $this->jwt_error;
		}

		return $request;
	}

	/**
	 * Revoke all refresh tokens for the user on logout.
	 *
	 * @since 2.0.0
	 * @param int $user_id
	 */
	public function simplejwt_revoke_all_on_logout( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( $user_id ) {
			RefreshStore::revoke_all_for_user( $user_id );
		}
	}

	/**
	 * Revoke all refresh tokens for the user on password reset.
	 *
	 * @since 2.0.0
	 * @param \WP_User $user
	 * @param string   $new_pass
	 */
	public function simplejwt_revoke_all_on_password_reset( $user, $new_pass ) {
		if ( $user instanceof WP_User ) {
			RefreshStore::revoke_all_for_user( $user->ID );
		}
	}

	/**
	 * Issue and persist a new refresh token for a user.
	 *
	 * @since 2.0.0
	 * @param int $user_id
	 * @return string|WP_Error The raw refresh token, or an error.
	 */
	private function simplejwt_issue_refresh_token( int $user_id ) {
		$raw    = TokenManager::generate_refresh_token();
		$hash   = TokenManager::hash_refresh_token( $raw );
		$family = TokenManager::generate_family_id();

		$stored = RefreshStore::store( $user_id, $hash, $family, TokenManager::get_refresh_ttl() );

		if ( ! $stored ) {
			return new WP_Error(
				'simplejwt_token_creation_error',
				JWTNotice::get_notice( 'unknown_error' ),
				array( 'status' => 500 )
			);
		}

		return $raw;
	}

	/**
	 * Perform the refresh-token rotation flow.
	 *
	 * @since 2.0.0
	 * @param string $raw_refresh The raw refresh token.
	 * @return WP_REST_Response|WP_Error
	 */
	private function simplejwt_perform_refresh( string $raw_refresh ) {
		$hash = TokenManager::hash_refresh_token( $raw_refresh );
		$row  = RefreshStore::find_by_hash( $hash );

		if ( ! $row ) {
			return new WP_Error(
				'simplejwt_invalid_refresh_token',
				JWTNotice::get_notice( 'invalid_refresh_token' ),
				array( 'status' => 403 )
			);
		}

		if ( $row['revoked'] ) {
			// A manually revoked token (e.g. via /token/revoke or logout) has no
			// `rotated_at`; reject it directly without triggering reuse detection.
			if ( empty( $row['rotated_at'] ) ) {
				return new WP_Error(
					'simplejwt_revoked_token',
					JWTNotice::get_notice( 'revoked_token' ),
					array( 'status' => 403 )
				);
			}

			// A rotated token replayed inside the grace window is a benign
			// concurrent retry; outside the window it is treated as theft.
			$is_grace = ( time() - strtotime( $row['rotated_at'] ) ) <= TokenManager::GRACE_WINDOW;

			if ( ! $is_grace ) {
				RefreshStore::revoke_family( $row['family_id'] );

				/**
				 * Fired when refresh-token reuse (theft) is detected and a family is revoked.
				 *
				 * @since 2.0.0
				 * @param int    $user_id   The user id.
				 * @param string $family_id The revoked rotation family id.
				 * @param string $ip        The client IP address.
				 */
				do_action( 'simplejwt_auth_token_reuse_detected', (int) $row['user_id'], $row['family_id'], $this->simplejwt_client_ip() );

				return new WP_Error(
					'simplejwt_reused_refresh_token',
					JWTNotice::get_notice( 'reused_refresh_token' ),
					array( 'status' => 403 )
				);
			}
		}

		if ( strtotime( $row['expires_at'] ) < time() ) {
			return new WP_Error(
				'simplejwt_expired_token',
				JWTNotice::get_notice( 'expired_token' ),
				array( 'status' => 403 )
			);
		}

		$user = get_user_by( 'id', (int) $row['user_id'] );

		if ( ! $user ) {
			return new WP_Error(
				'simplejwt_invalid_refresh_token',
				JWTNotice::get_notice( 'invalid_refresh_token' ),
				array( 'status' => 403 )
			);
		}

		$access = TokenManager::issue_access_token( $user );

		if ( is_wp_error( $access ) ) {
			return $access;
		}

		$new_raw  = TokenManager::generate_refresh_token();
		$new_hash = TokenManager::hash_refresh_token( $new_raw );

		$rotated = RefreshStore::rotate( $hash, $new_hash, $row['family_id'], TokenManager::get_refresh_ttl() );

		if ( ! $rotated ) {
			return new WP_Error(
				'simplejwt_token_creation_error',
				JWTNotice::get_notice( 'unknown_error' ),
				array( 'status' => 500 )
			);
		}

		return $this->simplejwt_token_response( $user, $access, $new_raw );
	}

	/**
	 * Build the standard token response envelope.
	 *
	 * @since 2.0.0
	 * @param \WP_User $user
	 * @param string   $access
	 * @param string   $refresh
	 * @return WP_REST_Response
	 */
	private function simplejwt_token_response( WP_User $user, string $access, string $refresh ) {
		$data = new WP_REST_Response(
			array(
				'code'    => 'simplejwt_auth_credential',
				'message' => JWTNotice::get_notice( 'auth_credential' ),
				'data'    => array(
					'status'             => 200,
					'id'                 => $user->ID,
					'email'              => $user->user_email,
					'nicename'           => $user->user_nicename,
					'display_name'       => $user->display_name,
					'token'              => $access,
					'token_expires_in'   => TokenManager::get_access_ttl(),
					'refresh_token'      => $refresh,
					'refresh_expires_in' => TokenManager::get_refresh_ttl(),
				),
			),
			200
		);

		// Let the user modify the data before send it back using `add_filter`.
		return apply_filters( 'simplejwt_token_before_dispatch', $data, $user );
	}

	/**
	 * Whether JWT authentication is enabled.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	private function simplejwt_is_auth_enabled() {
		return filter_var( DBManager::get_config( 'enable_auth' ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Read the Authorization header (handles Apache/REDIRECT variants).
	 *
	 * @since 2.0.0
	 * @return string
	 */
	private function simplejwt_get_auth_header() {
		$auth = ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';

		if ( ! $auth ) {
			$auth = ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) : '';
		}

		return $auth;
	}

	/**
	 * Extract the token from a `Bearer <token>` header value.
	 *
	 * @since 2.0.0
	 * @param string $auth_header
	 * @return string
	 */
	private function simplejwt_extract_bearer( string $auth_header ) {
		if ( '' === $auth_header || 0 !== strpos( $auth_header, 'Bearer' ) ) {
			return '';
		}

		list( $token ) = sscanf( $auth_header, 'Bearer %s' );

		return $token ? $token : '';
	}

	/**
	 * A basic transient-backed rate limiter (deterrent, not a robust defense).
	 *
	 * @since 2.0.0
	 * @param string $identifier A stable identifier for the operation.
	 * @return bool True if the request is allowed, false if it should be blocked.
	 */
	private function simplejwt_rate_limit( string $identifier ) {
		$max    = (int) apply_filters( 'simplejwt_rate_limit_max', 10 );
		$window = (int) apply_filters( 'simplejwt_rate_limit_window', MINUTE_IN_SECONDS );

		$key   = 'simplejwt_rl_' . md5( $identifier );
		$count = get_transient( $key );

		if ( false === $count ) {
			set_transient( $key, 1, $window );

			return true;
		}

		if ( (int) $count >= $max ) {
			return false;
		}

		set_transient( $key, (int) $count + 1, $window );

		return true;
	}

	/**
	 * Best-effort client IP address.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	private function simplejwt_client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}
}
