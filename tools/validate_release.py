#!/usr/bin/env python3
"""Validate that a release tag is version-consistent and acceptance-gated."""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCHEMA = "open-bunq-payments.release-acceptance.v1"
REQUIRED_CHECKS = (
    "sandbox_oauth",
    "sandbox_request_inquiry",
    "sandbox_payment_acceptance",
    "negative_payment_cases",
    "woocommerce_runtime",
    "surecart_runtime",
    "live_small_transaction",
    "security_review",
)


def fail(message: str) -> int:
    print(f"RELEASE_GATE_FAIL: {message}", file=sys.stderr)
    return 1


def extract(pattern: str, text: str, label: str) -> str:
    match = re.search(pattern, text, flags=re.MULTILINE)
    if not match:
        raise ValueError(f"could not read {label}")
    return match.group(1).strip()


def main() -> int:
    if len(sys.argv) != 2:
        return fail("usage: validate_release.py vX.Y.Z")

    tag = sys.argv[1].strip()
    match = re.fullmatch(r"v(\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)", tag)
    if not match:
        return fail(f"invalid tag format: {tag!r}")
    version = match.group(1)

    try:
        plugin = (ROOT / "open-bunq-payments.php").read_text(encoding="utf-8")
        readme = (ROOT / "readme.txt").read_text(encoding="utf-8")
        header_version = extract(r"^\s*\*\s*Version:\s*(\S+)", plugin, "plugin header version")
        constant_version = extract(r"define\(\s*['\"]OBP_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]", plugin, "OBP_VERSION")
        stable_tag = extract(r"^Stable tag:\s*(\S+)", readme, "readme Stable tag")
    except (OSError, ValueError) as exc:
        return fail(str(exc))

    versions = {
        "tag": version,
        "plugin_header": header_version,
        "OBP_VERSION": constant_version,
        "readme_stable_tag": stable_tag,
    }
    if len(set(versions.values())) != 1:
        return fail("version mismatch: " + json.dumps(versions, sort_keys=True))

    receipt_path = ROOT / "release-receipts" / f"{tag}.json"
    if not receipt_path.is_file():
        return fail(f"missing acceptance receipt: {receipt_path.relative_to(ROOT)}")

    try:
        receipt = json.loads(receipt_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        return fail(f"invalid receipt JSON: {exc}")

    if receipt.get("schema") != SCHEMA:
        return fail(f"receipt schema must be {SCHEMA!r}")
    if receipt.get("tag") != tag:
        return fail("receipt tag does not match pushed tag")
    if receipt.get("approved_for_release") is not True:
        return fail("receipt approved_for_release must be true")

    checks = receipt.get("checks")
    if not isinstance(checks, dict):
        return fail("receipt checks must be an object")
    failed = [name for name in REQUIRED_CHECKS if checks.get(name) != "PASS"]
    if failed:
        return fail("required checks not PASS: " + ", ".join(failed))

    evidence = receipt.get("evidence")
    if not isinstance(evidence, dict) or not str(evidence.get("release_receipt_reference", "")).strip():
        return fail("evidence.release_receipt_reference is required")
    if not str(evidence.get("security_review_reference", "")).strip():
        return fail("evidence.security_review_reference is required")

    print(f"RELEASE_GATE_PASS tag={tag} receipt={receipt_path.relative_to(ROOT)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
