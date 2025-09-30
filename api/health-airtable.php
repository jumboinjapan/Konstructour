<?php
// api/health-airtable.php
require_once __DIR__ . '/secret-airtable.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
  // Проверяем наличие токенов
  $tokens = load_airtable_tokens();
  $hasCurrent = !empty($tokens['current']);
  $hasNext = !empty($tokens['next']);
  
  if (!$hasCurrent && !$hasNext) {
    echo json_encode([
      'ok' => false,
      'reason' => 'no_tokens',
      'message' => 'No Airtable tokens configured'
    ]);
    exit;
  }
  
  // Проверяем whoami для current токена
  if ($hasCurrent) {
    $currentOk = airtable_whoami_check($tokens['current']);
    if ($currentOk) {
      echo json_encode([
        'ok' => true,
        'reason' => 'current_working',
        'message' => 'Current token is working',
        'token_slot' => 'current'
      ]);
      exit;
    }
  }
  
  // Проверяем whoami для next токена
  if ($hasNext) {
    $nextOk = airtable_whoami_check($tokens['next']);
    if ($nextOk) {
      // Автоматически промоутим next -> current
      store_airtable_token('current', $tokens['next']);
      store_airtable_token('next', null);
      
      echo json_encode([
        'ok' => true,
        'reason' => 'next_promoted',
        'message' => 'Next token promoted to current',
        'token_slot' => 'current'
      ]);
      exit;
    }
  }
  
  // Ни один токен не работает
  echo json_encode([
    'ok' => false,
    'reason' => 'all_tokens_invalid',
    'message' => 'All Airtable tokens are invalid',
    'has_current' => $hasCurrent,
    'has_next' => $hasNext
  ]);
  
} catch (Throwable $e) {
  error_log("[HEALTH-AIRTABLE] Error: " . $e->getMessage());
  
  echo json_encode([
    'ok' => false,
    'reason' => 'error',
    'message' => $e->getMessage()
  ]);
  http_response_code(500);
}
?>
