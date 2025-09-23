<?php
// Create Region (RU/EN) in Airtable with server-generated sequential ID (no Automations)
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
$regionsTableId = '';
if (!empty($airReg['tables']['region']['tableId'])) $regionsTableId = $airReg['tables']['region']['tableId'];
elseif (!empty($cfg['airtable']['table'])) $regionsTableId = $cfg['airtable']['table'];
if (!$pat || !$baseId || !$regionsTableId) respond(false, ['error'=>'Airtable settings incomplete'], 400);

$apiBase = 'https://api.airtable.com/v0/'.rawurlencode($baseId).'/';

// Helpers
function http_json($url, $method, $pat, $payload=null){
  $ch = curl_init($url);
  $opts = [
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$pat, 'Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>20
  ];
  if ($payload!==null) $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
  curl_setopt_array($ch, $opts);
  $resp = curl_exec($ch); $err = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return [$code,$err,$resp];
}

// Counter table config
$countersTableName = 'Counters'; // create manually with fields: Name(text), Last(number)
$counterName = 'regions';

// Ensure counter record exists
function ensure_counter($apiBase,$pat,$countersTableName,$counterName){
  [$code,$err,$resp] = http_json($apiBase.rawurlencode($countersTableName).'?'.http_build_query(['filterByFormula'=>"Name = '".$counterName."'",'maxRecords'=>1]), 'GET', $pat);
  if ($err) return [null,'Curl: '.$err];
  $j = json_decode($resp,true);
  if ($code>=200 && $code<300 && !empty($j['records'])) return [$j['records'][0],null];
  if ($code>=200 && $code<300){
    // create
    [$c2,$e2,$r2] = http_json($apiBase.rawurlencode($countersTableName), 'POST', $pat, ['fields'=>['Name'=>$counterName,'Last'=>0]]);
    if ($e2) return [null,'Curl: '.$e2];
    $j2 = json_decode($r2,true);
    if ($c2>=200 && $c2<300) return [$j2,null];
    return [null,'Airtable '.$c2];
  }
  return [null,'Airtable '.$code];
}

function next_seq($apiBase,$pat,$countersTableName,$counterName,$maxRetries=5){
  for ($attempt=1; $attempt<=$maxRetries; $attempt++){
    [$rec,$err] = ensure_counter($apiBase,$pat,$countersTableName,$counterName);
    if ($err || !$rec) { usleep(100000*$attempt); continue; }
    $id = $rec['id'];
    $last = intval($rec['fields']['Last'] ?? 0);
    $next = $last + 1;
    [$c,$e,$r] = http_json($apiBase.rawurlencode($countersTableName).'/'.rawurlencode($id), 'PATCH', $pat, ['fields'=>['Last'=>$next]]);
    if ($e){ usleep(100000*$attempt); continue; }
    if ($c>=200 && $c<300) return $next;
    usleep(100000*$attempt);
  }
  return null;
}

function format_reg($n){ return 'REG-'.str_pad(strval($n), 4, '0', STR_PAD_LEFT); }

// Generate next region id with retries and create record
for ($loop=0; $loop<5; $loop++){
  $seq = next_seq($apiBase,$pat,$countersTableName,$counterName);
  if (!$seq){ continue; }
  $regionId = format_reg($seq);
  // pre-check duplicate
  $filter = '{Идентификатор} = '.json_encode($regionId, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  [$c0,$e0,$r0] = http_json($apiBase.rawurlencode($regionsTableId).'?'.http_build_query(['filterByFormula'=>$filter,'maxRecords'=>1]), 'GET', $pat);
  if ($e0) { continue; }
  if ($c0>=200 && $c0<300){
    $j0 = json_decode($r0,true);
    if (!empty($j0['records'])) { usleep(150000); continue; }
  }
  // create
  [$c1,$e1,$r1] = http_json($apiBase.rawurlencode($regionsTableId), 'POST', $pat, ['fields'=>['Название (RU)'=>$nameRu,'Название (EN)'=>$nameEn,'Идентификатор'=>$regionId]]);
  if ($e1){ @file_get_contents(__DIR__.'/error-log.php?action=add', false, stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\n", 'content'=>json_encode(['type'=>'error','msg'=>'regions.create curl error','ctx'=>['err'=>$e1]])]])); continue; }
  if ($c1>=200 && $c1<300){ $j1=json_decode($r1,true); respond(true, ['record_id'=>$j1['id']??'', 'region_id'=>$regionId, 'name_ru'=>$nameRu, 'name_en'=>$nameEn]); }
}

respond(false, ['error'=>'could_not_generate_id'], 500);


