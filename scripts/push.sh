#!/usr/bin/env bash
set -euo pipefail

msg=${1:-"chore: quick push from Cursor"}

git add -A
if git diff --cached --quiet; then
  echo "Nothing to commit."
else
  git commit -m "$msg" || true
fi
git push origin main
echo "Pushed to origin/main."


