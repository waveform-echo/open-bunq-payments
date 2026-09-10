# Testing

## Local/static

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
node --check assets/js/wc-blocks.js
node --check assets/js/surecart.js
php tests/smoke.php
python3 tools/secret_scan.py --self-test
python3 tools/secret_scan.py --tree .
bash tools/update_manifest.sh
sha256sum -c SOURCE_MANIFEST.sha256
```

When testing from a full Git checkout, also scan history:

```bash
set -o pipefail
git log --all --full-history -p --no-ext-diff --no-renames \
  | python3 tools/secret_scan.py --stdin --label git-history
```

A passing secret scan means no configured detector matched. It is not proof that every possible credential format is absent.

## Provider sandbox acceptance

Test at least:

1. OAuth connect/reconnect.
2. monetary account discovery.
3. RequestInquiry created once per native order.
4. accepted exact payment -> one verified event.
5. duplicate webhook -> no duplicate downstream action.
6. cron + webhook race -> no duplicate downstream action.
7. rejection/revocation/expiration -> no entitlement.
8. accepted wrong amount -> mismatch, no entitlement.
9. accepted wrong currency -> mismatch, no entitlement.
10. redirect without payment -> still pending.
11. webhook without provider acceptance -> still pending.

## WooCommerce

Test Classic and Blocks if both are enabled, guest and logged-in checkout, HPOS, order retry, cancelled payment and a deliberately duplicated callback.

## SureCart

Test one-time checkout, payment link redirect/fallback email, accepted/rejected payment and verify that reusable/subscription checkout is refused.

## Controlled live acceptance

After sandbox is green, perform one explicitly authorised small live transaction. Save non-secret evidence of provider request id, WordPress payment ledger, native commerce order and final status. Do not commit customer data or credentials.

## Release gate

Before creating `vX.Y.Z`, create the corresponding `release-receipts/vX.Y.Z.json` described in [`../release-receipts/README.md`](../release-receipts/README.md). The tag-triggered workflow refuses publication when the receipt, version checks, tests, secret scans or ZIP integrity fail.
