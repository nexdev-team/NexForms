#!/usr/bin/env bash
# Local build of the same zip the Release workflow publishes (from the last commit).
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist
git archive --format=zip --prefix=nex-forms/ -o dist/nex-forms.zip HEAD
echo "dist/nex-forms.zip"
