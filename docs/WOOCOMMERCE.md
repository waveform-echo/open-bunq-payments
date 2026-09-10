# WooCommerce

## Support

- Classic checkout
- Checkout Blocks
- HPOS compatibility declaration
- one-time product payments

The gateway intentionally declares only `products`. It does **not** claim WooCommerce Subscriptions automatic renewal or refund support.

## Flow

```text
Woo order needs payment
-> central Open bunq payment
-> RequestInquiry
-> order on-hold
-> provider reconciliation
-> ACCEPTED + amount/currency exact
-> WooCommerce payment_complete()
```

Rejected, revoked or expired provider requests can move an unpaid order to failed.

## Installation

1. Enable WooCommerce integration under Settings > Open bunq Payments.
2. Complete bunq OAuth/API setup.
3. In WooCommerce payment settings enable the `bunq` gateway.
4. Test both Classic and Blocks checkout if your site exposes both.

## PMPro memberships through WooCommerce

Paid Memberships Pro has an official WooCommerce Integration add-on that can map WooCommerce products to PMPro membership levels. This is the recommended route when you specifically need PMPro membership entitlement to follow a Woo order handled by this gateway.

Automatic recurring PMPro membership still requires a genuinely recurring-capable Woo payment setup; this plugin's RequestInquiry adapter does not simulate subscription renewals.
