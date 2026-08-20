#!/usr/bin/env bash
# Build a production ZIP for one extension edition.
#
#   bash build.sh --edition=ru
#   bash build.sh --edition=intl
#   bash build.sh --all
#
# Editions differ ONLY in manifest.json (name, description, host_permissions,
# content_scripts[].matches). Every .js/.css/.html file is shipped identically to
# both builds — see .docs/2ext.md §5.3. That is deliberate: the RU update rides out
# to ~1000 installed devices and must not carry logic changes.
#
# Output goes to ../pub_ext/ (gitignored), next to chrome-extension_sample — the
# unpacked copy of the currently published release, kept there to diff against:
#   pub_ext/<edition>/                          loadable unpacked
#   pub_ext/booking-saver-<edition>-v<ver>.zip  upload this to the Chrome Web Store

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Files that build the package but never ship inside it.
EXCLUDE=(build.sh build-manifest.php manifest.base.json manifest.json editions dist)

# Packages land outside the source tree so they never get picked up as extension
# files, and sit beside the published-release sample for eyeballing diffs.
PUB_DIR="../pub_ext"

usage() { echo "usage: bash build.sh --edition=ru|intl | --all [--no-check]" >&2; exit 2; }

# Cross-checks editions/<edition>.json against the parsers in the database, so a
# portal can't end up shipped-but-unparsed (or parsed-but-unshipped). Needs a
# reachable DB; --no-check skips it when building without one.
check_manifest() {
  local edition="$1"
  [[ "$NO_CHECK" == "1" ]] && return 0

  if ! php ../artisan extension:check-manifest --edition="$edition"; then
    echo "  build.sh: aborted — fix the mismatch above, or pass --no-check" >&2
    exit 1
  fi
}

build_one() {
  local edition="$1"

  [[ -f "editions/${edition}.json" ]] || { echo "unknown edition: ${edition}" >&2; exit 2; }

  local version out_dir zip_name
  version=$(php -r 'echo json_decode(file_get_contents("manifest.base.json"), true)["version"];')
  out_dir="${PUB_DIR}/${edition}"
  zip_name="booking-saver-${edition}-v${version}.zip"

  mkdir -p "$PUB_DIR"

  check_manifest "$edition"

  rm -rf "$out_dir"
  mkdir -p "$out_dir"

  # Copy everything, then drop the build-only files. Copying the whole directory
  # (rather than listing files) is what keeps new assets from being silently left
  # out of the archive, which is how booking-modal.js went missing before.
  local rsync_args=(-a --exclude '*.zip')
  for e in "${EXCLUDE[@]}"; do rsync_args+=(--exclude "$e"); done
  rsync "${rsync_args[@]}" ./ "$out_dir/"

  php build-manifest.php "$edition" "$out_dir/manifest.json"

  # Every file referenced by the manifest must actually exist in the package.
  php -r '
    $dir = $argv[1];
    $m = json_decode(file_get_contents("$dir/manifest.json"), true);
    $refs = array_merge(
      [$m["background"]["service_worker"], $m["action"]["default_popup"]],
      $m["content_scripts"][0]["js"], $m["content_scripts"][0]["css"],
      array_values($m["icons"])
    );
    $missing = array_values(array_filter($refs, fn($f) => !is_file("$dir/$f")));
    if ($missing) { fwrite(STDERR, "missing from package: " . implode(", ", $missing) . "\n"); exit(1); }
  ' "$out_dir"

  rm -f "${PUB_DIR}/${zip_name}"
  (cd "$out_dir" && zip -rq "../${zip_name}" .)

  local sites
  sites=$(php -r 'echo count(json_decode(file_get_contents($argv[1]), true)["sites"]);' "editions/${edition}.json")
  echo "  ${edition}: pub_ext/${zip_name}  (v${version}, ${sites} site patterns)"
}

NO_CHECK=0
ARGS=()
for arg in "$@"; do
  case "$arg" in
    --no-check) NO_CHECK=1 ;;
    *) ARGS+=("$arg") ;;
  esac
done
set -- "${ARGS[@]+"${ARGS[@]}"}"

[[ $# -ge 1 ]] || usage

case "${1:-}" in
  --all)
    echo "Building all editions…"
    for f in editions/*.json; do
      build_one "$(basename "$f" .json)"
    done
    ;;
  --edition=*)
    echo "Building…"
    build_one "${1#--edition=}"
    ;;
  *) usage ;;
esac

echo "Done."
