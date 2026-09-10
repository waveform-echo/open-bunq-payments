#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
find . -type f \
  -not -path './.git/*' \
  -not -path './dist/*' \
  -not -path '*/__pycache__/*' \
  -not -name '*.pyc' \
  -not -path './SOURCE_MANIFEST.sha256' \
  -print0 | sort -z | xargs -0 sha256sum > SOURCE_MANIFEST.sha256
echo "Updated $ROOT/SOURCE_MANIFEST.sha256"
