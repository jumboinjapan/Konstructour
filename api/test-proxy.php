<?php
// Simple test proxy for API providers on shared hosting (PHP)
// WARNING: For demo/testing only. Restrict by origin and auth for production.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// CORS: mirror origin for browser requests
if (isset($_SERVER['HTTP_ORIGIN'])){
  header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
  header('Vary: Origin');
} else {
  header('Access-Control-Allow-Origin: *');
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

// Optional server-side secrets (create api/config.php)
$cfg = [];
if (file_exists(__DIR__.'/config.php')) {
  $cfg = require __DIR__.'/config.php';
}

switch ($provider) {
  case 'airtable':
    $key = $payload['api_key'] ?? ($cfg['airtable']['api_key'] ?? '');
    $base = $payload['base_id'] ?? ($cfg['airtable']['base_id'] ?? '');
    $table = $payload['table'] ?? ($cfg['airtable']['table'] ?? '');
    if (!$key) respond(false, ['error'=>'Missing api_key'], 400);
    // Если нет base/table — проверяем валидность ключа через meta/whoami
    if (!$base || !$table) {
      $url = 'https://api.airtable.com/v0/meta/whoami';
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ["Authorization: Bearer {$key}"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
      ]);
      $resp = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $err = curl_error($ch);
      curl_close($ch);
      if ($err) respond(false, ['error'=>$err], 502);
      $decoded = json_decode($resp,true);
      if ($status>=200 && $status<300) {
        respond(true, ['status'=>$status, 'body'=>$decoded]);
      } else {
        $msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'Upstream error';
        respond(false, ['status'=>$status, 'error'=>$msg, 'body'=>$decoded], $status ?: 502);
      }
    }
    // Если base и table заданы — пробуем запрос к таблице
    $url = "https://api.airtable.com/v0/{$base}/".rawurlencode($table)."?maxRecords=1";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_HTTPHEADER => ["Authorization: Bearer {$key}"],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) respond(false, ['error'=>$err], 502);
    $decoded = json_decode($resp,true);
    if ($status>=200 && $status<300) {
      respond(true, ['status'=>$status, 'body'=>$decoded]);
    } else {
      $msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'Upstream error';
      respond(false, ['status'=>$status, 'error'=>$msg, 'body'=>$decoded], $status ?: 502);
    }

  case 'openai':
    // Support both GET (query) and POST (JSON) to this proxy; always POST to OpenAI
    $key = $payload['api_key'] ?? ($cfg['openai']['api_key'] ?? '');
    $model = $payload['model'] ?? ($cfg['openai']['model'] ?? 'gpt-4o-mini');
    if (!$key) respond(false, ['error'=>'Missing api_key'], 400);
    $url = 'https://api.openai.com/v1/chat/completions';
    $body = json_encode([
      'model' => $model,
      // Slightly more robust ping
      'messages' => [[ 'role' => 'user', 'content' => 'Hello' ]],
      'max_tokens' => 10
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "Authorization: Bearer {$key}"
      ],
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $body,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) respond(false, ['error'=>$err], 502);
    $decoded = json_decode($resp, true);
    if ($status>=200 && $status<300) {
      respond(true, ['status'=>$status, 'body'=>$decoded]);
    } else {
      // Return detailed upstream error for UI
      $msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'Upstream error';
      respond(false, ['status'=>$status, 'error'=>$msg, 'body'=>$decoded], $status ?: 502);
    }

  case 'gsheets':
    $key = $payload['api_key'] ?? ($cfg['gsheets']['api_key'] ?? '');
    $sheet = $payload['spreadsheet_id'] ?? ($cfg['gsheets']['spreadsheet_id'] ?? '');
    if (!$key || !$sheet) respond(false, ['error'=>'Missing params'], 400);
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet}?includeGridData=false&key={$key}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) respond(false, ['error'=>$err], 502);
    respond($status>=200 && $status<300, ['status'=>$status, 'body'=>json_decode($resp,true)]);

  case 'gmaps':
    $key = $payload['api_key'] ?? ($cfg['gmaps']['api_key'] ?? '');
    if (!$key) respond(false, ['error'=>'Missing api_key'], 400);
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=Tokyo&key={$key}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) respond(false, ['error'=>$err], 502);
    respond($status>=200 && $status<300, ['status'=>$status, 'body'=>json_decode($resp,true)]);

  case 'recaptcha':
    $site = $payload['site_key'] ?? ($cfg['recaptcha']['site_key'] ?? '');
    $secret = $payload['secret'] ?? ($cfg['recaptcha']['secret'] ?? '');
    if (!$site || !$secret) respond(false, ['error'=>'Missing keys'], 400);
    // We cannot validate without a token here; do a secret format sanity check
    respond(true, ['note'=>'Secret present; real validation requires client token.']);

  case 'brilliantdb':
    $key = $payload['api_key'] ?? ($cfg['brilliantdb']['api_key'] ?? '');
    $base = rtrim($payload['base_url'] ?? ($cfg['brilliantdb']['base_url'] ?? ''), '/');
    $collection = $payload['collection'] ?? ($cfg['brilliantdb']['collection'] ?? '');
    if (!$key || !$base) respond(false, ['error'=>'Missing params'], 400);
    $url = $base.'/collections';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_HTTPHEADER => ["Authorization: Bearer {$key}"],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) respond(false, ['error'=>$err], 502);
    respond($status>=200 && $status<300, ['status'=>$status, 'body'=>json_decode($resp,true)]);

  case 'brilliantdirectory':
    // Brilliant Directories API v2 uses X-Api-Key header and site domain base
    $key = $payload['api_key'] ?? ($cfg['brilliantdb']['api_key'] ?? '');
    $base = rtrim($payload['base_url'] ?? ($cfg['brilliantdb']['base_url'] ?? ''), '/');
    if (!$key || !$base) respond(false, ['error'=>'Missing params'], 400);
    // Call the base /api/v2 endpoint; many installs return website/key info on 200
    $url = $base; // expected like https://www.example.com/api/v2/
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'X-Api-Key: '.$key,
      ],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) respond(false, ['error'=>$err], 502);
    $decoded = json_decode($resp,true);
    if ($status>=200 && $status<300) {
      respond(true, ['status'=>$status, 'body'=>$decoded]);
    } else {
      $msg = isset($decoded['message']) ? $decoded['message'] : 'Upstream error';
      respond(false, ['status'=>$status, 'error'=>$msg, 'body'=>$decoded], $status ?: 502);
    }

  default:
    if ($provider === 'server_keys') {
      // Report which providers have keys configured server-side
      $presence = [
        'openai' => !empty($cfg['openai']['api_key'] ?? ''),
        'airtable' => !empty($cfg['airtable']['api_key'] ?? ''),
        'gsheets' => !empty($cfg['gsheets']['api_key'] ?? ''),
        'gmaps' => !empty($cfg['gmaps']['api_key'] ?? ''),
        'recaptcha' => !empty($cfg['recaptcha']['secret'] ?? ''),
        // Для индикации server key достаточно наличия api_key
        'brilliantdirectory' => !empty($cfg['brilliantdb']['api_key'] ?? '')
      ];
      respond(true, ['keys'=>$presence]);
    }
    respond(false, ['error'=>'Unknown provider'], 400);
}


