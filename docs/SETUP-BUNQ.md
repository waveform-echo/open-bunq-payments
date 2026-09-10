# bunq Setup

## Recommended production model: OAuth/API

Public or reusable integrations should use bunq OAuth rather than asking every merchant to paste a private API key into a plugin.

### 1. Install

Install and activate Open bunq Payments. Open **Settings > Open bunq Payments**.

### 2. Start in Sandbox

Keep `Environment = Sandbox` until the complete payment lifecycle is green.

### 3. Create the OAuth application

In bunq's developer/OAuth area create an OAuth client for your site. Copy the **exact OAuth redirect URL** displayed by the plugin into the OAuth application configuration.

Save the Client ID and Client Secret in WordPress. The secret is encrypted and is not displayed again.

### 4. Connect

Click **Connect with bunq OAuth**, authorize at bunq, then return to WordPress. Select the desired monetary account.

The plugin creates its own local bunq installation keypair and session context. The local private key/access token are encrypted in WordPress options.

### 5. Webhooks

The plugin registers its unique HTTPS webhook target in bunq's notification URL filters while preserving filters it can read from the existing configuration.

A webhook is not payment proof. It only triggers a fresh RequestInquiry lookup.

### 6. Test

At minimum test:

- payment created;
- successful payment -> exact amount/currency -> verified;
- customer cancels/rejects;
- expired payment;
- duplicate callback;
- callback + cron race;
- amount/currency mismatch never grants entitlement;
- return to the correct order/checkout;
- sandbox/live configuration cannot be confused.

### 7. Go live

Create/use production OAuth credentials, set Environment to Live, reconnect, reselect the monetary account, and run a deliberately small live transaction before normal use.

## Device/IP note

bunq device registration can bind API use to permitted/source IPs. Hosting migrations, reverse proxies or changing outbound egress IPs can therefore require reconnection/device registration changes. Treat unexpected authentication failures after hosting/network changes as a provider-context issue, not a reason to disable signature verification.
