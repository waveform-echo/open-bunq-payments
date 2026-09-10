# Testing

## Static

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
node --check assets/js/wc-blocks.js
node --check assets/js/surecart.js
php tests/smoke.php
```

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

## Live

After sandbox green, perform one small live transaction. Save evidence of provider request id, WordPress payment ledger, native commerce order and final status without publishing customer/credential data.
