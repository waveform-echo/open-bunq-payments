# GitHub publication and release policy

The repository may be public before the first production release. Public source availability is **not** the same thing as release acceptance.

## Repository baseline

1. Keep the repository public only if source and documentation are intended for public disclosure.
2. Keep the independent-project disclaimer in the README and plugin description.
3. Enable GitHub Actions and require CI before merge on the protected default branch.
4. Enable GitHub Private Vulnerability Reporting and, where available, GitHub secret scanning/push protection.
5. Do not place bunq, SureCart, WordPress, hosting or customer credentials in repository files, Actions logs, issues or release receipts.

## Git identity privacy

Public commits must never expose a maintainer's personal mailbox address. Before committing or rewriting history, configure the repository-local Git identity to the maintainer name plus the exact GitHub-generated noreply address shown in GitHub account email settings. Do not copy a private/verified mailbox into `git user.email`, commit metadata, documentation, examples, release receipts or CI logs.

Use repository-local configuration rather than changing unrelated repositories:

```text
git config --local user.name "YOUR_GITHUB_USERNAME"
git config --local user.email "YOUR_GITHUB_NOREPLY_ADDRESS"
git config --local user.useConfigOnly true
```

Before every push, inspect the commits that will be introduced and confirm their author and committer identities use GitHub noreply only. If an already-published commit contains a personal mailbox address, rewrite only the affected history and use `--force-with-lease`, never an unconditional force push. Coordinate first when other contributors may have based work on the old history.

The repository source itself must remain account-neutral: do not hardcode a maintainer's noreply identifier into tracked project files.

## CI security meaning

CI scans the current working tree **and fetched Git history** using `tools/secret_scan.py`. Markdown is included. The scanner covers configured credential formats, private-key blocks, bearer/JWT-like tokens and high-entropy literals assigned to common secret fields.

A green scan means **none of the configured detectors matched**. It must never be described as mathematical proof that no possible secret exists. GitHub-native secret scanning/push protection and human review remain additional controls.

## Build versus release

`workflow_dispatch` on `.github/workflows/release.yml` performs a build/test/package run and uploads an Actions artifact. It does **not** publish a GitHub Release.

A pushed `vX.Y.Z` tag enters the release path. Before GitHub may publish that release, the workflow requires all of the following:

1. the tag version matches the plugin header, `OBP_VERSION` and WordPress `Stable tag`;
2. `release-receipts/vX.Y.Z.json` exists and passes `tools/validate_release.py`;
3. the receipt records PASS for sandbox OAuth, RequestInquiry, accepted payment, negative cases, WooCommerce runtime, SureCart runtime, a controlled small live transaction and independent security review;
4. PHP lint, smoke tests and repository/history secret scans pass;
5. the install ZIP builds, its SHA-256 sidecar verifies and `unzip -t` passes.

Only then does the tag-triggered workflow use GitHub's built-in `GH_TOKEN` with job-scoped `contents: write` permission to create the GitHub Release and attach both the ZIP and SHA-256 file.

Do not create `v3.0.0` merely because the source version says 3.0.0. The tag is authorized only after the corresponding acceptance receipt exists and is truthful.

## Acceptance evidence

See [`../release-receipts/README.md`](../release-receipts/README.md) for the receipt schema. Store only non-secret evidence references in Git. Customer data, bank details, OAuth secrets, tokens, private keys and full sensitive provider payloads stay outside the public repository.

## Before wider promotion

- repeat supported WordPress/PHP/WooCommerce/SureCart runtime coverage for the release candidate;
- resolve material findings from independent code/security review;
- decide WordPress.org naming/trademark policy before plugin-directory submission;
- add screenshots/translations only after UI stabilizes.
