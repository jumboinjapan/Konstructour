<?php
// Отладка полей таблицы городов
header('Content-Type: application/json; charset=utf-8');

// Получаем API ключ
$API_KEY = getenv('AIRTABLE_API_KEY') ?: getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_TOKEN');
if (!$API_KEY) {
  $cfgFile = __DIR__.'/config.php';
  if (file_exists($cfgFile)){
    $cfg = require $cfgFile; if (is_array($cfg)){
      $API_KEY = $cfg['airtable_registry']['api_key'] ?? ($cfg['airtable']['api_key'] ?? ($cfg['airtable']['token'] ?? ($cfg['airtable_pat'] ?? '')));
    }
  }
}

if (!$API_KEY) { 
  echo json_encode(['error' => 'No API key'], JSON_UNESCAPED_UNICODE); 
  exit; 
}

// Получаем конфигурацию
$cfgFile = __DIR__.'/config.php';
$cfg = [];
if (file_exists($cfgFile)){
  $cfg = require $cfgFile;
}

$BASE_ID = $cfg['airtable_registry']['baseId'] ?? '';
$CITY_TABLE_ID = $cfg['airtable_registry']['tables']['city']['tableId'] ?? '';

if (!$BASE_ID || !$CITY_TABLE_ID) {
  echo json_encode(['error' => 'No base or table ID'], JSON_UNESCAPED_UNICODE);
  exit;
}

function air_call($method, $path, $apiKey, $payload=null, $query=[]){
  $url = "https://api.airtable.com/v0/$path";
  if ($query) $url .= (strpos($url,'?')===false?'?':'&').http_build_query($query);
  $ch = curl_init($url);
  $headers = ["Authorization: Bearer $apiKey"];
  if (!is_null($payload)) $headers[]='Content-Type: application/json';
  curl_setopt_array($ch,[
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>$headers,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>20
  ]);
  if (!is_null($payload)) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  $out = curl_exec($ch); 
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); 
  $err=curl_error($ch); 
  curl_close($ch); 
  return [$code,$out,$err];
}

// Получаем одну запись из таблицы городов
list($code, $out, $err) = air_call('GET', "$BASE_ID/$CITY_TABLE_ID", $API_KEY, null, ['pageSize' => 1]);

if ($code >= 400) {
  echo json_encode([
    'error' => 'Failed to fetch table',
    'code' => $code,
    'response' => $out,
    'curl_error' => $err
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$data = json_decode($out, true);
$records = $data['records'] ?? [];

if (empty($records)) {
  echo json_encode([
    'message' => 'No records found in table',
    'fields' => []
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// Анализируем поля первой записи
$firstRecord = $records[0];
$fields = $firstRecord['fields'] ?? [];

echo json_encode([
  'message' => 'Table fields found',
  'total_records' => count($records),
  'first_record_id' => $firstRecord['id'] ?? 'unknown',
  'fields' => array_keys($fields),
  'field_details' => $fields
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
