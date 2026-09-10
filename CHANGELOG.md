# Changelog

## 3.0.0 — 2026-09-07

First repository-ready release.

- Replaced link-only payment assumptions with a shared provider-verification core.
- Added bunq OAuth sandbox/live flow.
- Added signed API sessions and server-response signature verification.
- Added RequestInquiry creation, status reconciliation, amount/currency checks and ledger.
- Added webhook-as-hint + five-minute reconciliation fallback.
- Added compare-and-swap verification to avoid duplicate downstream side effects.
- Added WooCommerce Classic, Blocks and HPOS-compatible adapter.
- Added SureCart Manual Payment Method adapter.
- Added shortcode, PHP API and REST API.
- Added optional manual bunq.me fallback.
- Added WP-CLI health/reconciliation/status commands.
- Added setup/health/payment-ledger admin UI.
- Added encrypted secret storage and conservative uninstall semantics.
- Added GitHub CI, security policy and project documentation.

### Explicit limitations

- automatic recurring renewal not implemented;
- automated refunds not implemented;
- live bunq/SureCart certification must be performed by the deploying merchant.
