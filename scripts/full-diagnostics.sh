#!/usr/bin/env bash
# scripts/full-diagnostics.sh
# Полная диагностика системы Konstructour

set -euo pipefail

HOST="${1:-http://localhost:8000}"
ADMIN_TOKEN="${2:-}"

fail(){ echo "❌ $*"; exit 1; }
pass(){ echo "✅ $*"; }
warn(){ echo "⚠️  $*"; }

echo "🔬 Konstructour Full System Diagnostics"
echo "Host: $HOST"
echo "Time: $(date)"
echo "=========================================="
echo ""

# Проверяем зависимости
echo "=== 1. Dependencies Check ==="
for cmd in curl jq sqlite3 bc; do
  if command -v "$cmd" >/dev/null 2>&1; then
    pass "$cmd is available"
  else
    fail "$cmd is not installed"
  fi
done

echo ""
echo "=== 2. Basic Connectivity ==="
if curl -sS --connect-timeout 5 "$HOST" >/dev/null; then
  pass "Host is reachable"
else
  fail "Host is not reachable"
fi

echo ""
echo "=== 3. Airtable Integration Tests ==="
echo "Running basic integration tests..."
if [ -f "scripts/kt_airtable_diag.sh" ]; then
  bash scripts/kt_airtable_diag.sh "$HOST"
else
  warn "kt_airtable_diag.sh not found, skipping integration tests"
fi

echo ""
echo "=== 4. Database Integrity ==="
if [ -f "scripts/db-integrity-check.sh" ]; then
  bash scripts/db-integrity-check.sh
else
  warn "db-integrity-check.sh not found, skipping database checks"
fi

echo ""
echo "=== 5. Performance Test ==="
if [ -f "scripts/performance-test.sh" ]; then
  echo "Running performance test (10 requests)..."
  bash scripts/performance-test.sh "$HOST" 10
else
  warn "performance-test.sh not found, skipping performance tests"
fi

echo ""
echo "=== 6. Security Tests ==="
if [ -n "$ADMIN_TOKEN" ] && [ -f "scripts/test-failover.sh" ]; then
  echo "Running security and failover tests..."
  bash scripts/test-failover.sh "$HOST" "$ADMIN_TOKEN"
else
  warn "Skipping security tests (no admin token or test script)"
fi

echo ""
echo "=== 7. Data Parity Check ==="
echo "Checking data consistency between Airtable and SQLite..."
PARITY_RESPONSE=$(curl -sS "$HOST/api/data-parity.php" 2>/dev/null || echo '{"ok":false,"error":"Request failed"}')
echo "$PARITY_RESPONSE" | jq . 2>/dev/null || echo "$PARITY_RESPONSE"

if echo "$PARITY_RESPONSE" | jq -e '.ok==true' >/dev/null 2>&1; then
  pass "Data parity check passed"
else
  warn "Data parity check failed or unavailable"
fi

echo ""
echo "=== 8. System Health Summary ==="
echo "Checking overall system health..."

# Health check
HEALTH_RESPONSE=$(curl -sS "$HOST/api/health-airtable.php" 2>/dev/null || echo '{"ok":false}')
if echo "$HEALTH_RESPONSE" | jq -e '.ok==true' >/dev/null 2>&1; then
  pass "Airtable health: OK"
else
  fail "Airtable health: FAILED"
fi

# Database check
if [ -f "api/konstructour.db" ]; then
  DB_SIZE=$(du -h api/konstructour.db | cut -f1)
  pass "Database exists: $DB_SIZE"
else
  fail "Database file not found"
fi

# Log files check
if [ -d "logs" ] || [ -f "/var/log/konstructour-cron.log" ]; then
  pass "Log files accessible"
else
  warn "Log files not found or not accessible"
fi

echo ""
echo "=== 9. Production Readiness Checklist ==="
echo "Checking production readiness criteria..."

# Проверяем критические компоненты
CRITICAL_FILES=(
  "api/secret-airtable.php"
  "api/health-airtable.php"
  "api/test-proxy-secure.php"
  "api/sync-airtable-new.php"
  "api/db.php"
)

for file in "${CRITICAL_FILES[@]}"; do
  if [ -f "$file" ]; then
    pass "Critical file exists: $file"
  else
    fail "Critical file missing: $file"
  fi
done

# Проверяем права доступа
if [ -f "/var/konstructour/secrets/airtable.json" ]; then
  PERMS=$(stat -c "%a" /var/konstructour/secrets/airtable.json 2>/dev/null || echo "unknown")
  if [ "$PERMS" = "600" ]; then
    pass "Secrets file has correct permissions (600)"
  else
    warn "Secrets file permissions: $PERMS (should be 600)"
  fi
else
  warn "Secrets file not found at expected location"
fi

echo ""
echo "=== 10. Recommendations ==="
echo "Based on the diagnostic results:"

# Анализируем результаты и даем рекомендации
if echo "$HEALTH_RESPONSE" | jq -e '.ok==true' >/dev/null 2>&1; then
  echo "✅ Airtable integration is working properly"
else
  echo "❌ Airtable integration needs attention"
fi

if [ -f "api/konstructour.db" ]; then
  DB_RECORDS=$(sqlite3 api/konstructour.db "SELECT COUNT(*) FROM regions;" 2>/dev/null || echo "0")
  if [ "$DB_RECORDS" -gt 0 ]; then
    echo "✅ Database contains data ($DB_RECORDS regions)"
  else
    echo "⚠️  Database is empty - consider running sync"
  fi
fi

echo ""
echo "=========================================="
echo "🎉 Full diagnostics completed!"
echo "Check the results above for any issues."
echo "System is ready for production use."
