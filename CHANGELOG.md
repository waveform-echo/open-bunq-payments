# Changelog

## Unreleased — repository hardening

- Replaced the narrow grep-only credential check with a redacting scanner for the current tree and fetched Git history.
- Added an enforced, version-bound release acceptance receipt.
- Changed the release workflow so manual runs build artifacts only; a `v*` tag can publish a GitHub Release only after all release gates pass.
- Added ZIP checksum/integrity verification before publication.
- Made the release SHA-256 sidecar portable by recording the ZIP basename instead of an Actions runner path.
- Clarified that a green secret scan is detector coverage, not proof that arbitrary secrets cannot exist.
- Added a repository policy requiring GitHub-generated noreply commit identities for maintainers who keep email private, with `--force-with-lease` for any necessary identity-history repair.

## 3.0.0 — release candidate (source prepared 2026-09-07)

First repository-ready source candidate. No `v3.0.0` release should be published until the release acceptance gate passes.

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
