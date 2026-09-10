# Troubleshooting

## OAuth returns “invalid/expired state”

Start Connect with bunq again. OAuth state expires after roughly 15 minutes and is single-use.

## OAuth works but account list is empty

Confirm the authorized bunq identity has monetary accounts available to the OAuth application and that API device/session creation succeeds. Inspect non-secret debug logs.

## Authentication breaks after moving host

bunq device permissions can be sensitive to source/permitted IPs. Reconnect/recreate the API context after hosting or outbound-IP changes instead of bypassing security checks.

## Order remains pending after payment

Run **Reconcile pending payments**. Check whether bunq reports `ACCEPTED`, whether amount/currency match, and whether WP-Cron can run. A redirect alone is expected to leave an unpaid RequestInquiry pending.

## `mismatch`

Do not manually force automatic completion. Compare expected order currency/amount with the provider RequestInquiry and resolve the discrepancy first.

## SureCart method missing

Save a valid SureCart Secret API Key and use **Provision/repair SureCart method**. Confirm the site can reach `api.surecart.com`.
