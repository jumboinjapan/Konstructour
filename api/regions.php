<?php
// Create Region (RU/EN) in Airtable and wait for Automation to assign stable ID
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond($ok, $data=[], $code=200){ http_response_code($code); echo json_encode(['ok'=>$ok]+$data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, ['error'=>'Invalid method'], 405);

// Same-origin check (allow http/https on same host)
$ref = $_SERVER['HTTP_REFERER'] ?? '';
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on') ? 'https' : 'http';
if ($ref && strpos($ref, $scheme.'://'.$_SERVER['HTTP_HOST'].'/') !== 0) {
  $alt = ($scheme==='https'?'http':'https').'://'.$_SERVER['HTTP_HOST'].'/';
  if (strpos($ref, $alt) !== 0) respond(false, ['error'=>'Invalid origin'], 403);
}

$raw = file_get_contents('php://input');
$req = json_decode($raw, true);
if (!$req || !is_array($req)) respond(false, ['error'=>'Invalid JSON'], 400);

$nameRu = trim((string)($req['name_ru'] ?? ''));
$nameEn = trim((string)($req['name_en'] ?? ''));
if ($nameRu === '' || $nameEn === '') respond(false, ['error'=>'name_ru and name_en are required'], 400);

// Load config
$cfg = [];
$cfgFile = __DIR__.'/config.php';
if (file_exists($cfgFile)) { $cfg = require $cfgFile; if (!is_array($cfg)) $cfg = []; }

$airReg = $cfg['airtable_registry'] ?? [];
$pat = $cfg['airtable']['api_key'] ?? ($airReg['api_key'] ?? ($cfg['airtable']['token'] ?? ''));
$baseId = $airReg['baseId'] ?? ($airReg['base_id'] ?? ($cfg['airtable']['base_id'] ?? ''));
$tableId = '';
if (!empty($airReg['tables']['region']['tableId'])) $tableId = $airReg['tables']['region']['tableId'];
elseif (!empty($cfg['airtable']['table'])) $tableId = $cfg['airtable']['table'];

if (!$pat || !$baseId || !$tableId) respond(false, ['error'=>'Airtable settings incomplete'], 400);

$baseUrl = 'https://api.airtable.com/v0/'.rawurlencode($baseId).'/'.rawurlencode($tableId);

// 1) Create record
$createPayload = [ 'fields' => [ 'Название (RU)' => $nameRu, 'Название (EN)' => $nameEn ] ];
$ch = curl_init($baseUrl);
curl_setopt_array($ch, [
  CURLOPT_POST=>true,
  CURLOPT_HTTPHEADER=>[
    'Authorization: Bearer '.$pat,
    'Content-Type: application/json'
  ],
  CURLOPT_POSTFIELDS=>json_encode($createPayload, JSON_UNESCAPED_UNICODE),
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_TIMEOUT=>20
]);
$resp = curl_exec($ch); $err = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
if ($err) { @file_get_contents(__DIR__.'/error-log.php?action=add', false, stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\n", 'content'=>json_encode(['type'=>'error','msg'=>'regions.create curl error','ctx'=>['err'=>$err]])]])); respond(false, ['error'=>'Curl: '.$err], 500); }
$j = json_decode($resp, true);
if (!($code>=200 && $code<300)){
  @file_get_contents(__DIR__.'/error-log.php?action=add', false, stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\n", 'content'=>json_encode(['type'=>'error','msg'=>'regions.create failed','ctx'=>['code'=>$code,'response'=>$j]])]]));
  respond(false, ['error'=>'Airtable '.$code, 'response'=>$j], $code ?: 500);
}

$recordId = $j['id'] ?? '';
if (!$recordId) respond(false, ['error'=>'No record id'], 500);

// 2) Poll for stable ID assigned by Automation: field name "Идентификатор"
$stableId = '';
for ($i=0; $i<10; $i++){
  $url = $baseUrl.'/'.rawurlencode($recordId);
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER=>[ 'Authorization: Bearer '.$pat ],
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>20
  ]);
  $resp = curl_exec($ch); $err = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  if ($err) break;
  if ($code>=200 && $code<300){
    $rj = json_decode($resp, true);
    $stableId = $rj['fields']['Идентификатор'] ?? '';
    if ($stableId) break;
  }
  usleep(500000); // 500 ms
}

respond(true, [ 'record_id'=>$recordId, 'region_id'=>($stableId?:null), 'name_ru'=>$nameRu, 'name_en'=>$nameEn ]);


