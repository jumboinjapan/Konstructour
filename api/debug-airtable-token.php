<?php
// Диагностика токена Airtable
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/_airtable-common.php';

try {
  $cfg = air_cfg();
  
  // Информация о токене (безопасно)
  $tokenPreview = substr($cfg['api_key'], 0, 3) . '...' . substr($cfg['api_key'], -6);
  $tokenLength = strlen($cfg['api_key']);
  
  // Проверка формата токена
  $isValidFormat = preg_match('/^pat[^\s]{20,}$/', $cfg['api_key']);
  
  // Тест whoami
  [$code, $out, $err, $url] = air_call('GET', 'meta/whoami');
  
  $whoamiSuccess = ($code >= 200 && $code < 300);
  $whoamiData = null;
  if ($whoamiSuccess) {
    $whoamiData = json_decode($out, true);
  }
  
  // Тест доступа к базе данных
  [$code2, $out2, $err2, $url2] = air_call('GET', '', null, ['pageSize' => 1]);
  $baseAccessSuccess = ($code2 >= 200 && $code2 < 300);
  $baseData = null;
  if ($baseAccessSuccess) {
    $baseData = json_decode($out2, true);
  }
  
  echo json_encode([
    'ok' => $whoamiSuccess && $baseAccessSuccess,
    'token_info' => [
      'preview' => $tokenPreview,
      'length' => $tokenLength,
      'valid_format' => $isValidFormat,
    ],
    'whoami_test' => [
      'success' => $whoamiSuccess,
      'code' => $code,
      'url' => $url,
      'error' => $err,
      'data' => $whoamiData,
    ],
    'base_access_test' => [
      'success' => $baseAccessSuccess,
      'code' => $code2,
      'url' => $url2,
      'error' => $err2,
      'records_count' => $baseData ? count($baseData['records'] ?? []) : 0,
    ],
    'config' => [
      'base_id' => $cfg['base_id'],
      'table_id' => $cfg['table_id'],
    ]
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine(),
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
