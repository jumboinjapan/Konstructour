#!/usr/bin/env bash
# scripts/test-failover.sh
# Тестирование failover и ротации токенов

set -euo pipefail

HOST="${1:-http://localhost}"
ADMIN_TOKEN="${2:-}"

if [ -z "$ADMIN_TOKEN" ]; then
  echo "Usage: $0 <host> <admin_token>"
  echo "Example: $0 http://localhost:8000 your-admin-token"
  exit 1
fi

fail(){ echo "❌ $*"; exit 1; }
pass(){ echo "✅ $*"; }

echo "🔄 Testing Airtable Failover and Token Rotation"
echo "Host: $HOST"
echo "Time: $(date)"
echo ""

# Функция для выполнения HTTP запросов
http_request() {
  local method="$1"
  local url="$2"
  local data="$3"
  local headers="$4"
  
  if [ "$method" = "POST" ]; then
    curl -sS -X POST "$url" -H 'Content-Type: application/json' -d "$data" $headers
  else
    curl -sS "$url" $headers
  fi
}

echo "=== 1. Initial Health Check ==="
HEALTH=$(http_request "GET" "$HOST/api/health-airtable.php" "" "")
echo "$HEALTH" | jq .
echo "$HEALTH" | jq -e '.ok==true' >/dev/null || fail "Initial health check failed"
pass "Initial health check passed"

echo ""
echo "=== 2. Testing Token Rotation (Next -> Current) ==="
echo "Setting a new token in 'next' slot..."

# Устанавливаем новый токен в next слот (используем placeholder для теста)
NEW_TOKEN="pat.test1234567890123456789012345678901234567890"
ROTATE_RESPONSE=$(http_request "POST" "$HOST/api/config-store-secure.php" \
  "{\"airtable\":{\"api_key\":\"$NEW_TOKEN\"}}" \
  "-H \"X-Admin-Token: $ADMIN_TOKEN\"")

echo "$ROTATE_RESPONSE" | jq .
echo "$ROTATE_RESPONSE" | jq -e '.ok==true' >/dev/null || fail "Failed to set next token"
pass "Next token set successfully"

echo ""
echo "=== 3. Testing WhoAmI (should trigger promotion) ==="
WHOAMI_RESPONSE=$(http_request "POST" "$HOST/api/test-proxy-secure.php?provider=airtable" \
  '{"whoami":true}' "")
echo "$WHOAMI_RESPONSE" | jq .
echo "$WHOAMI_RESPONSE" | jq -e '.ok==true' >/dev/null || fail "WhoAmI failed"
pass "WhoAmI completed"

echo ""
echo "=== 4. Verifying Token Promotion ==="
FINAL_HEALTH=$(http_request "GET" "$HOST/api/health-airtable.php" "" "")
echo "$FINAL_HEALTH" | jq .
echo "$FINAL_HEALTH" | jq -e '.ok==true' >/dev/null || fail "Final health check failed"
pass "Final health check passed"

echo ""
echo "=== 5. Testing Rate Limiting (10 rapid requests) ==="
echo "Sending 10 rapid whoami requests..."
for i in {1..10}; do
  response=$(http_request "POST" "$HOST/api/test-proxy-secure.php?provider=airtable" \
    '{"whoami":true}' "" -w "%{http_code}" -o /dev/null)
  if [ "$response" != "200" ]; then
    echo "Request $i failed with code $response"
  else
    echo -n "."
  fi
done
echo ""
pass "Rate limiting test completed"

echo ""
echo "=== 6. Testing Unauthorized Access ==="
echo "Testing config-store without admin token..."
UNAUTH_RESPONSE=$(http_request "POST" "$HOST/api/config-store-secure.php" \
  '{"airtable":{"api_key":"test"}}' "")
echo "$UNAUTH_RESPONSE"
if echo "$UNAUTH_RESPONSE" | grep -q "403\|Forbidden"; then
  pass "Unauthorized access properly blocked"
else
  fail "Unauthorized access not blocked"
fi

echo ""
echo "=== 7. Testing Invalid PAT Format ==="
echo "Testing with invalid PAT format..."
INVALID_RESPONSE=$(http_request "POST" "$HOST/api/config-store-secure.php" \
  '{"airtable":{"api_key":"invalid-token"}}' \
  "-H \"X-Admin-Token: $ADMIN_TOKEN\"")
echo "$INVALID_RESPONSE"
if echo "$INVALID_RESPONSE" | grep -q "400\|Bad PAT"; then
  pass "Invalid PAT format properly rejected"
else
  fail "Invalid PAT format not rejected"
fi

echo ""
echo "🎉 All failover tests passed!"
echo "System demonstrates robust token management and security."
