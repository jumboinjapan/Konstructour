<?php
// /api/regions-create.php — бесплатный способ без Automations
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ---- конфиг / ключи ----
$AIRTABLE_API_KEY = getenv('AIRTABLE_API_KEY');

// Пытаемся прочитать из config.php, если переменная окружения не задана
$cfgFile = __DIR__.'/config.php';
if (!$AIRTABLE_API_KEY && file_exists($cfgFile)){
  $cfg = require $cfgFile; if (is_array($cfg)){
    $AIRTABLE_API_KEY = $cfg['airtable']['api_key'] ?? ($cfg['airtable']['token'] ?? '');
  }
}

// База/таблица: берём из env, иначе — из airtable_registry
$BASE_ID = getenv('AIRTABLE_BASE_ID') ?: '';
$TABLE_ID = getenv('AIRTABLE_REGIONS_TABLE_ID') ?: (getenv('AIRTABLE_TABLE_ID') ?: '');
$F_ID = getenv('AIRTABLE_REGIONS_ID_FIELD') ?: 'Идентификатор';
$F_RU = getenv('AIRTABLE_REGIONS_NAME_RU_FIELD') ?: 'Название (RU)';
$F_EN = getenv('AIRTABLE_REGIONS_NAME_EN_FIELD') ?: 'Название (EN)';

if (file_exists($cfgFile)){
  $cfg = require $cfgFile; if (is_array($cfg)){
    if ($BASE_ID === ''){
      $BASE_ID = $cfg['airtable_registry']['baseId'] ?? ($cfg['airtable_registry']['base_id'] ?? ($cfg['airtable']['base_id'] ?? $BASE_ID));
    }
    if ($TABLE_ID === ''){
      $TABLE_ID = $cfg['airtable_registry']['tables']['region']['tableId'] ?? ($cfg['airtable']['table'] ?? $TABLE_ID);
    }
  }
}

// ---- чтение JSON тела ----
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$name_ru = trim($payload['name_ru'] ?? '');
$name_en = trim($payload['name_en'] ?? '');

if ($name_ru === '' || $name_en === '') { http_response_code(400); echo json_encode(['ok'=>false, 'error'=>'name_ru and name_en are required'], JSON_UNESCAPED_UNICODE); exit; }
if (!$AIRTABLE_API_KEY) { http_response_code(500); echo json_encode(['ok'=>false, 'error'=>'AIRTABLE_API_KEY is missing'], JSON_UNESCAPED_UNICODE); exit; }
if (!$BASE_ID || !$TABLE_ID) { http_response_code(500); echo json_encode(['ok'=>false, 'error'=>'Airtable base/table missing'], JSON_UNESCAPED_UNICODE); exit; }

// ---- helpers ----
function air_get($url, $apiKey) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [ CURLOPT_HTTPHEADER => ["Authorization: Bearer $apiKey"], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT=>20 ]);
  $out = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); return [$code, $out];
}
function air_post($url, $apiKey, $data) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [ CURLOPT_HTTPHEADER => ["Authorization: Bearer $apiKey","Content-Type: application/json"], CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT=>20 ]);
  $out = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); return [$code, $out];
}

// ---- 1) находим следующий REG-XXXX (скан постранично) ----
$offset = null; $max = 0;
do {
  $qs = http_build_query(['pageSize'=>100, 'offset'=>$offset]);
  list($code, $body) = air_get("https://api.airtable.com/v0/$BASE_ID/$TABLE_ID?$qs", $AIRTABLE_API_KEY);
  if ($code >= 300) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Airtable error '.$code,'details'=>json_decode($body,true)], JSON_UNESCAPED_UNICODE); exit; }
  $j = json_decode($body, true);
  foreach (($j['records'] ?? []) as $rec) {
    $val = strval($rec['fields'][$F_ID] ?? '');
    if (preg_match('/^REG-(\d{4})$/', $val, $m)) {
      $n = intval($m[1], 10);
      if ($n > $max) $max = $n;
    }
  }
  $offset = $j['offset'] ?? null;
} while ($offset);

$next = $max + 1;
$region_id = sprintf('REG-%04d', $next);

// ---- 2) создаём запись сразу с ID ----
$fields = [ $F_RU=>$name_ru, $F_EN=>$name_en, $F_ID=>$region_id ];
list($code, $body) = air_post("https://api.airtable.com/v0/$BASE_ID/$TABLE_ID", $AIRTABLE_API_KEY, ['fields'=>$fields]);

if ($code >= 300) { http_response_code(500); echo json_encode(['ok'=>false, 'error'=>"Airtable error ($code)", 'details'=>json_decode($body, true)], JSON_UNESCAPED_UNICODE); exit; }

$created = json_decode($body, true);
echo json_encode(['ok'=>true, 'record_id'=>$created['id'] ?? null, 'region_id'=>$region_id], JSON_UNESCAPED_UNICODE);


