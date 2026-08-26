# Test Key Fixtures

Throwaway keypairs used only by the test suite. **Do not use these keys in
production** — they are committed to the repository and therefore public.

- `rsa_private_key.pem` / `rsa_public_key.pem` — RSA 2048, for RS256/RS384/RS512.
- `ec256_private_key.pem` / `ec256_public_key.pem` — NIST P-256, for ES256.
- `ec384_private_key.pem` / `ec384_public_key.pem` — NIST P-384, for ES384.

There are intentionally no PS* fixtures: `firebase/php-jwt` requires the optional
`phpseclib` dependency for PS256, which this plugin does not bundle.

To regenerate (from the repo root, PHP 8.2+ with OpenSSL):

```sh
php -r '
$c = ["private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA];
$k = openssl_pkey_new($c); openssl_pkey_export($k, $p);
file_put_contents("tests/fixtures/rsa_private_key.pem", $p);
file_put_contents("tests/fixtures/rsa_public_key.pem", openssl_pkey_get_details($k)["key"]);
'
```

(On Windows you may need to pass `["config" => "<path-to-openssl.cnf>"]` to
`openssl_pkey_new()` / `openssl_pkey_export()`.)
