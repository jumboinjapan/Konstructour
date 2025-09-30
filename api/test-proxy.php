<?php
// Test proxy for Airtable API
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/_airtable-common.php';

try {
  $provider = $_GET['provider'] ?? '';
  $baseId = $_GET['base_id'] ?? '';
  
  if ($provider !== 'airtable') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unsupported provider'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  
  if (!$baseId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing base_id parameter'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  
  $cfg = air_cfg();
  
  // Проверяем, что base_id совпадает с конфигурацией
  if ($baseId !== $cfg['base_id']) {
    http_response_code(400);
    echo json_encode([
      'ok' => false, 
      'error' => 'Base ID mismatch',
      'expected' => $cfg['base_id'],
      'received' => $baseId
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }
  
  // Тестируем подключение к Airtable
  [$code, $out, $err, $url] = air_call('GET', '', null, ['pageSize' => 1]);
  
  if ($out === false || $err) {
    http_response_code(500);
    echo json_encode([
      'ok' => false,
      'error' => 'cURL failed',
      'curl_error' => $err,
      'url' => $url
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }
  
  $json = json_decode($out, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode([
      'ok' => false,
      'error' => 'Invalid JSON response',
      'json_error' => json_last_error_msg(),
      'raw_output' => $out
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }
  
  if ($code >= 400) {
    http_response_code(500);
    echo json_encode([
      'ok' => false,
      'error' => 'Airtable API error',
      'code' => $code,
      'details' => $json ?: $out
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }
  
  // Успешный ответ
  echo json_encode([
    'ok' => true,
    'message' => 'Airtable proxy test successful',
    'base_id' => $cfg['base_id'],
    'table_id' => $cfg['table_id'],
    'records_count' => count($json['records'] ?? []),
    'sample_record' => $json['records'][0] ?? null
  ], JSON_UNESCAPED_UNICODE);
  
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine()
  ], JSON_UNESCAPED_UNICODE);
}
?>
