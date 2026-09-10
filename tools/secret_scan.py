#!/usr/bin/env python3
"""Fail-closed secret-pattern scanner for repository source and git history.

This scanner intentionally reports only file/line/pattern metadata. It never prints
matched credential material into CI logs. A PASS means none of the configured
patterns were detected; it is not a proof that arbitrary secrets cannot exist.
"""
from __future__ import annotations

import argparse
import math
import os
import re
import sys
from pathlib import Path
from typing import Iterable, Iterator, Sequence, Tuple

EXCLUDED_DIRS = {".git", "dist", "vendor", "node_modules"}
MAX_FILE_BYTES = 5 * 1024 * 1024

PATTERNS: Sequence[Tuple[str, re.Pattern[str]]] = (
    ("private-key-block", re.compile(r"-----BEGIN (?:RSA |EC |DSA |OPENSSH )?PRIVATE KEY-----")),
    ("github-classic-token", re.compile(r"\bgh[pousr]_[A-Za-z0-9]{36,255}\b")),
    ("github-fine-grained-token", re.compile(r"\bgithub_pat_[A-Za-z0-9_]{60,255}\b")),
    ("aws-access-key", re.compile(r"\b(?:AKIA|ASIA|AIDA|AROA|AIPA|ANPA|ANVA|ASCA)[A-Z0-9]{16}\b")),
    ("google-api-key", re.compile(r"\bAIza[0-9A-Za-z_-]{35}\b")),
    ("slack-token", re.compile(r"\bxox[baprs]-[A-Za-z0-9-]{10,}\b")),
    ("sendgrid-api-key", re.compile(r"\bSG\.[A-Za-z0-9_-]{16,}\.[A-Za-z0-9_-]{16,}\b")),
    ("stripe-secret-key", re.compile(r"\bsk_(?:live|test)_[A-Za-z0-9_-]{16,}\b")),
    ("jwt", re.compile(r"\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\b")),
    ("bearer-token", re.compile(r"(?i)\bBearer\s+[A-Za-z0-9._~+/-]{24,}={0,2}\b")),
)

QUOTED_ASSIGNMENT_RE = re.compile(
    r"(?i)\b(?:client[_-]?secret|access[_-]?token|api[_-]?key|auth[_-]?token|secret[_-]?key|private[_-]?key|password|passwd)\b"
    r"\s*(?:=>|:=|=|:)\s*([\"'])([^\"'\r\n]{16,})\1"
)
ENV_ASSIGNMENT_RE = re.compile(
    r"(?i)^\s*(?:export\s+)?(?:client[_-]?secret|access[_-]?token|api[_-]?key|auth[_-]?token|secret[_-]?key|private[_-]?key|password|passwd)"
    r"\s*=\s*([A-Za-z0-9_./+=:@~-]{16,})\s*(?:#.*)?$"
)

PLACEHOLDER_MARKERS = (
    "example", "sample", "placeholder", "replace", "changeme", "your_", "your-",
    "xxxx", "dummy", "fake", "test-only", "not-a-secret", "redacted",
)


def entropy(value: str) -> float:
    if not value:
        return 0.0
    counts = {ch: value.count(ch) for ch in set(value)}
    n = len(value)
    return -sum((count / n) * math.log2(count / n) for count in counts.values())


def looks_like_placeholder(value: str) -> bool:
    lower = value.lower()
    if any(marker in lower for marker in PLACEHOLDER_MARKERS):
        return True
    if len(set(value)) <= 4:
        return True
    return False


def scan_line(line: str) -> Iterable[str]:
    for name, pattern in PATTERNS:
        if pattern.search(line):
            yield name
    for match in QUOTED_ASSIGNMENT_RE.finditer(line):
        value = match.group(2)
        if not looks_like_placeholder(value) and entropy(value) >= 3.3:
            yield "high-entropy-secret-assignment"
    env_match = ENV_ASSIGNMENT_RE.search(line)
    if env_match:
        value = env_match.group(1)
        if not looks_like_placeholder(value) and entropy(value) >= 3.3:
            yield "high-entropy-env-secret-assignment"


def scan_text(lines: Iterable[str], label: str) -> list[Tuple[str, int, str]]:
    findings: list[Tuple[str, int, str]] = []
    for lineno, line in enumerate(lines, 1):
        for pattern_name in scan_line(line):
            findings.append((label, lineno, pattern_name))
    return findings


def iter_tree(root: Path) -> Iterator[Path]:
    for path in root.rglob("*"):
        if not path.is_file():
            continue
        try:
            rel_parts = path.relative_to(root).parts
        except ValueError:
            continue
        if any(part in EXCLUDED_DIRS for part in rel_parts):
            continue
        try:
            if path.stat().st_size > MAX_FILE_BYTES:
                continue
        except OSError:
            continue
        yield path


def read_text_file(path: Path) -> Iterable[str] | None:
    try:
        data = path.read_bytes()
    except OSError:
        return None
    if b"\x00" in data:
        return None
    return data.decode("utf-8", errors="replace").splitlines()


def scan_tree(root: Path) -> list[Tuple[str, int, str]]:
    findings: list[Tuple[str, int, str]] = []
    for path in iter_tree(root):
        lines = read_text_file(path)
        if lines is None:
            continue
        label = str(path.relative_to(root))
        findings.extend(scan_text(lines, label))
    return findings


def emit(findings: Sequence[Tuple[str, int, str]]) -> int:
    if findings:
        print(f"SECRET_SCAN_FAIL findings={len(findings)}", file=sys.stderr)
        for label, lineno, pattern_name in findings:
            print(f"{label}:{lineno}: {pattern_name}", file=sys.stderr)
        print("Matched credential material is intentionally redacted.", file=sys.stderr)
        return 1
    print("SECRET_SCAN_PASS configured_patterns=clean")
    return 0


def self_test() -> int:
    positives = [
        "token='" + "ghp_" + "A1b2C3d4E5f6G7h8I9j0K1l2M3n4O5p6Q7r8" + "'",
        "-----BEGIN " + "PRIVATE KEY-----",
        "client_secret='" + "mR8vQ2nK7xP4cL9sT6wY3dF1gH5jB0zN" + "'",
        "Authorization: Bearer " + "AbCdEfGhIjKlMnOpQrStUvWxYz0123456789",
    ]
    negatives = [
        "client_secret='YOUR_CLIENT_SECRET'",
        "access_token = '<redacted>'",
        "pattern = r'ghp_[A-Za-z0-9]{36}'",
        "-----BEGIN (?:RSA )?PRIVATE KEY-----",
    ]
    for sample in positives:
        if not list(scan_line(sample)):
            print("SECRET_SCAN_SELF_TEST_FAIL positive fixture missed", file=sys.stderr)
            return 2
    for sample in negatives:
        if list(scan_line(sample)):
            print("SECRET_SCAN_SELF_TEST_FAIL negative fixture flagged", file=sys.stderr)
            return 2
    print("SECRET_SCAN_SELF_TEST_PASS")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser()
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--tree", type=Path, help="scan a repository working tree")
    group.add_argument("--stdin", action="store_true", help="scan text from stdin")
    group.add_argument("--self-test", action="store_true", help="run scanner fixtures")
    parser.add_argument("--label", default="stdin", help="label used for stdin findings")
    args = parser.parse_args()

    if args.self_test:
        return self_test()
    if args.stdin:
        return emit(scan_text(sys.stdin, args.label))
    return emit(scan_tree(args.tree.resolve()))


if __name__ == "__main__":
    raise SystemExit(main())
