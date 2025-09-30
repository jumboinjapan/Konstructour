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
  $url = 'https://www.konstructour.com/api/sync-airtable-new.php';
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 300, // 5 минут
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

// Основная логика
try {
  log_message("Starting cron sync check");
  
  // Проверяем health
  $health = check_airtable_health();
  
  if (!$health['ok']) {
    log_message("Health check failed: " . ($health['reason'] ?? 'unknown'));
    
    // Если проблема с токенами - не запускаем синхронизацию
    if (in_array($health['reason'], ['no_tokens', 'all_tokens_invalid'])) {
      log_message("Skipping sync due to token issues");
      exit(1);
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
  } else {
    log_message("Sync failed: " . ($syncResult['error'] ?? 'unknown error'));
    exit(1);
  }
  
} catch (Throwable $e) {
  log_message("Cron error: " . $e->getMessage());
  exit(1);
}
?>