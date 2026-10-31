#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$ROOT/plugin/achuthan-jobs"
OUT="$ROOT/dist/achuthan-jobs-v0.13.0-private.zip"
mkdir -p "$ROOT/dist"
command -v python3 >/dev/null || { echo "Python3 is needed for packaging" >&2; exit 2; }
python3 - "$SRC" "$OUT" <<'PYBUILD'
import sys, zipfile
from pathlib import Path
src=Path(sys.argv[1]); out=Path(sys.argv[2]); root=src.parent
needed=['achuthan-jobs.php','assets/front.js','includes/class-achuthan-questions.php']
for rel in needed:
    if not (src/rel).exists(): raise SystemExit(f'Missing {rel}')
with zipfile.ZipFile(out,'w',zipfile.ZIP_DEFLATED) as z:
    for f in sorted(src.rglob('*')):
        if not f.is_file() or 'tests' in f.relative_to(src).parts: continue
        z.write(f, arcname=(Path('achuthan-jobs')/f.relative_to(src)).as_posix())
with zipfile.ZipFile(out) as z:
    assert z.testzip() is None, 'ZIP integrity failed'
    assert 'achuthan-jobs/achuthan-jobs.php' in z.namelist()
print(f'Created {out} ({out.stat().st_size} bytes)')
PYBUILD
