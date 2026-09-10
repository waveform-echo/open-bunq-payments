# Release acceptance receipts

A `vX.Y.Z` Git tag is not release authorization by itself.

For a tagged release, add `release-receipts/vX.Y.Z.json` **before** creating the tag. The release workflow validates the receipt, source version, tests, secret scan and package integrity before it may create a GitHub Release.

Required schema:

```json
{
  "schema": "open-bunq-payments.release-acceptance.v1",
  "tag": "vX.Y.Z",
  "approved_for_release": true,
  "checks": {
    "sandbox_oauth": "PASS",
    "sandbox_request_inquiry": "PASS",
    "sandbox_payment_acceptance": "PASS",
    "negative_payment_cases": "PASS",
    "woocommerce_runtime": "PASS",
    "surecart_runtime": "PASS",
    "live_small_transaction": "PASS",
    "security_review": "PASS"
  },
  "evidence": {
    "release_receipt_reference": "non-secret internal/public evidence reference",
    "security_review_reference": "non-secret review reference"
  }
}
```

Do not place credentials, customer data, bank-account information, full provider payloads, private keys or access tokens in this directory. Evidence references should point to appropriately protected records, not copy sensitive records into Git.

The receipt means the listed acceptance work was performed for this release candidate. It is not a universal guarantee for every merchant, host, WordPress configuration or provider account.
