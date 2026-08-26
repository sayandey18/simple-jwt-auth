# Changelog

All notable changes to this project will be documented in this file.

## [2.0.0]

### Added

- Refresh token support: `POST /wp-json/auth/v1/token` now returns `refresh_token`, `token_expires_in`, and `refresh_expires_in` alongside the existing response fields.
- `POST /wp-json/auth/v1/token/refresh` endpoint to rotate access (and refresh) tokens.
- `POST /wp-json/auth/v1/token/revoke` endpoint to revoke a refresh token (and its family).
- `GET /wp-json/auth/v1/me` endpoint to return the authenticated user profile.
- Opaque, server-side refresh tokens (stored SHA-256 hashed) in the new `{wp_prefix}_simplejwt_refresh_tokens` table, with rotation on each use and reuse detection.
- Action hook `simplejwt_auth_token_reuse_detected` (args: `user_id`, `family_id`, `ip`).
- Filter hooks `simplejwt_rate_limit_max` (default `10`) and `simplejwt_rate_limit_window` (default `MINUTE_IN_SECONDS`).
- Standard `sub` and `jti` claims in the JWT payload (the legacy `data.user.id` is retained for backward compatibility).
- Admin settings for access token lifetime (seconds) and refresh token lifetime (seconds).
- Rate limiting (transient-based) applied to `/token`, `/token/refresh`, and `/token/revoke`.

### Changed

- Enforced the `enable_auth` setting (previously stored but never read). On upgrade from 1.0.2, a configured `secret_key` or `private_key` auto-sets `enable_auth = '1'`; fresh installs default to `'0'` (disabled).
- Corrected the supported signing algorithms to HS256, HS384, HS512, RS256, RS384, RS512, ES256, ES384 (removed ES512, PS256, PS384, PS512, which were previously listed but not supported by the JWT library).
- Upgraded `firebase/php-jwt` to `^7.1`.

### Fixed

- Terminology: `SIMPLE_JWT_AUTH_ENCRYPT_KEY` is now documented as the key-encryption-key (KEK) that encrypts the signing keys at rest; rotating it invalidates stored signing keys and requires re-entering them in settings.

### Security

- Refresh tokens are revoked on user logout and password reset.
- Refresh token reuse detection revokes the whole token family.

### Breaking

- Requires PHP 8.2 or higher (was 7.4).
- Requires WordPress 7.0 or higher (was 5.2/6.7).

## [1.0.2]

- Prior release.
