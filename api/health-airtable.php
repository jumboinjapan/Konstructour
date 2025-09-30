<?php
// api/health-airtable.php
require_once __DIR__ . '/secret-airtable.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function airtable_whoami_with_metrics($token) {
  $start = microtime(true);
  
  $ch = curl_init('https://api.airtable.com/v0/meta/whoami');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    CURLOPT_TIMEOUT => 10,
  ]);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $latency = round((microtime(true) - $start) * 1000, 2); // ms
  
  curl_close($ch);
  
  return [
    'ok' => $httpCode >= 200 && $httpCode < 300,
    'http_code' => $httpCode,
    'latency_ms' => $latency,
    'response' => $response
  ];
}

try {
  $startTime = microtime(true);
  
  // Проверяем наличие токенов
  $tokens = load_airtable_tokens();
  $hasCurrent = !empty($tokens['current']);
  $hasNext = !empty($tokens['next']);
  
  if (!$hasCurrent && !$hasNext) {
    echo json_encode([
      'ok' => false,
      'reason' => 'no_tokens',
      'message' => 'No Airtable tokens configured',
      'timestamp' => date('c'),
      'latency_ms' => round((microtime(true) - $startTime) * 1000, 2)
    ]);
    exit;
  }
  
  $auth = ['current' => false, 'next' => false];
  $metrics = ['current' => null, 'next' => null];
  
  // Проверяем whoami для current токена
  if ($hasCurrent) {
    $currentResult = airtable_whoami_with_metrics($tokens['current']);
    $auth['current'] = $currentResult['ok'];
    $metrics['current'] = [
      'latency_ms' => $currentResult['latency_ms'],
      'http_code' => $currentResult['http_code']
    ];
    
    if ($currentResult['ok']) {
      echo json_encode([
        'ok' => true,
        'reason' => 'current_working',
        'message' => 'Current token is working',
        'auth' => $auth,
        'metrics' => $metrics,
        'token_slot' => 'current',
        'timestamp' => date('c'),
        'latency_ms' => round((microtime(true) - $startTime) * 1000, 2)
      ]);
      exit;
    }
  }
  
  // Проверяем whoami для next токена
  if ($hasNext) {
    $nextResult = airtable_whoami_with_metrics($tokens['next']);
    $auth['next'] = $nextResult['ok'];
    $metrics['next'] = [
      'latency_ms' => $nextResult['latency_ms'],
      'http_code' => $nextResult['http_code']
    ];
    
    if ($nextResult['ok']) {
      // Автоматически промоутим next -> current
      store_airtable_token('current', $tokens['next']);
      store_airtable_token('next', null);
      
      error_log("[HEALTH-AIRTABLE] PROMOTE next->current");
      
      echo json_encode([
        'ok' => true,
        'reason' => 'next_promoted',
        'message' => 'Next token promoted to current',
        'auth' => $auth,
        'metrics' => $metrics,
        'token_slot' => 'current',
        'timestamp' => date('c'),
        'latency_ms' => round((microtime(true) - $startTime) * 1000, 2)
      ]);
      exit;
    }
  }
  
  // Ни один токен не работает
  error_log("[HEALTH-AIRTABLE] AUTH fail - all tokens invalid");
  
  echo json_encode([
    'ok' => false,
    'reason' => 'all_tokens_invalid',
    'message' => 'All Airtable tokens are invalid',
    'auth' => $auth,
    'metrics' => $metrics,
    'has_current' => $hasCurrent,
    'has_next' => $hasNext,
    'timestamp' => date('c'),
    'latency_ms' => round((microtime(true) - $startTime) * 1000, 2)
  ]);
  
} catch (Throwable $e) {
  error_log("[HEALTH-AIRTABLE] Error: " . $e->getMessage());
  
  echo json_encode([
    'ok' => false,
    'reason' => 'error',
    'message' => $e->getMessage(),
    'timestamp' => date('c'),
    'latency_ms' => round((microtime(true) - $startTime) * 1000, 2)
  ]);
  http_response_code(500);
}
?>
