# Contributing

Contributions are welcome.

## Principles

1. Provider truth outranks redirects, browser state and webhook payloads.
2. Never silently grant an entitlement on an ambiguous payment state.
3. Adapters do not duplicate bunq authentication/reconciliation logic.
4. Recurring support must have a real recurring provider contract; never emulate it with one-time payments.
5. New external services must be disclosed in documentation.
6. Never include real credentials, customer data or production payloads in tests/issues.

## Development

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
node --check assets/js/wc-blocks.js
node --check assets/js/surecart.js
php tests/smoke.php
```

Open a pull request with:
- problem/goal;
- provider/platform documentation used;
- tests;
- backwards compatibility notes;
- security/privacy impact.
