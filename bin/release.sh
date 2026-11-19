#!/usr/bin/env bash
# Usage: bin/release.sh 0.2.0
# Sets the version in nex-forms.php, commits, tags v0.2.0. Then: git push --follow-tags
set -euo pipefail
cd "$(dirname "$0")/.."
V="${1:-}"
[[ "$V" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Usage: bin/release.sh X.Y.Z"; exit 1; }
[ -z "$(git status --porcelain)" ] || { echo "Commit or stash your changes first."; exit 1; }
git rev-parse "v$V" >/dev/null 2>&1 && { echo "Tag v$V already exists."; exit 1; }
sed -i.bak -E "s/^( \* Version:[[:space:]]+).*/\1$V/; s/(define\( 'NEXFORMS_VERSION', ')[^']+(')/\1$V\2/" nex-forms.php
rm -f nex-forms.php.bak
grep -qE "^ \* Version:[[:space:]]+$V$" nex-forms.php && grep -q "'NEXFORMS_VERSION', '$V'" nex-forms.php || { echo "Version replace failed"; git checkout nex-forms.php; exit 1; }
git commit -qam "Release $V"
git tag -a "v$V" -m "NexForms $V"
echo "Tagged v$V. Publish with: git push --follow-tags"
