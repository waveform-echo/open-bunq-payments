# Security Details

## Secret storage

Secrets use authenticated encryption where available:

1. libsodium `secretbox` when available;
2. AES-256-GCM fallback through OpenSSL.

The encryption key is derived from WordPress secret salts. Moving the database to a site with different salts intentionally makes old encrypted secrets unreadable; reconnect the provider rather than copying plaintext credentials.

## OAuth CSRF protection

Each OAuth start creates a random state stored in a short-lived transient. The callback consumes that exact state. The callback can complete after the browser has lost its WordPress admin session; possession of a fresh state plus provider authorization is the authority, while initiating OAuth remains an admin-only operation.

## Provider signatures

The plugin creates a local RSA installation keypair and verifies `X-Bunq-Server-Signature` on signed API responses. Missing/invalid signatures fail closed.

## Redirect policy

Payment URLs must be HTTPS and on bunq hosts unless a developer deliberately extends the filter. Post-payment return URLs are same-site by default.

## Logging

Do not log secrets. Debug mode is intended for non-secret operational data. WooCommerce logger is used when present.
