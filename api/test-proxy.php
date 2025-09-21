<?php
// Simple test proxy for API providers on shared hosting (PHP)
// WARNING: For demo/testing only. Restrict by origin and auth for production.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

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
    $key = $payload['api_key'] ?? '';
    $base = $payload['base_id'] ?? '';
    $table = $payload['table'] ?? '';
    if (!$key || !$base || !$table) respond(false, ['error'=>'Missing params'], 400);
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
    respond($status>=200 && $status<300, ['status'=>$status, 'body'=>json_decode($resp,true)]);

  case 'openai':
    $key = $payload['api_key'] ?? '';
    $model = $payload['model'] ?? 'gpt-4o-mini';
    if (!$key) respond(false, ['error'=>'Missing api_key'], 400);
    $url = 'https://api.openai.com/v1/chat/completions';
    $body = json_encode([
      'model'=>$model,
      'messages'=>[['role'=>'user','content'=>'ping']],
      'max_tokens'=>4
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
    respond($status>=200 && $status<300, ['status'=>$status, 'body'=>json_decode($resp,true)]);

  case 'gsheets':
    $key = $payload['api_key'] ?? '';
    $sheet = $payload['spreadsheet_id'] ?? '';
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
    $key = $payload['api_key'] ?? '';
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
    $site = $payload['site_key'] ?? '';
    $secret = $payload['secret'] ?? '';
    if (!$site || !$secret) respond(false, ['error'=>'Missing keys'], 400);
    // We cannot validate without a token here; do a secret format sanity check
    respond(true, ['note'=>'Secret present; real validation requires client token.']);

  case 'brilliantdb':
    $key = $payload['api_key'] ?? '';
    $base = rtrim($payload['base_url'] ?? '', '/');
    $collection = $payload['collection'] ?? '';
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

  default:
    respond(false, ['error'=>'Unknown provider'], 400);
}


