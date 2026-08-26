# Simple JWT Auth

![Simple JWT Auth](https://raw.githubusercontent.com/sayandey18/simple-jwt-auth/refs/heads/master/.wordpress-org/banner-1544x500.jpg)

<p align="center">
    <img src="https://img.shields.io/badge/Support-stable-blue" alt="Support Level">
    <img src="https://img.shields.io/wordpress/plugin/required-php/simple-jwt-auth?label=Required%20PHP" alt="Required PHP Version">
    <img src="https://img.shields.io/wordpress/plugin/wp-version/simple-jwt-auth?label=Required%20WordPress" alt="Required WP Version">
    <img src="https://img.shields.io/wordpress/plugin/tested/simple-jwt-auth?label=Tested%20Upto" alt="Tested WP Version">
    <img src="https://img.shields.io/wordpress/plugin/v/simple-jwt-auth?label=Plugin%20Version" alt="Plugin Version">
    <img src="https://img.shields.io/wordpress/plugin/dt/simple-jwt-auth?label=Downloads" alt="WordPress Plugin Downloads">
</p>

> Extends the WP REST API using JSON Web Tokens for robust authentication, providing a secure and reliable way to access and manage WordPress data.

## Description

Extends the WordPress REST API using JSON Web Tokens for robust authentication and authorization. 

JSON Web Token (JWT) is an open standard ([RFC 7519](https://tools.ietf.org/html/rfc7519)) that defines a compact and self-contained way for securely transmitting information between two parties.

It provides a secure and reliable way to access and manage WordPress data from external applications, making it ideal for building headless CMS solutions.

- Support & question: [WordPress support forum](https://wordpress.org/support/plugin/simple-jwt-auth/)
- Reporting plugin's bug: [GitHub issues tracker](https://github.com/sayandey18/simple-jwt-auth/issues)

**Plugins GitHub Repo** https://github.com/sayandey18/simple-jwt-auth

## Enable PHP HTTP Authorization Header

HTTP Authorization is a mechanism that allows clients to provide credentials to servers, thereby gaining access to protected resources. This is typically achieved by sending a special header, the Authorization header, in the HTTP request.

#### Shared Hosts

Most shared hosts have disabled the **HTTP Authorization Header** by default.

To enable this option you'll need to edit your **.htaccess** file by adding the following:

```
RewriteEngine on
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]
```

#### WPEngine

To enable this option you'll need to edit your .htaccess file adding the follow:

```
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
```

## Configuration

Simple JWT Auth plugin needs a **Key-Encryption-Key (KEK)** to encrypt and decrypt the JWT **signing keys** (`secret_key`, `private_key`, and `public_key`) at rest. The KEK is defined via the **SIMPLE_JWT_AUTH_ENCRYPT_KEY** constant and must be exactly 32 characters long and never revealed. Rotating this KEK invalidates the stored signing keys and requires re-entering them in the plugin settings.

To add the **key-encryption-key** edit your `wp-config.php` file and add a new constant called **SIMPLE_JWT_AUTH_ENCRYPT_KEY**

```
define( 'SIMPLE_JWT_AUTH_ENCRYPT_KEY', 'your-32-char-encryption-key' );
```

Generate a 32 character key from here: [https://string-gen.netlify.app](https://string-gen.netlify.app)

This plugin requires **PHP 8.2** or higher and **WordPress 7.0** or higher.

Here is the sample response if the encryption key is not configured in wp-config.php file.

```
{
    "code": "simplejwt_bad_encryption_key",
    "message": "Encryption key is not configured properly.",
    "data": {
        "status": 403
    }
}
```

### Signing keys via wp-config.php constants

Instead of storing the signing keys in the database, you can define them directly in `wp-config.php` using constants. Constants take precedence over the values saved in the plugin settings, and their values are used as-is (plaintext, not encrypted — the KEK above only encrypts keys at rest in the database).

```
define( 'SIMPLE_JWT_AUTH_ALGORITHM',  'HS256' );     // HS256, HS384, HS512, RS256, RS384, RS512, ES256 or ES384.
define( 'SIMPLE_JWT_AUTH_SECRET_KEY', 'your-secret-key' );       // Required for HS* algorithms (min 32 chars).
define( 'SIMPLE_JWT_AUTH_PRIVATE_KEY', '-----BEGIN PRIVATE KEY-----...' ); // Required for RS*/ES* signing.
define( 'SIMPLE_JWT_AUTH_PUBLIC_KEY',  '-----BEGIN PUBLIC KEY-----...' ); // Required for RS*/ES* verification.
```

All four constants are optional:

| Constant | Overrides | Used for |
|----------|-----------|----------|
| `SIMPLE_JWT_AUTH_ALGORITHM` | `algorithm` | The JWT signing algorithm (HS256…ES384). |
| `SIMPLE_JWT_AUTH_SECRET_KEY` | `secret_key` | Symmetric (HS*) signing and verification. |
| `SIMPLE_JWT_AUTH_PRIVATE_KEY` | `private_key` | Asymmetric (RS*/ES*) signing. |
| `SIMPLE_JWT_AUTH_PUBLIC_KEY` | `public_key` | Asymmetric (RS*/ES*) verification. |

When a constant is defined, the corresponding field on the plugin Settings page is disabled and marked as "Defined in wp-config.php".

## REST Endpoints

When the plugin is activated, a new namespace is added.

```
/auth/v1
```

Also, five new endpoints are added to this namespace.

| Endpoints                         | HTTP Verb |
|-----------------------------------|:---------:|
| /wp-json/auth/v1/token            |    POST   |
| /wp-json/auth/v1/token/refresh    |    POST   |
| /wp-json/auth/v1/token/revoke     |    POST   |
| /wp-json/auth/v1/token/validate   |    POST   |
| /wp-json/auth/v1/me               |    GET    |

### Requesting/Generating Token

To generate a new token, submit a POST request to this endpoint. With `username` and `password` as the parameters.

It will validates the user credentials, and returns success response including a token if the authentication is correct or returns an error response if the authentication is failed.

```
curl --location 'https://example.com/wp-json/auth/v1/token' \
--header 'Content-Type: application/json' \
--data-raw '{
    "username": "wordpress_username",
    "password": "wordpress_password"
}'
```

#### Sample of success response

```
{
    "code": "simplejwt_auth_credential",
    "message": "Token created successfully",
    "data": {
        "status": 200,
        "id": "2",
        "email": "sayandey@outlook.com",
        "nicename": "sayan_dey",
        "display_name": "Sayan Dey",
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciO.........",
        "token_expires_in": 900,
        "refresh_token": "opaque-refresh-token",
        "refresh_expires_in": 1209600
    }
}
```

#### Sample of error response

```
{
    "code": "simplejwt_invalid_username",
    "message": "Error: The username admin_user is not registered on this site. If you are unsure of your username, try your email address instead.",
    "data": {
        "status": 403
    }
}
```

Once you get the token, you can store it somewhere in your application:

- using **Cookie** 
- or using **localstorage** 
- or using a wrapper like [localForage](https://localforage.github.io/localForage/) or [PouchDB](https://pouchdb.com/)
- or using local database like SQLite
- or your choice based on app you develop

Then you should pass this token as _Bearer Authentication_ header to every API call.

```
Authorization: Bearer your-generated-token
```

Here is an example to create WordPress post using JWT token authentication.

```
curl --location 'https://example.com/wp-json/wp/v2/posts' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciO.........' \
--data '{
    "title": "Dummy post through API",
    "content": "Lorem Ipsum is simply dummy text of the printing and typesetting industry.",
    "status": "publish",
    "tags": [
        4,
        5,
        6
    ]
}'
```

Plugin's middleware intercepts every request to the server, checking for the presence of the **Authorization** header. If the header is found, it attempts to decode the JWT token contained within.

Upon successful decoding, the middleware extracts the user information stored in the token and authenticates the user accordingly, ensuring that only authorized requests are processed.

### Validating Token

This is a helper endpoint to validate a token. You only will need to make a **POST** request sending the Bearer Authorization header.

```
curl --location --request POST 'https://example.com/wp-json/auth/v1/token/validate' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciO.........'
```

#### Sample of success response

```
{
    "code": "simplejwt_valid_token",
    "message": "Token is valid",
    "data": {
        "status": 200
    }
}
```

### Refresh & Revocation

Access tokens are short-lived. When an access token expires, POST the `refresh_token` to `/wp-json/auth/v1/token/refresh` to rotate it and receive a new access (and refresh) token. Refresh tokens are opaque, stored server-side (SHA-256 hashed), and rotate on every use; a detected reuse revokes the entire token family. To invalidate a session, POST the refresh token to `/wp-json/auth/v1/token/revoke`. Refresh tokens are also revoked automatically on user logout and password reset.

## REST Errors

If the token is invalid an error will be returned, here are some samples of errors.

#### Invalid Username

```
{
    "code": "simplejwt_invalid_username",
    "message": "Error: The username admin is not registered on this site. If you are unsure of your username, try your email address instead.",
    "data": {
        "status": 403
    }
}
```

#### Invalid Password

```
{
    "code": "simplejwt_incorrect_password",
    "message": "Error: The password you entered for the username tiyasha_das is incorrect. Lost your password?",
    "data": {
        "status": 403
    }
}
```

#### Invalid Signature

```
{
    "code": "simplejwt_invalid_token",
    "message": "Signature verification failed",
    "data": {
        "status": 403
    }
}
```

#### Invalid Token

```
{
    "code": "simplejwt_invalid_token",
    "message": "Syntax error, malformed JSON",
    "data": {
        "status": 403
    }
}
```

#### Expired Token

```
{
    "code": "simplejwt_invalid_token",
    "message": "Expired token",
    "data": {
        "status": 403
    }
}
```

#### No Authorization

```
{
    "code": "simplejwt_no_auth_header",
    "message": "Authorization header not found",
    "data": {
        "status": 403
    }
}
```

#### Bad Authorization

```
{
    "code": "simplejwt_bad_auth_header",
    "message": "Authorization header malformed",
    "data": {
        "status": 400
    }
}
```

#### Wrong Algorithm Token

```
{
    "code": "simplejwt_invalid_token",
    "message": "Incorrect key for this algorithm",
    "data": {
        "status": 403
    }
}
```

#### Unsupported Algorithm

```
{
    "code": "simplejwt_unsupported_algorithm",
    "message": "Unsupported algorithm see https://tinyurl.com/uf4ns6fm",
    "data": {
        "status": 403
    }
}
```

#### Bad Configuration

```
{
    "code": "simplejwt_bad_config",
    "message": "JWT is not configured properly, please contact the admin",
    "data": {
        "status": 403
    }
}
```

#### Bad Encryption Key

```
{
    "code": "simplejwt_bad_encryption_key",
    "message": "Encryption key is not configured properly.",
    "data": {
        "status": 403
    }
}
```
#### Invalid Encryption Key Length

```
{
    "code": "simplejwt_invalid_enckey_length",
    "message": "Encryption key must be exactly 32 characters long",
    "data": {
        "status": 400
    }
}
```

## Available Hooks

**Simple JWT Auth** is a developer-friendly plugin. It has various filter hooks available to override the default settings.

#### simplejwt_cors_allow_headers

The `simplejwt_cors_allow_headers` allows you to modify the available headers when the Cross-Origin Resource Sharing (CORS) support is enabled.

Default value:

```
'Access-Control-Allow-Headers, Content-Type, Authorization'
```

Usage example:

```
/**
 * Change the allowed CORS headers.
 *
 * @param   string $headers The allowed headers.
 * @return  string The allowed headers.
 */
add_filter("simplejwt_cors_allow_headers", function ($headers) {
    // Modify the headers here.
    return $headers;
});
```

#### simplejwt_auth_iss

The `simplejwt_auth_iss` allows you to change the [**iss**](https://datatracker.ietf.org/doc/html/rfc7519#section-4.1.1) value before the payload is encoded to be a token.

Default value:

```
get_bloginfo( 'url' );
```

Usage example:

```
/**
 * Change the token issuer.
 *
 * @param   string $iss The token issuer.
 * @return  string The token issuer.
 */
add_filter("simplejwt_auth_iss", function ($iss) {
    // Modify the "iss" here.
    return $iss;
});
```

#### simplejwt_not_before

The `simplejwt_not_before` allows you to change the [**nbf**](https://tools.ietf.org/html/rfc7519#section-4.1.5) value before the payload is encoded to be a token.

Default value:

```
time();
```

Usage example:

```
/**
 * Change the token's nbf value.
 *
 * @param   int $not_before The default "nbf" value in timestamp.
 * @param   int $issued_at The "iat" value in timestamp.
 * @return  int The "nbf" value.
 */
add_filter(
    "simplejwt_not_before",
    function ($not_before, $issued_at) {
        // Modify the "not_before" here.
        return $not_before;
    },
    10,
    2,
);
```

#### simplejwt_auth_expire

The `simplejwt_auth_expire` allows you to change the value [**exp**](https://tools.ietf.org/html/rfc7519#section-4.1.4) before the payload is encoded to be a token.

Default value:

```
time() + ( DAY_IN_SECONDS * 7 )
```

Usage example:

```
/**
 * Change the token's expire value.
 *
 * @param   int $expire The default "exp" value in timestamp.
 * @param   int $issued_at The "iat" value in timestamp.
 * @return  int The "nbf" value.
 */
add_filter(
    "simplejwt_auth_expire",
    function ($expire, $issued_at) {
        // Modify the "expire" here.
        return $expire;
    },
    10,
    2,
);
```

#### simplejwt_payload_before_sign

The `simplejwt_payload_before_sign` allows you to modify all the payload data before being encoded and signed.

Default value:

```
$payload = [
    "iss" => $this->simplejwt_get_iss(),
    "iat" => $issued_at,
    "nbf" => $not_before,
    "exp" => $expire,
    "data" => [
        "user" => [
            "id" => $user->data->ID,
        ],
    ],
];
```

Usage example:

```
/**
 * Modify the payload data before being encoded & signed.
 *
 * @param   array $payload The default payload
 * @param   WP_User $user The authenticated user.
 * @return  array The payloads data.
 */
add_filter(
    "simplejwt_payload_before_sign",
    function ($payload, $user) {
        // Modify the payload here.
        return $payload;
    },
    10,
    2,
);
```

#### simplejwt_token_before_dispatch

The `simplejwt_token_before_dispatch` allows you to modify the token response before to dispatch it to the client.

Default value:

```
$data = new WP_REST_Response(
    [
        "code" => "simplejwt_auth_credential",
        "message" => JWTNotice::get_notice("auth_credential"),
        "data" => [
            "status" => 200,
            "id" => $user->data->ID,
            "email" => $user->data->user_email,
            "nicename" => $user->data->user_nicename,
            "display_name" => $user->data->display_name,
            "token" => $token,
        ],
    ],
    200,
);
```

Usage example:

```
/**
 * Modify the JWT response before dispatch.
 *
 * @param   WP_REST_Response $data The token response data.
 * @param   WP_User $user The user object for whom the token is being generated.
 * @return  WP_REST_Response Modified token response data.
 */
add_filter(
    "simplejwt_token_before_dispatch",
    function ($data, $user) {
        // Modify the response data.
        if ($user instanceof WP_User) {
        }
        return $data;
    },
    10,
    2,
);
```

#### simplejwt_auth_token_reuse_detected

The `simplejwt_auth_token_reuse_detected` action fires when a refresh token reuse is detected and the token family is revoked.

Args:

- `int $user_id` The affected user ID.
- `int $family_id` The refresh token family ID.
- `string $ip` The client IP address.

Usage example:

```
/**
 * Handle a detected refresh token reuse.
 *
 * @param   int    $user_id   The affected user ID.
 * @param   int    $family_id The refresh token family ID.
 * @param   string $ip        The client IP address.
 */
add_action("simplejwt_auth_token_reuse_detected", function ($user_id, $family_id, $ip) {
    // Handle the reuse event here.
}, 10, 3);
```

#### simplejwt_rate_limit_max

The `simplejwt_rate_limit_max` filter allows you to change the maximum number of attempts allowed within the rate-limit window.

Default value:

```
10
```

Usage example:

```
/**
 * Change the maximum rate-limit attempts.
 *
 * @param   int $max The maximum attempts.
 * @return  int The maximum attempts.
 */
add_filter("simplejwt_rate_limit_max", function ($max) {
    // Modify the maximum here.
    return $max;
});
```

#### simplejwt_rate_limit_window

The `simplejwt_rate_limit_window` filter allows you to change the rate-limit window, in seconds.

Default value:

```
MINUTE_IN_SECONDS
```

Usage example:

```
/**
 * Change the rate-limit window.
 *
 * @param   int $window The window in seconds.
 * @return  int The window in seconds.
 */
add_filter("simplejwt_rate_limit_window", function ($window) {
    // Modify the window here.
    return $window;
});
```

## Credits

* [WordPress REST API](https://developer.wordpress.org/rest-api/)
* [php-jwt by Firebase](https://github.com/firebase/php-jwt)

## Installation

This section describes how to install the plugin and get it working.

### Using FTP Client

1. Download the latest plugin from [here](https://downloads.wordpress.org/plugin/simple-jwt-auth.zip)
2. Unzip the `simple-jwt-auth.zip` file in your computer.
3. Upload `simple-jwt-auth` folder into the `/wp-content/plugins/` directory.
4. Activate the plugin through the 'Plugins' dashboard.

### Uploading from Dashboard

1. Download the latest plugin from [here](https://downloads.wordpress.org/plugin/simple-jwt-auth.zip)
2. Navigate to the Plugins section and click 'Add New Plugin' from the dashboard.
3. Navigate to the Upload area by clicking on the 'Upload Plugin' button.
4. Select the `simple-jwt-auth.zip` from your computer.
5. Click on the 'Install Now' button.
6. Activate the plugin through the 'Plugins' dashboard.