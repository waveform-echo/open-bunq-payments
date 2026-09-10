# Open bunq Payments for WordPress

Community-built, open-source bunq payments for WordPress.

**One bunq provider core, multiple commerce adapters:**

- WooCommerce (Classic + Checkout Blocks)
- SureCart (Manual Payment Method + provider-verified `manually_pay`)
- Generic shortcode payments
- PHP developer API
- authenticated WordPress REST creation API
- extension hooks for third-party adapters

> This project is independent community software. It is **not affiliated with, endorsed by, or maintained by bunq B.V.** “bunq” is a trademark of its respective owner.

## Why this exists

A payment plugin should not infer “paid” from a browser redirect. Open bunq Payments treats bunq as the payment authority and WordPress commerce plugins as downstream consumers.

```text
checkout
  -> RequestInquiry at bunq
  -> customer pays
  -> webhook/cron wakes plugin
  -> plugin re-fetches RequestInquiry from bunq
  -> ACCEPTED + exact amount + exact currency
  -> atomic VERIFIED transition
  -> WooCommerce payment_complete / SureCart manually_pay / developer hook
```

A webhook is **never** accepted as payment proof by itself.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- OpenSSL PHP extension
- HTTPS for production
- a bunq OAuth application for public/multi-user deployments
- WooCommerce and/or SureCart only if using those adapters

## Features

### bunq core

- sandbox/live OAuth
- encrypted Client Secret, OAuth access token and local RSA private key
- signed bunq API calls
- bunq server-signature verification
- RequestInquiry creation and reconciliation
- monetary-account selection
- webhook URL registration without silently deleting existing bunq filters
- 5-minute reconciliation fallback
- amount/currency verification
- atomic/idempotent verification
- internal payment ledger
- optional legacy `bunq.me` manual mode (never automatically marks paid)

### WooCommerce

- classic checkout
- WooCommerce Checkout Blocks
- HPOS compatibility declaration
- one-time product payments
- provider truth -> `payment_complete()`
- rejected/revoked/expired requests fail unpaid orders

### SureCart

- provisions a non-reusable Manual Payment Method
- starts a bunq RequestInquiry for the finalized checkout
- provider truth -> SureCart `/checkouts/{id}/manually_pay`
- checkout email fallback carries the payment link if browser redirect integration is unavailable
- recurring/reusable checkouts are intentionally rejected

### Generic/developer

Shortcode:

```text
[open_bunq_payment amount="12.50" currency="EUR" description="Donation" reference="donation-42" label="Pay with bunq"]
```

PHP:

```php
$payment = open_bunq_create_payment([
    'integration'       => 'my-plugin',
    'object_id'         => 'invoice-123',
    'amount'            => '19.95',
    'currency'          => 'EUR',
    'description'       => 'Invoice 123',
    'merchant_reference'=> 'invoice-123',
    'return_url'        => home_url('/thank-you/'),
]);
```

Listen for verified payment truth:

```php
add_action('open_bunq/payment_verified', function($record, $bunq_request) {
    if ($record['integration'] !== 'my-plugin') {
        return;
    }
    // Grant exactly the entitlement represented by object_id.
}, 10, 2);
```

## What is deliberately NOT claimed

- No automatic recurring debit/subscription renewal via RequestInquiry.
- No automatic refunds.
- No card-data handling.
- No claim that a redirect or webhook equals payment.
- No automatic PMPro recurring subscription support.

For PMPro, see [`docs/PMPRO.md`](docs/PMPRO.md).

## Setup

See [`docs/SETUP-BUNQ.md`](docs/SETUP-BUNQ.md).

For a production site: **sandbox first**. Do not switch to live merely because OAuth connects.

## Security model

See [`SECURITY.md`](SECURITY.md) and [`docs/SECURITY.md`](docs/SECURITY.md).

## Data retention

The payment ledger and encrypted settings are preserved on normal uninstall by default because payment/audit data should not disappear accidentally. To deliberately remove plugin data on uninstall, define:

```php
define('OPEN_BUNQ_REMOVE_DATA_ON_UNINSTALL', true);
```

before uninstalling.

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md).

## License

GPL-2.0-or-later. See [`LICENSE`](LICENSE).
