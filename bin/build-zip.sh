#!/usr/bin/env bash
# Build an installable zip in dist/ (excludes tests, bin and git files).
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION=$(grep -m1 "Version:" nex-forms.php | awk '{print $NF}')
mkdir -p dist
rm -f "dist/nex-forms-$VERSION.zip"
git archive --format=zip --prefix=nex-forms/ -o "dist/nex-forms-$VERSION.zip" HEAD -- . ':!tests' ':!bin' ':!.gitignore' ':!README.md'
echo "dist/nex-forms-$VERSION.zip"
