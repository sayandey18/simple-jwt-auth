=== Simple JWT Auth – JWT Authentication for WordPress REST API ===
Contributors: sayandey18
Donate link: https://github.com/sayandey18
Tags: jwt authentication, jwt, json web token, rest api, rest api authentication, authentication, headless, headless cms, api, token, access token, refresh token
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 2.0.0
Requires PHP: 8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

JWT authentication for the WordPress REST API: access and refresh tokens with rotation, revocation, and validation for headless and decoupled apps.

== Description ==

Simple JWT Auth – JWT Authentication for WordPress REST API secures and protects your WordPress REST API using JSON Web Tokens. It lets external applications authenticate WordPress users, obtain an access token and a refresh token, and call any REST endpoint with a standard Bearer header.

JSON Web Token (JWT) is an open standard ([RFC 7519](https://tools.ietf.org/html/rfc7519)) that defines a compact, self-contained way to transmit information securely between two parties. This plugin uses JWT to provide a modern, stateless authentication layer for headless WordPress builds.

**Modern access-token and refresh-token architecture**

* Issues short-lived **access tokens** (stateless JWTs) alongside opaque **refresh tokens**.
* **Refresh-token rotation** — every refresh issues a new access token *and* a new refresh token, so a leaked refresh token is quickly invalidated.
* **Reuse detection** — re-presenting a rotated refresh token outside a short grace window revokes the entire token family and fires the `simplejwt_auth_token_reuse_detected` action.
* **Revocation** — revoke a single refresh token, its whole rotation family, or all of a user's sessions. Refresh tokens are also revoked automatically on logout and password reset.
* **Validation** — a dedicated endpoint verifies an access token on demand, and a `/me` endpoint returns the authenticated user's profile.

**Secure by design**

* Signing keys (`secret_key`, `private_key`, `public_key`) are encrypted at rest with AES-256-GCM using a key-encryption-key (KEK) defined in `wp-config.php`.
* Refresh tokens are opaque and stored only as SHA-256 hashes — the raw token is never written to the database.
* Rate limiting (configurable) is applied to the token, refresh, and revoke endpoints to deter brute force.
* Optional CORS support and optional XML-RPC disabling.

**Modern and flexible**

* Requires PHP 8.2+ and WordPress 7.0+.
* Supports HS256, HS384, HS512, RS256, RS384, RS512, ES256, and ES384 signing algorithms.
* Extensible via filter and action hooks for payload, expiry, issuer, CORS headers, and the token response.

**Built for developers** — authenticate WordPress from React, Next.js, Vue, mobile apps, and any other external client. Configuration can live in the plugin settings or be overridden with `wp-config.php` constants.

- Support & questions: [WordPress support forum](https://wordpress.org/support/plugin/simple-jwt-auth/)
- Bug reports: [GitHub issues tracker](https://github.com/sayandey18/simple-jwt-auth/issues)
- Source code: [GitHub repository](https://github.com/sayandey18/simple-jwt-auth)

== Enable PHP HTTP Authorization Header ==

HTTP Authorization is the mechanism clients use to send credentials to a server — a special `Authorization` header in the HTTP request. Many shared hosts have it disabled by default.

= Shared hosts =

Add the following to your `.htaccess` file:

```
RewriteEngine on
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]
```

= WP Engine =

Add the following to your `.htaccess` file:

```
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
```

== Configuration ==

Simple JWT Auth uses a **Key-Encryption-Key (KEK)** to encrypt and decrypt the JWT signing keys (`secret_key`, `private_key`, and `public_key`) at rest. Define it in `wp-config.php` with the `SIMPLE_JWT_AUTH_ENCRYPT_KEY` constant. The KEK must be exactly 32 characters long and must never be revealed.

```
define( 'SIMPLE_JWT_AUTH_ENCRYPT_KEY', 'your-32-char-encryption-key' );
```

Rotating the KEK invalidates the stored signing keys and requires re-entering them in the plugin settings (a `simplejwt_kek_mismatch` error is returned until then).

= Signing keys via wp-config.php constants =

Instead of storing signing keys in the database, define them directly in `wp-config.php`. Constants take precedence over the plugin settings, and their values are used as-is (plaintext, not encrypted).

```
define( 'SIMPLE_JWT_AUTH_ALGORITHM', 'HS256' );            // HS256, HS384, HS512, RS256, RS384, RS512, ES256 or ES384.
define( 'SIMPLE_JWT_AUTH_SECRET_KEY', 'your-secret-key' ); // Required for HS* algorithms (min 32 chars).
define( 'SIMPLE_JWT_AUTH_PRIVATE_KEY', '-----BEGIN PRIVATE KEY-----...' ); // Required for RS*/ES* signing.
define( 'SIMPLE_JWT_AUTH_PUBLIC_KEY', '-----BEGIN PUBLIC KEY-----...' );   // Required for RS*/ES* verification.
```

| Constant | Overrides | Used for |
|----------|-----------|----------|
| `SIMPLE_JWT_AUTH_ALGORITHM` | `algorithm` | The JWT signing algorithm. |
| `SIMPLE_JWT_AUTH_SECRET_KEY` | `secret_key` | Symmetric (HS*) signing and verification. |
| `SIMPLE_JWT_AUTH_PRIVATE_KEY` | `private_key` | Asymmetric (RS*/ES*) signing. |
| `SIMPLE_JWT_AUTH_PUBLIC_KEY` | `public_key` | Asymmetric (RS*/ES*) verification. |

When a constant is defined, the matching field on the Settings page is disabled and marked "Defined in wp-config.php".

= Enabling authentication =

For a fresh install, authentication is disabled by default. Turn on **Enable JWT** in the plugin settings, choose an algorithm, and provide the required signing key(s) before issuing tokens.

== REST Endpoints ==

The plugin registers the `auth/v1` namespace with five endpoints:

| Endpoint | Method | Purpose |
|----------|:------:|---------|
| `/wp-json/auth/v1/token` | POST | Authenticate credentials; return an access token and a refresh token. |
| `/wp-json/auth/v1/token/refresh` | POST | Rotate an access token (and refresh token) using a refresh token. |
| `/wp-json/auth/v1/token/revoke` | POST | Revoke a refresh token and its rotation family. |
| `/wp-json/auth/v1/token/validate` | POST | Validate an access token. |
| `/wp-json/auth/v1/me` | GET | Return the authenticated user's profile. |

= Generate a token =

Submit a `POST` request with `username` and `password`:

```
curl --location 'https://example.com/wp-json/auth/v1/token' \
--header 'Content-Type: application/json' \
--data-raw '{
    "username": "wordpress_username",
    "password": "wordpress_password"
}'
```

Success response:

```
{
    "code": "simplejwt_auth_credential",
    "message": "Token created successfully",
    "data": {
        "status": 200,
        "id": "2",
        "email": "user@example.com",
        "nicename": "username",
        "display_name": "User Name",
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOi...",
        "token_expires_in": 900,
        "refresh_token": "opaque-refresh-token",
        "refresh_expires_in": 1209600
    }
}
```

Store the access token and refresh token in your application (a secure cookie, `localStorage`, or a wrapper such as [localForage](https://localforage.github.io/localForage/)). Then pass the access token as a Bearer header on every protected request:

```
Authorization: Bearer your-access-token
```

For example, creating a post with an access token:

```
curl --location 'https://example.com/wp-json/wp/v2/posts' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOi...' \
--data '{
    "title": "Hello headless",
    "content": "Created through the REST API with JWT authentication.",
    "status": "publish"
}'
```

= Refresh a token =

Access tokens are short-lived. When one expires, send the refresh token to `/token/refresh` (in the body or as a Bearer header) to rotate it and receive a new access token and refresh token:

```
curl --location 'https://example.com/wp-json/auth/v1/token/refresh' \
--header 'Content-Type: application/json' \
--data-raw '{ "refresh_token": "opaque-refresh-token" }'
```

The response has the same shape as the token response. Each rotation invalidates the previous refresh token.

= Revoke a token =

To invalidate a session, send the refresh token to `/token/revoke`:

```
curl --location 'https://example.com/wp-json/auth/v1/token/revoke' \
--header 'Content-Type: application/json' \
--data-raw '{ "refresh_token": "opaque-refresh-token" }'
```

Success response:

```
{
    "code": "simplejwt_token_revoked",
    "message": "Token has been revoked",
    "data": { "status": 200 }
}
```

= Validate a token =

Verify an access token with a `POST` request carrying the Bearer header:

```
curl --location --request POST 'https://example.com/wp-json/auth/v1/token/validate' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOi...'
```

Success response:

```
{
    "code": "simplejwt_valid_token",
    "message": "Token is valid",
    "data": { "status": 200 }
}
```

= Current user =

Get the authenticated user's profile:

```
curl --location 'https://example.com/wp-json/auth/v1/me' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOi...'
```

Success response:

```
{
    "code": "simplejwt_user",
    "message": "User data retrieved successfully",
    "data": {
        "status": 200,
        "id": 2,
        "email": "user@example.com",
        "nicename": "username",
        "display_name": "User Name",
        "roles": ["administrator"]
    }
}
```

== REST Errors ==

Every error returns a consistent envelope with a stable `code`, a `message`, and a `data.status` HTTP status. Common codes include:

| Code | Meaning |
|------|---------|
| `simplejwt_missing_credentials` | Username or password is missing. |
| `simplejwt_invalid_username` | The username is not registered on this site. |
| `simplejwt_incorrect_password` | The password is incorrect. |
| `simplejwt_no_auth_header` | The Authorization header is missing. |
| `simplejwt_bad_auth_header` | The Authorization header is malformed. |
| `simplejwt_invalid_token` | The access token is invalid (bad signature, malformed, or not yet valid). |
| `simplejwt_expired_token` | The access or refresh token has expired. |
| `simplejwt_invalid_refresh_token` | The refresh token is unknown or invalid. |
| `simplejwt_reused_refresh_token` | A rotated refresh token was reused; the token family was revoked. |
| `simplejwt_revoked_token` | The token has been revoked. |
| `simplejwt_bad_issuer` | The token issuer does not match this server. |
| `simplejwt_unsupported_algorithm` | The configured signing algorithm is unsupported. |
| `simplejwt_rate_limited` | Too many requests; please try again later. |
| `simplejwt_bad_config` | JWT authentication is not configured or is disabled. |
| `simplejwt_bad_encryption_key` | The key-encryption-key is not configured. |
| `simplejwt_invalid_enckey_length` | The key-encryption-key is not exactly 32 characters. |
| `simplejwt_kek_mismatch` | The key-encryption-key was rotated; re-enter the signing keys. |

== Available Hooks ==

Simple JWT Auth is developer-friendly and exposes filter and action hooks to override its default behaviour.

= simplejwt_cors_allow_headers (filter) =

Modify the CORS `Access-Control-Allow-Headers` value. Default: `Access-Control-Allow-Headers, Content-Type, Authorization`.

```
add_filter( 'simplejwt_cors_allow_headers', function ( $headers ) {
    return $headers;
} );
```

= simplejwt_auth_iss (filter) =

Change the token `iss` (issuer) claim. Default: `get_bloginfo( 'url' )`.

```
add_filter( 'simplejwt_auth_iss', function ( $iss ) {
    return $iss;
} );
```

= simplejwt_not_before (filter) =

Change the token `nbf` (not-before) claim. Default: the issue time.

```
add_filter( 'simplejwt_not_before', function ( $not_before, $issued_at ) {
    return $not_before;
}, 10, 2 );
```

= simplejwt_auth_expire (filter) =

Change the token `exp` (expiry) claim. Default: `time() + access token lifetime` (900 seconds by default).

```
add_filter( 'simplejwt_auth_expire', function ( $expire, $issued_at ) {
    return $expire;
}, 10, 2 );
```

= simplejwt_payload_before_sign (filter) =

Modify the JWT payload before it is signed. The payload contains the `iss`, `iat`, `nbf`, `exp`, `sub`, and `jti` claims (plus the legacy `data.user.id`).

```
add_filter( 'simplejwt_payload_before_sign', function ( $payload, $user ) {
    return $payload;
}, 10, 2 );
```

= simplejwt_token_before_dispatch (filter) =

Modify the token response before it is returned to the client. The response includes the access token, refresh token, and their lifetimes.

```
add_filter( 'simplejwt_token_before_dispatch', function ( $data, $user ) {
    return $data;
}, 10, 2 );
```

= simplejwt_auth_token_reuse_detected (action) =

Fired when refresh-token reuse is detected and a token family is revoked. Arguments: `$user_id`, `$family_id`, `$ip`.

```
add_action( 'simplejwt_auth_token_reuse_detected', function ( $user_id, $family_id, $ip ) {
    // Alert, log, or revoke further sessions here.
}, 10, 3 );
```

= simplejwt_rate_limit_max (filter) =

Change the maximum number of attempts allowed within the rate-limit window. Default: `10`.

```
add_filter( 'simplejwt_rate_limit_max', function ( $max ) {
    return $max;
} );
```

= simplejwt_rate_limit_window (filter) =

Change the rate-limit window, in seconds. Default: `MINUTE_IN_SECONDS` (60).

```
add_filter( 'simplejwt_rate_limit_window', function ( $window ) {
    return $window;
} );
```

== Postman Collection ==

A ready-to-use Postman collection is bundled with the plugin. Open **Simple JWT Auth → Documentation** in your WordPress admin and click **Download Postman Collection**, then import the JSON into Postman. The collection preconfigures your site URL and includes the token, refresh, revoke, validate, and `/me` requests.

== Installation ==

= Using FTP =

1. Download the plugin from [here](https://downloads.wordpress.org/plugin/simple-jwt-auth.zip).
2. Unzip the `simple-jwt-auth.zip` file.
3. Upload the `simple-jwt-auth` folder to the `/wp-content/plugins/` directory.
4. Activate the plugin through the Plugins dashboard.

= Uploading from the dashboard =

1. Download the plugin from [here](https://downloads.wordpress.org/plugin/simple-jwt-auth.zip).
2. In the dashboard, go to Plugins → Add New Plugin.
3. Click Upload Plugin.
4. Select the `simple-jwt-auth.zip` file.
5. Click Install Now.
6. Activate the plugin through the Plugins dashboard.

== Frequently Asked Questions ==

= Where can I find the source code? =

Simple JWT Auth is open source. Visit the [GitHub repository](https://github.com/sayandey18/simple-jwt-auth) and consider giving it a star.

= How can I contribute? =

Thank you — contributions are welcome. See the [GitHub repository](https://github.com/sayandey18/simple-jwt-auth) for details.

= Where can I report a bug? =

Submit a ticket in the [WordPress support forum](https://wordpress.org/support/plugin/simple-jwt-auth/) or, for developers, [create a GitHub issue](https://github.com/sayandey18/simple-jwt-auth/issues).

= Why do I get "Encryption key is not configured properly"? =

The `SIMPLE_JWT_AUTH_ENCRYPT_KEY` constant is missing from `wp-config.php`. Add it with a value that is exactly 32 characters long.

= Does this plugin work with React, Next.js, Vue, or mobile apps? =

Yes. Any client that can make HTTP requests and send a `Authorization: Bearer <token>` header can authenticate against the REST API.

== Screenshots ==

1. Simple JWT Auth Settings
2. Simple JWT Auth Options
3. Simple JWT Auth Documentation

== Changelog ==

= 2.0.0 =
* Added refresh tokens with rotation and revocation (POST /token/refresh, POST /token/revoke).
* Added the /auth/v1/me endpoint to return the authenticated user profile.
* Added access token and refresh token lifetime settings.
* Added rate limiting to the token, refresh, and revoke endpoints.
* Enforced the enable_auth setting, auto-migrating existing configurations to stay enabled.
* Updated firebase/php-jwt to ^7.1 and raised requirements to WordPress 7.0 / PHP 8.2.
* Corrected the supported algorithms list (HS256, HS384, HS512, RS256, RS384, RS512, ES256, ES384).
* Security hardening: opaque refresh tokens stored SHA-256 hashed, reuse detection, and revocation on logout and password reset.

= 1.0.2 =
* Tested up to WordPress 6.7.

= 1.0.1 =
* Disabled direct file access.
* Fixed the undefined variable notice in the admin area.
* Bug fixes and improvements.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Version 2.0.0 is a major release requiring PHP 8.2+ and WordPress 7.0+. It introduces refresh-token rotation and revocation and now enforces the Enable JWT setting. Existing installs with configured signing keys are automatically kept enabled.
