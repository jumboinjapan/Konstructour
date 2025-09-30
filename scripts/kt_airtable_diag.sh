#!/usr/bin/env bash
set -euo pipefail

HOST="${1:-http://localhost}"
fail(){ echo "❌ $*"; exit 1; }
pass(){ echo "✅ $*"; }

# Проверяем зависимости
jq -V >/dev/null 2>&1 || fail "jq not installed"
curl -V >/dev/null 2>&1 || fail "curl not installed"
sqlite3 -version >/dev/null 2>&1 || fail "sqlite3 not installed"

echo "🔍 Konstructour Airtable Diagnostics"
echo "Host: $HOST"
echo "Time: $(date)"
echo ""

echo "=== 1. Health Check ==="
H=$(curl -sS "$HOST/api/health-airtable.php") || fail "health fetch failed"
echo "$H" | jq .
echo "$H" | jq -e '.ok==true' >/dev/null || fail "health not ok"
pass "health ok"

echo ""
echo "=== 2. WhoAmI Test ==="
W=$(curl -sS -X POST "$HOST/api/test-proxy-secure.php?provider=airtable" \
  -H 'Content-Type: application/json' -d '{"whoami":true}') || fail "whoami fetch failed"
echo "$W" | jq .
echo "$W" | jq -e '.ok==true and .auth==true' >/dev/null || fail "whoami not ok"
pass "whoami ok"

echo ""
echo "=== 3. Sync Test ==="
S=$(curl -sS "$HOST/api/cron-sync.php") || fail "sync fetch failed"
echo "$S" | jq .
echo "$S" | jq -e '.ok==true' >/dev/null || fail "sync not ok"
pass "sync ok"

echo ""
echo "=== 4. SQLite Database Check ==="
if [ -f "api/konstructour.db" ]; then
  echo "Database exists: $(ls -lh api/konstructour.db | awk '{print $5}')"
  
  echo "Record counts:"
  sqlite3 api/konstructour.db 'SELECT "regions",count(*) FROM regions UNION ALL SELECT "cities",count(*) FROM cities UNION ALL SELECT "pois",count(*) FROM pois;'
  
  echo ""
  echo "Foreign Key integrity:"
  sqlite3 api/konstructour.db "
  SELECT 'city_orphans', COUNT(*) FROM cities  c LEFT JOIN regions r ON c.region_id=r.id WHERE r.id IS NULL;
  SELECT 'poi_orphans_city', COUNT(*) FROM pois p LEFT JOIN cities c ON p.city_id=c.id WHERE p.city_id IS NOT NULL AND c.id IS NULL;
  SELECT 'poi_orphans_region', COUNT(*) FROM pois p LEFT JOIN regions r ON p.region_id=r.id WHERE p.region_id IS NOT NULL AND r.id IS NULL;
  "
  
  echo ""
  echo "SQLite PRAGMA settings:"
  sqlite3 api/konstructour.db 'PRAGMA foreign_keys; PRAGMA journal_mode; PRAGMA synchronous;'
  
  pass "database integrity checked"
else
  fail "database file not found"
fi

echo ""
echo "=== 5. Security Check ==="
echo "Checking server keys endpoint:"
curl -sS "$HOST/api/test-proxy-secure.php?provider=server_keys" | jq .

echo ""
echo "Testing unauthorized access (should fail):"
if curl -sS -X POST "$HOST/api/config-store-secure.php" -d '{}' 2>/dev/null | grep -q "403\|Forbidden"; then
  pass "unauthorized access properly blocked"
else
  fail "unauthorized access not blocked"
fi

echo ""
echo "=== 6. Load Test (10 requests) ==="
echo "Testing whoami stability:"
for i in {1..10}; do
  response=$(curl -sS -X POST "$HOST/api/test-proxy-secure.php?provider=airtable" \
    -H 'Content-Type: application/json' -d '{"whoami":true}' -w "%{http_code}" -o /dev/null)
  if [ "$response" != "200" ]; then
    fail "whoami request $i failed with code $response"
  fi
  echo -n "."
done
echo ""
pass "load test passed"

echo ""
echo "🎉 All diagnostic checks passed!"
echo "System is ready for production use."
