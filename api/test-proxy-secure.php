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

      // Выполняем запрос к Airtable
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
      ]);
      
      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);
      curl_close($ch);
      
      if ($error) {
        respond(false, ['error'=>$error], 502);
      }
      
      $data = json_decode($response, true);
      
      if ($httpCode >= 200 && $httpCode < 300) {
        respond(true, [
          'status' => $httpCode,
          'body' => $data,
          'token_slot' => $promote ? 'next_promoted' : 'current'
        ]);
      } else {
        respond(false, [
          'status' => $httpCode,
          'error' => $data['error']['message'] ?? 'Unknown error',
          'body' => $data
        ], $httpCode);
      }

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
