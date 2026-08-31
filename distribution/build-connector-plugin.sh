#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
OUT="$ROOT/artifacts"
ZIP="$OUT/partneropen-connector-0.1.0.zip"
CHECKSUM="$ZIP.sha256"

mkdir -p "$OUT"
rm -f "$ZIP" "$CHECKSUM"
python3 "$ROOT/scripts/package_plugin.py" partneropen-connector "$ZIP"
sha256sum "$ZIP" > "$CHECKSUM"
printf '%s\n' "Built $ZIP"
