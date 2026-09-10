#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$DIST" "$STAGE/open-bunq-payments"
rsync -a --exclude-from="$ROOT/.distignore" --exclude='dist' "$ROOT/" "$STAGE/open-bunq-payments/"
(cd "$STAGE" && zip -qr "$DIST/open-bunq-payments.zip" open-bunq-payments)
(cd "$DIST" && sha256sum open-bunq-payments.zip > open-bunq-payments.zip.sha256)
echo "Built $DIST/open-bunq-payments.zip"
