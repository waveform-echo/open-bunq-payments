# Security Policy

## Reporting a vulnerability

Do not publish active vulnerabilities, credentials, access tokens, private keys, bank-account information or exploitable reproduction details in a public issue.

Until a dedicated security contact exists for the public repository, repository maintainers should configure GitHub Private Vulnerability Reporting before accepting production use reports.

## Security guarantees intended by design

- OAuth Client Secret, access token, SureCart API key and bunq API context/private key are encrypted at rest using a key derived from WordPress secret salts.
- OAuth uses a high-entropy, short-lived state value.
- API responses are rejected when the bunq server signature is missing or invalid.
- Provider callbacks/webhooks are hints only.
- A payment becomes `verified` only after a fresh provider read reports `ACCEPTED` and exact amount/currency match.
- Verification uses an atomic state transition so concurrent webhook/cron requests cannot intentionally emit the same verified event twice.
- External payment redirects are restricted to trusted HTTPS bunq hosts by default.
- Return URLs are restricted to the same WordPress site by default.
- Manual bunq.me mode never auto-verifies payment.
- No card data is collected or stored by this plugin.

## Deployment responsibilities

The merchant is responsible for WordPress/host security, HTTPS, access control, backups, protecting OAuth credentials, keeping dependencies supported, and validating the exact bunq/SureCart/WooCommerce account configuration before production use.

## Repository credential scanning

CI runs `tools/secret_scan.py` against both the current working tree and fetched Git history. The scanner reports only file/line/detector metadata and intentionally does not echo matched credential material into logs.

A passing scan means no configured detector matched; it is not proof that arbitrary secrets cannot exist. Maintainers should also enable GitHub Private Vulnerability Reporting and, where available, GitHub secret scanning/push protection.
