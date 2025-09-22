#!/usr/bin/env bash
set -euo pipefail

# Placeholder sync hook. Here you can run your local data sync commands.
# Example: node tools/sync.js or curl localhost:3000/sync
echo "Running local sync (placeholder)..."
sleep 1

# Auto-push after sync
msg=${1:-"chore: sync data and push"}
bash scripts/push.sh "$msg"


