# External Services

The plugin communicates with third-party services only for configured functionality.

## bunq

API/OAuth mode communicates with bunq OAuth and API endpoints to:
- authorize the merchant;
- create API installation/device/session context;
- list monetary accounts;
- create/read RequestInquiry payments;
- configure notification URL filters.

Data can include merchant-reference, payment description, amount, currency, account/request identifiers and technical authentication/signature material.

Review bunq's current API/OAuth documentation and privacy/terms before deployment.

## SureCart (optional)

When the SureCart adapter is enabled/configured, the plugin sends server-side authenticated requests to `api.surecart.com` to provision/update the bunq manual method, inspect checkout/order state and mark a checkout manually paid only after bunq verification.

## No analytics service

The plugin itself does not send telemetry/analytics to project maintainers.
