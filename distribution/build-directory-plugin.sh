#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
OUT="$ROOT/artifacts"
ZIP="$OUT/partneropen-connector-directory-0.1.0.zip"
CHECKSUM="$ZIP.sha256"

mkdir -p "$OUT"
rm -f "$ZIP" "$CHECKSUM"
python3 "$ROOT/scripts/package_plugin.py" partneropen-connector "$ZIP" directory
python3 "$ROOT/scripts/verify_directory_package.py" "$ZIP"
sha256sum "$ZIP" > "$CHECKSUM"
printf '%s\n' "Built $ZIP (directory-safe link boundary; replace Contributors before WordPress.org submission)"
