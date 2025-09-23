<?php
// /api/regions-create.php  — REPLACE FILE
header('Content-Type: application/json; charset=utf-8');

$API_KEY = getenv('AIRTABLE_API_KEY') ?: getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_TOKEN');
$BASE_ID  = 'apppwhjFN82N9zNqm';
$TABLE_ID = 'tblbSajWkzI8X7M4U'; // Regions by Table ID

// FIELD NAMES (пока по именам; при желании позже заменим на Field ID fldXXXX...)
$F_ID = 'Идентификатор';
$F_RU = 'Название (RU)';
$F_EN = 'Название (EN)';

// Fallback: попытаться взять ключ из server config.php
if (!$API_KEY) {
  $cfgFile = __DIR__.'/config.php';
  if (file_exists($cfgFile)){
    $cfg = require $cfgFile; if (is_array($cfg)){
      $API_KEY = $cfg['airtable']['api_key'] ?? ($cfg['airtable']['token'] ?? ($cfg['airtable_pat'] ?? ($cfg['airtable_registry']['api_key'] ?? '')));
    }
  }
}

if (!$API_KEY) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server Airtable key missing (env AIRTABLE_API_KEY/AIRTABLE_PAT or api/config.php)']); exit; }

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
$name_ru = trim((string)($body['name_ru'] ?? ''));
$name_en = trim((string)($body['name_en'] ?? ''));

if ($name_ru === '' || $name_en === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'name_ru and name_en are required']); exit;
}

function air_call($method, $path, $apiKey, $payload=null, $query=[]) {
  $url = "https://api.airtable.com/v0/$path";
  if ($query) $url .= (strpos($url,'?')===false?'?':'&') . http_build_query($query);
  $ch = curl_init($url);
  $headers = ["Authorization: Bearer $apiKey"];
  if (!is_null($payload)) $headers[] = "Content-Type: application/json";
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
  ]);
  if (!is_null($payload)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  }
  $out = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);
  return [$code, $out, $err];
}

function format_id($n){ return sprintf('REG-%04d', $n); }

// 1) найти максимальный номер REG-XXXX (с пагинацией)
$offset = null; $max = 0;
do {
  list($code,$out,$err) = air_call('GET', "$BASE_ID/$TABLE_ID", $API_KEY, null, ['pageSize'=>100, 'offset'=>$offset]);
  if ($code>=400) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>"Airtable $code on list",'details'=>json_decode($out,true)], JSON_UNESCAPED_UNICODE); exit;
  }
  $j = json_decode($out,true);
  foreach (($j['records'] ?? []) as $rec) {
    $val = strval($rec['fields'][$F_ID] ?? '');
    if (preg_match('/^REG-(\d{4})$/', $val, $m)) {
      $max = max($max, intval($m[1],10));
    }
  }
  $offset = $j['offset'] ?? null;
} while ($offset);

$nextId = format_id($max + 1);

// 2) создание с попытками: сначала с Идентификатор, если ошибка Unknown field — пробуем с Name
$nbsp = "\xC2\xA0"; // NBSP in UTF-8
$candidates = [
  $F_ID,
  $F_ID.' ',           // trailing space
  ' '.$F_ID,           // leading space
  $F_ID.$nbsp,         // trailing NBSP
  $nbsp.$F_ID,         // leading NBSP
  'Name'
];
$created = null; $lastResp=null; $attempt=null; $lastStatus=0;
foreach($candidates as $primary){
  $attempt = ['fields'=>[ $primary=>$nextId, $F_RU=>$name_ru, $F_EN=>$name_en ]];
  list($code,$out,$err) = air_call('POST', "$BASE_ID/$TABLE_ID", $API_KEY, $attempt);
  if ($code>=300){
    $resp=json_decode($out,true); $lastResp=$resp; $lastStatus=$code;
    $msg = strtolower(json_encode($resp, JSON_UNESCAPED_UNICODE));
    if (strpos($msg,'unknown field')!==false || strpos($msg,'could not find field')!==false){
      continue; // попробуем следующий кандидат
    } else {
      @file_get_contents(__DIR__.'/error-log.php?action=add', false, stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\n", 'content'=>json_encode(['type'=>'error','msg'=>'Region create failed','ctx'=>['status'=>$code,'request'=>$attempt,'response'=>$resp]], JSON_UNESCAPED_UNICODE) ]]));
      http_response_code(500); echo json_encode(['ok'=>false,'error'=>"Airtable $code",'request'=>$attempt,'details'=>$resp], JSON_UNESCAPED_UNICODE); exit;
    }
  } else { $created=json_decode($out,true); break; }
}

if (!$created){
  // fallback: создать без первичного, затем PATCH первичное
  $first = ['fields'=>[ $F_RU=>$name_ru, $F_EN=>$name_en ]];
  list($c2,$o2,$e2)=air_call('POST', "$BASE_ID/$TABLE_ID", $API_KEY, $first);
  if ($c2>=300){ $r2=json_decode($o2,true); @file_get_contents(__DIR__.'/error-log.php?action=add', false, stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\n", 'content'=>json_encode(['type'=>'error','msg'=>'Region create no-primary failed','ctx'=>['status'=>$c2,'request'=>$first,'response'=>$r2]], JSON_UNESCAPED_UNICODE) ]])); http_response_code(500); echo json_encode(['ok'=>false,'error'=>"Airtable $c2",'request'=>$first,'details'=>$r2], JSON_UNESCAPED_UNICODE); exit; }
  $created = json_decode($o2,true); $recId = $created['id'] ?? '';
  if ($recId){
    foreach($candidates as $primary){
      $patch=['fields'=>[ $primary=>$nextId ]]; list($pc,$po,$pe)=air_call('PATCH', "$BASE_ID/$TABLE_ID/".rawurlencode($recId), $API_KEY, $patch);
      if ($pc<300) break; // patched
    }
  }
}

echo json_encode(['ok'=>true,'record_id'=>$created['id'] ?? null,'region_id'=>$nextId], JSON_UNESCAPED_UNICODE);

