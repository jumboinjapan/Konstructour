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

# Show Actions URL and macOS notification if available
REMOTE_URL=$(git config --get remote.origin.url)
if [[ "$REMOTE_URL" =~ ^git@github.com:(.*)\.git$ ]]; then
  ACTIONS_URL="https://github.com/${BASH_REMATCH[1]}/actions"
elif [[ "$REMOTE_URL" =~ ^https://github.com/(.*)\.git$ ]]; then
  ACTIONS_URL="https://github.com/${BASH_REMATCH[1]}/actions"
else
  ACTIONS_URL="https://github.com"
fi
echo "Actions: $ACTIONS_URL"
if command -v osascript >/dev/null 2>&1; then
  osascript -e 'display notification "Pushed to origin/main" with title "Konstructour Sync" subtitle "GitHub Actions запущен"'
fi


