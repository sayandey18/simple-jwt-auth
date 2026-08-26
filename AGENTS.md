# AGENTS.md

WordPress plugin ("Simple JWT Auth") that secures the WP REST API with JWT. PHP 8.2+, WordPress 7.0+. Pure PHP + WordPress hooks, with PHPUnit tests, PHPCS lint, and a GitHub Actions CI/CD pipeline.

## Commands

- `composer install` — installs production + dev deps into `includes/vendor` (dev tools included during development).
- `composer run lint` — PHPCS (`includes/vendor/bin/phpcs --standard=phpcs.xml.dist`).
- `composer run test` — PHPUnit (requires the WP test suite; see `.github/workflows/ci.yml` for setup).
- `composer run build` — builds an optimized production zip at `dist/simple-jwt-auth.zip` (runs `composer install --no-dev` in a temp dir; your local dev tools are left intact).
- CI: `.github/workflows/ci.yml` (lint + test on push/PR) and `deploy.yml` (test → build → deploy to WordPress.org SVN on tag push).

## Architecture

- Entrypoint `simple-jwt-auth.php` defines constants and `simplejwt_auth_run()`; the WordPress Plugin Boilerplate structure wires everything via `Simple_Jwt_Auth_Loader`.
- REST namespace is `auth/v{SIMPLE_JWT_AUTH_API_VERSION}` (currently `auth/v1`, decoupled from plugin semver). Routes in `public/endpoints/simple-jwt-auth-rest.php`: `POST /token`, `POST /token/refresh`, `POST /token/revoke`, `POST /token/validate`, `GET /me`.
- JWT signing/verification uses `firebase/php-jwt`, but ALWAYS reference it through the wrapper classes `Simple_Jwt_Auth\Firebase\JWT\JWT` / `Key` (and `ExpiredException`/`SignatureInvalidException`/`BeforeValidException`) in `includes/class-simple-jwt-auth-namespace.php`. Never import `Firebase\JWT\JWT` directly.
- Token issuance/verification is centralized in `Simple_Jwt_Auth\Token\TokenManager` (`includes/token/class-simple-jwt-auth-token-manager.php`); refresh tokens are persisted (hashed) in the `{wp_prefix}_simplejwt_refresh_tokens` table via `Simple_Jwt_Auth\Database\RefreshStore`.
- Config is stored in the custom table `{wp_prefix}_simplejwt_config` (created on activation in `includes/class-simple-jwt-auth-activator.php`), accessed via `Simple_Jwt_Auth\Database\DBManager`. Settings keys: `enable_auth`, `algorithm`, `secret_key`, `public_key`, `private_key`, `enable_cors`, `disable_xmlrpc`, `supported_algo`, `token_expiration`, `refresh_expiration`.
- Signing keys are AES-256-GCM encrypted at rest (`includes/crypto/class-simple-jwt-auth-crypto.php`). All user-facing messages/error codes live in `Simple_Jwt_Auth\Notice\JWTNotice` (`includes/class-simple-jwt-auth-notice.php`).
- `includes/class-simple-jwt-auth-upgrader.php` runs versioned migrations on activation (e.g. the `enable_auth` auto-enable for existing installs).

## Gotchas

- Composer `vendor-dir` is `includes/vendor`, NOT `vendor/`. The vendor tree is **gitignored and NOT committed** — run `composer install` after cloning. Production builds run `composer install --no-dev`; dev deps (phpunit, wpcs, php-compatibility, yoast/polyfills) live in `require-dev` of the SAME `composer.json`. `composer.json` pins `platform.php = 8.2`.
- Requires `define( 'SIMPLE_JWT_AUTH_ENCRYPT_KEY', '<32-char key>' );` in `wp-config.php`. Must be EXACTLY 32 chars; the plugin returns `simplejwt_bad_encryption_key` / `simplejwt_invalid_enckey_length` otherwise. Rotating this key invalidates all stored signing keys (returns `simplejwt_kek_mismatch`) and requires re-entering them in settings.
- Version is duplicated and must be bumped in sync: header + `SIMPLE_JWT_AUTH_VERSION` in `simple-jwt-auth.php`, `composer.json` `version`, and `Stable tag:` in `readme.txt`. `SIMPLE_JWT_AUTH_API_VERSION` is separate — only bump it for a breaking route change.
- Supported algorithms are a single source of truth: `TokenManager::ALGORITHMS` (HS256/384/512, RS256/384/512, ES256/384). ES512/PS* are intentionally absent — firebase/php-jwt 7.x doesn't implement them (PS256 also needs phpseclib). Do not add them without the matching dependency.
- Code follows WordPress Coding Standards (PHPCS is configured but disables the style-only sniffs; keep the safety/security sniffs green). Tab indentation for WP plugin files, but 4-space indentation inside namespaced classes (`includes/crypto`, `includes/database`, `includes/token`, `includes/class-simple-jwt-auth-namespace.php`, `includes/class-simple-jwt-auth-notice.php`, `public/endpoints/simple-jwt-auth-rest.php`).
- `.distignore` lists files excluded from the production zip (dev tooling, docs, tests, `.wordpress-org`). The deploy action maps `.wordpress-org/` to SVN `assets/`.
- `tests/fixtures/` contains committed throwaway RSA/EC keypairs for tests only — never use in production.

## Style notes

- Text domain: `simple-jwt-auth`. All strings wrapped in `__()` / `esc_html__()`.
- Plugin identifier/prefix used for constants and hooks: `SIMPLE_JWT_AUTH_` / `simplejwt_`.
