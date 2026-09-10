# Paid Memberships Pro (PMPro)

Open bunq Payments 3.0 does not ship a direct recurring PMPro gateway.

## Recommended supported path

Use PMPro's official **WooCommerce Integration** add-on:

```text
PMPro membership level
<- mapped WooCommerce product
WooCommerce checkout
-> Open bunq Payments
-> verified Woo order
-> PMPro WooCommerce Integration grants/revokes the mapped membership according to its documented lifecycle
```

This avoids creating a second membership/payment authority inside this plugin.

## One-time memberships

A one-time PMPro level can also be sold as a one-time WooCommerce product through the PMPro integration.

## Recurring memberships

Do not configure “€X/month automatically” unless the selected WooCommerce payment rail actually supports automatic recurring renewal. Open bunq Payments' RequestInquiry adapter currently does not.

A future direct PMPro adapter must implement PMPro's gateway contract and a real recurring bunq-compatible payment/mandate lifecycle before it can claim subscription support.
