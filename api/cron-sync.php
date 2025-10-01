<?php
// api/cron-sync.php
require_once __DIR__ . '/secret-airtable.php';

// Логирование
function log_message($message) {
  $timestamp = date('Y-m-d H:i:s');
  error_log("[CRON-SYNC] $timestamp: $message");
}

// Проверка health check
function check_airtable_health() {
  $url = 'https://www.konstructour.com/api/health-airtable.php';
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
  ]);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  
  if ($httpCode !== 200) {
    return ['ok' => false, 'reason' => 'http_error', 'code' => $httpCode];
  }
  
  $data = json_decode($response, true);
  return $data;
}

// Выполнение синхронизации
function run_sync() {
  // Вызываем синхронизацию напрямую вместо HTTP запроса
  ob_start();
  
  try {
    // Подключаем и запускаем синхронизацию
    require_once __DIR__ . '/sync-airtable-clean.php';
    $output = ob_get_clean();
    
    // Парсим вывод для получения статистики
    $regions = 0;
    $cities = 0;
    $pois = 0;
    
    if (preg_match_all('/✅ (REG-\d+)/', $output, $matches)) {
      $regions = count($matches[1]);
    }
    if (preg_match_all('/✅ (CTY-\d+|LOC-\d+)/', $output, $matches)) {
      $cities = count($matches[1]);
    }
    if (preg_match_all('/✅ (POI-\d+)/', $output, $matches)) {
      $pois = count($matches[1]);
    }
    
    return [
      'ok' => true,
      'regions' => $regions,
      'cities' => $cities,
      'pois' => $pois,
      'output' => $output
    ];
    
  } catch (Exception $e) {
    ob_end_clean();
    return [
      'ok' => false,
      'error' => $e->getMessage(),
      'reason' => 'sync_error'
    ];
  }
}

// Основная логика
try {
  log_message("Starting cron sync check");
  
  // Проверяем наличие секретного файла
  $secretPath = airtable_secret_path();
  if (!file_exists($secretPath)) {
    log_message("CRITICAL: Secret file missing: $secretPath");
    exit(2); // Код 2 для file_missing
  }
  
  if (!is_readable($secretPath)) {
    log_message("CRITICAL: Secret file not readable: $secretPath");
    exit(3); // Код 3 для permission_denied
  }
  
  // Проверяем health
  $health = check_airtable_health();
  
  if (!$health['ok']) {
    log_message("Health check failed: " . ($health['reason'] ?? 'unknown'));
    
    // Критические ошибки - не запускаем синхронизацию
    if (in_array($health['reason'], ['file_missing', 'permission_denied', 'token_missing', 'all_tokens_invalid'])) {
      log_message("Skipping sync due to critical issues: " . $health['reason']);
      exit(4); // Код 4 для critical issues
    }
    
    // Для других ошибок - пробуем синхронизацию
    log_message("Health check failed but attempting sync anyway");
  } else {
    log_message("Health check passed: " . ($health['reason'] ?? 'unknown'));
  }
  
  // Запускаем синхронизацию
  log_message("Starting sync");
  $syncResult = run_sync();
  
  if ($syncResult['ok']) {
    $regions = $syncResult['regions'] ?? 0;
    $cities = $syncResult['cities'] ?? 0;
    $pois = $syncResult['pois'] ?? 0;
    log_message("Sync completed successfully: $regions regions, $cities cities, $pois pois");
    
    // Возвращаем JSON для дашборда
    header('Content-Type: application/json');
    echo json_encode([
      'ok' => true,
      'regions' => $regions,
      'cities' => $cities,
      'pois' => $pois,
      'message' => 'Sync completed successfully'
    ]);
  } else {
    log_message("Sync failed: " . ($syncResult['error'] ?? 'unknown error'));
    
    // Возвращаем JSON ошибки для дашборда
    header('Content-Type: application/json');
    echo json_encode([
      'ok' => false,
      'error' => $syncResult['error'] ?? 'Sync failed',
      'message' => 'Sync failed'
    ]);
    exit(1);
  }
  
} catch (Throwable $e) {
  log_message("Cron error: " . $e->getMessage());
  exit(1);
}
?>