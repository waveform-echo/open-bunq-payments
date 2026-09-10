# Architecture

```text
                  WordPress Admin
                       |
                Settings / OAuth
                       |
                 OBP_Provider
                       |
                  OBP_Bunq_API
        installation/device/session/signatures
                       |
                    bunq API
                       |
                RequestInquiry truth
                       |
              OBP_Payment_Service
            /         |          \
      OBP_Store   REST/cron    actions
         |                         |
   payment ledger             adapters
                         /       |        \
                  WooCommerce SureCart Generic/3rd-party
```

## Authority boundaries

- bunq: provider payment status
- Open bunq core: verification/reconciliation/ledger
- WooCommerce/SureCart/third party: order or entitlement state

## Payment state

Internal states include `created`, `pending`, `verified`, provider terminal states, `mismatch`, and `error`.

`verified` is a one-way compare-and-swap transition from an unpaid in-flight state after provider truth passes all checks. That transition is the only source of `open_bunq/payment_verified`.

## Webhooks

The webhook endpoint contains a random secret path token, but the token does not make webhook payload content authoritative. It only starts a provider reconciliation pass.

## Reconciliation

WP-Cron runs every five minutes as a fallback. Administrators and WP-CLI can also initiate reconciliation.
