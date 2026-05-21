#!/usr/bin/env bash
# Build production ZIP for Chrome Web Store upload.
# Run from the chrome-extension/ directory or the repo root.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

VERSION=$(grep -oP '"version":\s*"\K[^"]+' manifest.json)
OUT="booking-saver-v${VERSION}.zip"

rm -f "$OUT"

zip -r "$OUT" \
  manifest.json \
  auth.js \
  background.js \
  content.js \
  content.css \
  popup.html \
  popup.js \
  popup.css \
  parsers/ \
  icons/

echo "Built: $SCRIPT_DIR/$OUT  (version $VERSION)"
