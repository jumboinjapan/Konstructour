#!/usr/bin/env bash
# scripts/performance-test.sh
# Нагрузочное тестирование Airtable интеграции

set -euo pipefail

HOST="${1:-http://localhost}"
REQUESTS="${2:-30}"

fail(){ echo "❌ $*"; exit 1; }
pass(){ echo "✅ $*"; }

echo "⚡ Airtable Performance Test"
echo "Host: $HOST"
echo "Requests: $REQUESTS"
echo "Time: $(date)"
echo ""

# Массивы для сбора метрик
declare -a response_times
declare -a http_codes
declare -a latencies

echo "=== Running $REQUESTS sequential whoami requests ==="
for i in $(seq 1 $REQUESTS); do
  start_time=$(date +%s.%N)
  
  response=$(curl -sS -X POST "$HOST/api/test-proxy-secure.php?provider=airtable" \
    -H 'Content-Type: application/json' \
    -d '{"whoami":true}' \
    -w "%{http_code}" \
    -o /dev/null)
  
  end_time=$(date +%s.%N)
  duration=$(echo "$end_time - $start_time" | bc -l)
  response_time_ms=$(echo "$duration * 1000" | bc -l | cut -d. -f1)
  
  response_times+=($response_time_ms)
  http_codes+=($response)
  
  if [ "$response" = "200" ]; then
    echo -n "."
  else
    echo -n "X"
  fi
  
  # Небольшая пауза между запросами
  sleep 0.1
done

echo ""
echo ""

# Анализ результатов
echo "=== Performance Analysis ==="

# Подсчет успешных запросов
success_count=0
for code in "${http_codes[@]}"; do
  if [ "$code" = "200" ]; then
    ((success_count++))
  fi
done

success_rate=$(echo "scale=2; $success_count * 100 / $REQUESTS" | bc -l)
echo "Success rate: $success_count/$REQUESTS ($success_rate%)"

# Статистика времени ответа
if [ ${#response_times[@]} -gt 0 ]; then
  # Сортировка для медианы
  IFS=$'\n' sorted_times=($(sort -n <<<"${response_times[*]}"))
  unset IFS
  
  # Минимум, максимум, медиана
  min_time=${sorted_times[0]}
  max_time=${sorted_times[-1]}
  median_index=$(((${#sorted_times[@]} - 1) / 2))
  median_time=${sorted_times[$median_index]}
  
  # Среднее
  total_time=0
  for time in "${response_times[@]}"; do
    total_time=$(echo "$total_time + $time" | bc -l)
  done
  avg_time=$(echo "scale=2; $total_time / ${#response_times[@]}" | bc -l)
  
  echo "Response times (ms):"
  echo "  Min: $min_time"
  echo "  Max: $max_time"
  echo "  Avg: $avg_time"
  echo "  Median: $median_time"
  
  # Проверка стабильности (все времена в пределах 2x от медианы)
  stability_threshold=$(echo "$median_time * 2" | bc -l | cut -d. -f1)
  unstable_requests=0
  for time in "${response_times[@]}"; do
    if [ "$time" -gt "$stability_threshold" ]; then
      ((unstable_requests++))
    fi
  done
  
  if [ $unstable_requests -eq 0 ]; then
    pass "Response times are stable"
  else
    echo "⚠️  Warning: $unstable_requests requests had unstable response times"
  fi
fi

# Проверка HTTP кодов
echo ""
echo "HTTP Status Codes:"
for code in "${http_codes[@]}"; do
  echo -n "$code "
done
echo ""

# Проверка на rate limiting
rate_limited=0
for code in "${http_codes[@]}"; do
  if [ "$code" = "429" ]; then
    ((rate_limited++))
  fi
done

if [ $rate_limited -gt 0 ]; then
  echo "⚠️  Warning: $rate_limited requests were rate limited (429)"
else
  pass "No rate limiting detected"
fi

# Проверка на ошибки аутентификации
auth_errors=0
for code in "${http_codes[@]}"; do
  if [ "$code" = "401" ] || [ "$code" = "403" ]; then
    ((auth_errors++))
  fi
done

if [ $auth_errors -gt 0 ]; then
  fail "$auth_errors authentication errors detected"
else
  pass "No authentication errors"
fi

echo ""
echo "=== Load Test Summary ==="
echo "Total requests: $REQUESTS"
echo "Successful: $success_count"
echo "Failed: $((REQUESTS - success_count))"
echo "Success rate: $success_rate%"

if [ "$success_rate" = "100.00" ]; then
  pass "All requests successful"
else
  fail "Some requests failed"
fi

echo ""
echo "🎉 Performance test completed!"
