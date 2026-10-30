#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$ROOT/plugin/achuthan-jobs"
command -v php >/dev/null || { echo "Missing php CLI" >&2; exit 2; }
command -v node >/dev/null || { echo "Missing node CLI" >&2; exit 2; }
find "$SRC" -type f -name '*.php' -print0 | while IFS= read -r -d '' f; do php -l "$f"; done
find "$SRC" -type f -name '*.js' -print0 | while IFS= read -r -d '' f; do node --check "$f"; done
php "$SRC/tests/security-smoke.php"
python3 "$ROOT/scripts/check-branding.py"
echo "Private Achuthan Jobs source verification passed (mocked tests only)."
