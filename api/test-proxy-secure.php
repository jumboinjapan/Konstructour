<?php
// api/test-proxy-secure.php
require_once __DIR__ . '/secret-airtable.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// CORS: mirror origin; если нет — разрешаем текущий хост (http/https)
if (isset($_SERVER['HTTP_ORIGIN'])){
  header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
  header('Vary: Origin');
} else {
  $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on') ? 'https' : 'http';
  header('Access-Control-Allow-Origin: '.$scheme.'://'.$_SERVER['HTTP_HOST']);
}
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization');
  http_response_code(204);
  exit;
}

$provider = $_GET['provider'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

function body_json() {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function respond($ok, $data = [], $code = 200) {
  http_response_code($code);
  echo json_encode(['ok'=>$ok] + $data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

function airtable_request_with_retry($url, $token, $maxRetries = 3) {
  $retryDelays = [1, 2, 4]; // секунды
  
  for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
      CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Если успех - возвращаем результат
    if ($httpCode >= 200 && $httpCode < 300) {
      return [
        'ok' => true,
        'http_code' => $httpCode,
        'response' => $response
      ];
    }
    
    // Если 429 (rate limit) - ждем и повторяем
    if ($httpCode === 429) {
      $delay = $retryDelays[$attempt] ?? 8;
      error_log("[TEST-PROXY-AIRTABLE] Rate limited, retrying in {$delay}s (attempt " . ($attempt + 1) . ")");
      sleep($delay);
      continue;
    }
    
    // Если 401/403 - не повторяем
    if ($httpCode === 401 || $httpCode === 403) {
      error_log("[TEST-PROXY-AIRTABLE] Auth failed: HTTP $httpCode");
      return [
        'ok' => false,
        'http_code' => $httpCode,
        'response' => $response
      ];
    }
    
    // Для других ошибок - повторяем
    error_log("[TEST-PROXY-AIRTABLE] Request failed: HTTP $httpCode, retrying...");
    if ($attempt < $maxRetries - 1) {
      sleep($retryDelays[$attempt] ?? 1);
    }
  }
  
  // Все попытки исчерпаны
  error_log("[TEST-PROXY-AIRTABLE] All retry attempts failed");
  return [
    'ok' => false,
    'http_code' => $httpCode,
    'response' => $response
  ];
}

// Accept POST (JSON) and GET (query params) to bypass mod_security filters on some hosts
$payload = $method === 'POST' ? body_json() : $_GET;

switch ($provider) {
  case 'airtable':
    try {
      // Выбираем рабочий токен с фэйловером
      $pick = get_airtable_token_with_failover('airtable_whoami_check');
      $token = $pick['token'];
      $promote = $pick['promote'];

      // При необходимости — промоутим next -> current
      if ($promote) {
        store_airtable_token('current', $token);
        store_airtable_token('next', null);
      }

      // Если это запрос "whoami", просто отвечаем
      if (!empty($payload['whoami'])) {
        respond(true, ['auth'=>true, 'token_slot' => $promote ? 'next_promoted' : 'current']);
      }

      // Получаем параметры запроса
      $base = $payload['base_id'] ?? '';
      $table = $payload['table'] ?? '';
      $view = $payload['view'] ?? '';
      
      if (!$base || !$table) {
        respond(false, ['error'=>'Missing base_id or table'], 400);
      }

      // Формируем URL для запроса к Airtable
      $url = "https://api.airtable.com/v0/{$base}/{$table}";
      $params = [];
      if ($view) $params['view'] = $view;
      if (!empty($params)) {
        $url .= '?' . http_build_query($params);
      }

      // Выполняем запрос к Airtable с retry логикой
      $result = airtable_request_with_retry($url, $token);
      
      if (!$result['ok']) {
        respond(false, [
          'status' => $result['http_code'],
          'error' => 'Request failed after retries',
          'response' => $result['response']
        ], $result['http_code']);
      }
      
      $data = json_decode($result['response'], true);
      
      respond(true, [
        'status' => $result['http_code'],
        'body' => $data,
        'token_slot' => $promote ? 'next_promoted' : 'current'
      ]);

    } catch (Throwable $e) {
      error_log("[TEST-PROXY-AIRTABLE] Error: " . $e->getMessage());
      respond(false, ['error'=>$e->getMessage()], 401);
    }
    break;

  case 'server_keys':
    try {
      $tokens = load_airtable_tokens();
      respond(true, [
        'keys' => [
          'airtable' => !empty($tokens['current']) || !empty($tokens['next'])
        ]
      ]);
    } catch(Throwable $e) {
      respond(true, ['keys' => ['airtable' => false]]);
    }
    break;

  default:
    respond(false, ['error'=>'Unknown provider'], 400);
}
?>
