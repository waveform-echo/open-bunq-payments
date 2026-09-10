=== Open bunq Payments for WordPress ===
Contributors: open-bunq-payments-contributors
Tags: bunq, payments, woocommerce, surecart, payment gateway
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provider-verified bunq payments for WooCommerce, SureCart and custom WordPress integrations.

== Description ==

Open bunq Payments provides one shared bunq OAuth/API core with adapters for WooCommerce, SureCart and custom integrations.

Payment is never inferred from a redirect or webhook. In API mode the plugin re-fetches the RequestInquiry at bunq and verifies provider status, amount and currency before emitting a verified-payment event or marking a supported checkout paid.

This independent community project is not affiliated with or endorsed by bunq B.V.

== Installation ==

1. Upload and activate the plugin.
2. Open Settings > Open bunq Payments.
3. Keep Environment on Sandbox while integrating.
4. Create/configure a bunq OAuth application and register the exact redirect URL shown by the plugin.
5. Enter Client ID and Client Secret, save, then Connect with bunq OAuth.
6. Select the monetary account.
7. Enable/configure WooCommerce or provision the SureCart manual payment method.
8. Run end-to-end accepted, rejected and amount-mismatch tests before switching to Live.

== Frequently Asked Questions ==

= Does it support subscriptions? =

Not automatic renewals. bunq RequestInquiry is treated as a one-time payment request. Recurring/reusable SureCart checkouts and subscription-style automatic renewal claims are rejected unless a future recurring-capable provider implementation is added.

= Does a webhook mark an order paid? =

No. A webhook only triggers reconciliation. The plugin asks bunq for current provider truth before marking a payment verified.

= Can I use it without WooCommerce? =

Yes. SureCart, a generic shortcode, PHP API and authenticated REST API are included.

= Can I use bunq.me only? =

There is a manual legacy fallback. It creates a bunq.me URL but never marks an order paid automatically.

== Changelog ==

= 3.0.0 =
* New provider core with OAuth/API verification.
* WooCommerce and SureCart adapters.
* Generic shortcode, PHP and REST APIs.
* Atomic payment ledger and reconciliation.
* Encrypted secrets and server-signature verification.
