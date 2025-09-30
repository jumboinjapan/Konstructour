#!/bin/bash

echo "🔍 Тестируем Airtable API..."

# Базовый URL (замените на ваш домен)
BASE_URL="https://www.konstructour.com"
# Или для локального тестирования:
# BASE_URL="http://localhost:8000"

echo "📍 Базовый URL: $BASE_URL"

echo ""
echo "🔍 Тест 1: Health endpoint"
curl -s "$BASE_URL/api/health.php" | jq '.' 2>/dev/null || curl -s "$BASE_URL/api/health.php"

echo ""
echo "🔍 Тест 2: Server keys"
curl -s "$BASE_URL/api/test-proxy.php?provider=server_keys&_=$(date +%s)" | jq '.' 2>/dev/null || curl -s "$BASE_URL/api/test-proxy.php?provider=server_keys&_=$(date +%s)"

echo ""
echo "🔍 Тест 3: Airtable whoami (без токена)"
curl -s -X POST "$BASE_URL/api/test-proxy.php?provider=airtable&_=$(date +%s)" \
  -H "Content-Type: application/json" \
  -d '{"whoami": true}' | jq '.' 2>/dev/null || curl -s -X POST "$BASE_URL/api/test-proxy.php?provider=airtable&_=$(date +%s)" \
  -H "Content-Type: application/json" \
  -d '{"whoami": true}'

echo ""
echo "🔍 Тест 4: Airtable whoami (с placeholder токеном)"
curl -s -X POST "$BASE_URL/api/test-proxy.php?provider=airtable&_=$(date +%s)" \
  -H "Content-Type: application/json" \
  -d '{"whoami": true, "api_key": "PLACEHOLDER_FOR_REAL_API_KEY"}' | jq '.' 2>/dev/null || curl -s -X POST "$BASE_URL/api/test-proxy.php?provider=airtable&_=$(date +%s)" \
  -H "Content-Type: application/json" \
  -d '{"whoami": true, "api_key": "PLACEHOLDER_FOR_REAL_API_KEY"}'

echo ""
echo "🔍 Тест 5: Config endpoint"
curl -s "$BASE_URL/api/config.php" | jq '.' 2>/dev/null || curl -s "$BASE_URL/api/config.php"

echo ""
echo "✅ Тесты завершены"
