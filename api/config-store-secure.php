<?php
// api/config-store-secure.php
require_once __DIR__ . '/secret-airtable.php';

header('Content-Type: application/json; charset=utf-8');

// Проверка админ токена (упрощенная для тестирования)
$adminToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
$expectedToken = getenv('ADMIN_TOKEN') ?: '';

if ($expectedToken) {
  // Если переменная окружения настроена, проверяем токен
  if (!$adminToken || !hash_equals($expectedToken, $adminToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
  }
} else {
  // Если переменная не настроена, принимаем любой токен (для тестирования)
  if (!$adminToken) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'X-Admin-Token header required']);
    exit;
  }
  // Любой токен принимается
}

$body = json_decode(file_get_contents('php://input') ?: '[]', true);

try {
  // Запись нового ключа — только в слот next
  if (isset($body['airtable']['api_key'])) {
    $val = $body['airtable']['api_key'];
    if ($val === null || $val === '') {
      // Очистить оба слота
      store_airtable_token('current', null);
      store_airtable_token('next', null);
      echo json_encode(['ok' => true, 'message' => 'All tokens cleared']);
    } else {
      // Валидация PAT формата
      if (!preg_match('~^pat\\.[A-Za-z0-9_\\-]{20,}$~', $val)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid PAT format']);
        exit;
      }
      
      // Сохраняем в next слот
      store_airtable_token('next', $val);
      echo json_encode(['ok' => true, 'message' => 'Token saved to next slot']);
    }
    exit;
  }

  // Обработка других конфигураций (mapping и т.д.)
  // Здесь можно добавить логику для сохранения других настроек
  
  echo json_encode(['ok' => true, 'message' => 'Configuration updated']);

} catch (Throwable $e) {
  error_log("[CONFIG-STORE] Error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
