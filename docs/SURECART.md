# SureCart

## Architecture

The adapter uses SureCart's Manual Payment Method model as the checkout surface, but provider truth remains at bunq.

```text
SureCart checkout
-> bunq manual method selected
-> SureCart finalized/processing checkout
-> RequestInquiry created
-> customer pays at bunq
-> Open bunq re-fetches provider truth
-> exact ACCEPTED payment
-> PATCH /v1/checkouts/{id}/manually_pay
-> SureCart creates its normal purchase/order entitlement state
```

## Setup

1. Enable SureCart integration.
2. Enter a SureCart Secret API Key in Open bunq Payments.
3. Click **Provision/repair SureCart method**.
4. Verify `bunq` appears under SureCart payment processors/manual methods.
5. Test a one-time product.

The plugin also emails a payment link as a fallback when an order is created and the browser-side compatibility redirect did not occur.

## Recurring checkouts

The adapter creates `reusable=false` and rejects checkouts that require a reusable/recurring payment method. SureCart manual methods can be configured as reusable, but that would not make a bunq RequestInquiry an automatic renewal mandate. The plugin therefore fails closed instead of granting indefinite subscription access from one payment.

## External service disclosure

When SureCart integration is enabled, the configured SureCart Secret API Key is used server-side to call `https://api.surecart.com/v1` for manual method provisioning, checkout/order lookup, and `manually_pay` after provider verification.
