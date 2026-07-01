#!/usr/bin/env bash
# Sync the dev build from the prod build (chrome-extension/ → chrome-extension-dev/).
#
# Why this script exists:
#   Most dev files are byte-identical to prod and used to be copied by hand with `cp`.
#   Two shared files (`background.js`, `popup.html`) additionally need dev-only lines
#   that load the "Send to Developer" reporter. A plain `cp` silently drops those lines,
#   which breaks the reporter (popup shows "Failed: Failed" because no service-worker
#   handler answers the SEND_PAGE_REPORT message). This script re-injects them, so the
#   dev build never ends up half-wired again.
#
# NOT synced (intentionally different — never overwrite):
#   manifest.json  — dev name, <all_urls>, extra permissions
#   auth.js        — dev API_BASE (http://booking.localhost/...)
#   dev-reporter.* — dev-only files that have no prod counterpart
#
# Usage:  bash chrome-extension-dev/sync-from-prod.sh   (run from repo root or this dir)

set -euo pipefail

DEV_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROD_DIR="$(cd "$DEV_DIR/../chrome-extension" && pwd)"

# Files identical in both builds — safe to copy verbatim.
SHARED_FILES=(
  background.js
  content.js
  content.css
  popup.js
  popup.css
  popup.html
  bookings.js
  bookings.css
  bookings.html
  manual-booking.js
  manual-booking.css
  manual-booking.html
  booking-modal.js
)

echo "Syncing prod → dev …"
for f in "${SHARED_FILES[@]}"; do
  cp "$PROD_DIR/$f" "$DEV_DIR/$f"
  echo "  copied $f"
done

# parsers/ and icons/ are directories of shared files.
cp -r "$PROD_DIR/parsers/." "$DEV_DIR/parsers/"
cp -r "$PROD_DIR/icons/."   "$DEV_DIR/icons/"
echo "  copied parsers/ and icons/"

# --- Re-inject dev-only lines that cp just overwrote (idempotent) ---

# 1) background.js: load the SEND_PAGE_REPORT service-worker handler.
if ! grep -q 'dev-reporter-bg.js' "$DEV_DIR/background.js"; then
  printf '\n// Dev build only: register the SEND_PAGE_REPORT service-worker handler.\nimportScripts("dev-reporter-bg.js");\n' >> "$DEV_DIR/background.js"
  echo "  re-injected importScripts(dev-reporter-bg.js) into background.js"
fi

# 2) popup.html: load the DEV badge + "Send to Developer" button and its styles.
if ! grep -q 'dev-reporter.css' "$DEV_DIR/popup.html"; then
  sed -i 's#\(\s*\)<link rel="stylesheet" href="popup.css">#&\n\1<link rel="stylesheet" href="dev-reporter.css">#' "$DEV_DIR/popup.html"
  echo "  re-injected dev-reporter.css link into popup.html"
fi
if ! grep -q 'dev-reporter-popup.js' "$DEV_DIR/popup.html"; then
  sed -i 's#\(\s*\)<script src="popup.js"></script>#&\n\1<script src="dev-reporter-popup.js"></script>#' "$DEV_DIR/popup.html"
  echo "  re-injected dev-reporter-popup.js script into popup.html"
fi

echo "Done. Reload the dev extension at chrome://extensions."
