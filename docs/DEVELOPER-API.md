# Developer API

## Functions

- `open_bunq_create_payment(array $args)`
- `open_bunq_get_payment(string $public_token)`
- `open_bunq_reconcile_payment(string $public_token)`

## Core actions

- `open_bunq/payment_created` — record, args
- `open_bunq/payment_verified` — record, provider RequestInquiry
- `open_bunq/payment_terminal` — record, provider RequestInquiry
- `open_bunq/payment_mismatch` — record, provider RequestInquiry
- `open_bunq/payment_error` — payment id, exception, args

## Filters

- `open_bunq/payment_args`
- `open_bunq/trusted_payment_url`
- `open_bunq/allow_external_return_url`
- `open_bunq/rest_can_create`
- `open_bunq/integrations`

## Adapter contract

A downstream adapter should:

1. create or resolve its native pending object;
2. call the central service once with a stable `integration + object_id`;
3. redirect/display the returned provider payment URL;
4. listen to `open_bunq/payment_verified` for only its integration;
5. re-fetch/check its native object before granting entitlement;
6. make its own downstream action idempotent;
7. never implement its own bunq OAuth/session/webhook verification.

The central service uses object locks and reuses a pending/verified payment for the same `integration + object_id`.
