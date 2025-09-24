<?php
// /api/cities-create.php — Create City/Location without Airtable Automations
// Input JSON: { name_ru, name_en, type: 'city'|'location', region_rec_id }
// ID format: CTY-0001 for city, LOC-0001 for location
header('Content-Type: application/json; charset=utf-8');

$API_KEY = getenv('AIRTABLE_API_KEY') ?: getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_TOKEN');

// Try to read from server config as fallback
if (!$API_KEY) {
  $cfgFile = __DIR__.'/config.php';
  if (file_exists($cfgFile)){
    $cfg = require $cfgFile; if (is_array($cfg)){
      $API_KEY = $cfg['airtable_registry']['api_key'] ?? ($cfg['airtable']['api_key'] ?? ($cfg['airtable']['token'] ?? ($cfg['airtable_pat'] ?? '')));
    }
  }
}
if (!$API_KEY) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server Airtable key missing'], JSON_UNESCAPED_UNICODE); exit; }

// Resolve Base / City table from registry or env
$BASE_ID = getenv('AIRTABLE_BASE_ID') ?: '';
$CITY_TABLE_ID = getenv('AIRTABLE_CITIES_TABLE_ID') ?: '';
if (file_exists(__DIR__.'/config.php')){
  $cfg = require __DIR__.'/config.php'; if (is_array($cfg)){
    if ($BASE_ID==='') $BASE_ID = $cfg['airtable_registry']['baseId'] ?? ($cfg['airtable_registry']['base_id'] ?? ($cfg['airtable']['base_id'] ?? $BASE_ID));
    if ($CITY_TABLE_ID==='') $CITY_TABLE_ID = $cfg['airtable_registry']['tables']['city']['tableId'] ?? $CITY_TABLE_ID;
    $REGION_TABLE_ID = $cfg['airtable_registry']['tables']['region']['tableId'] ?? '';
  }
}
if (!$BASE_ID || !$CITY_TABLE_ID){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Airtable base/table missing (cities)'], JSON_UNESCAPED_UNICODE); exit; }

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
$name_ru = trim((string)($body['name_ru'] ?? ''));
$name_en = trim((string)($body['name_en'] ?? ''));
$type    = strtolower(trim((string)($body['type'] ?? 'city')));
$regionId = trim((string)($body['region_rec_id'] ?? ''));
$regionName = trim((string)($body['region_name'] ?? ''));
$regionRid = trim((string)($body['region_rid'] ?? ''));
if ($name_ru==='' || $name_en===''){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'name_ru and name_en are required'], JSON_UNESCAPED_UNICODE); exit; }
if ($type!=='city' && $type!=='location') $type='city';

function air_call($method, $path, $apiKey, $payload=null, $query=[]){
  $url = "https://api.airtable.com/v0/$path"; if ($query) $url .= (strpos($url,'?')===false?'?':'&').http_build_query($query);
  $ch = curl_init($url);
  $headers = ["Authorization: Bearer $apiKey"]; if (!is_null($payload)) $headers[]='Content-Type: application/json';
  curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);
  if (!is_null($payload)) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  $out = curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch); return [$code,$out,$err];
}
function log_err($msg,$ctx){ @file_get_contents(__DIR__.'/error-log.php?action=add', false, stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\n", 'content'=>json_encode(['type'=>'error','msg'=>$msg,'ctx'=>$ctx], JSON_UNESCAPED_UNICODE) ]])); }

// 1) Compute next ID for requested prefix
$prefix = $type==='location' ? 'LOC' : 'CTY';
$offset=null; $max=0; $loops=0; $re = '/^'.preg_quote($prefix,'/').'-(\d{4})$/';
do{
  $loops++; if ($loops>1000) break;
  list($code,$out,$err)=air_call('GET', "$BASE_ID/$CITY_TABLE_ID", $API_KEY, null, ['pageSize'=>100,'offset'=>$offset]);
  if ($code>=400){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>"Airtable $code on list",'details'=>json_decode($out,true)], JSON_UNESCAPED_UNICODE); exit; }
  $j=json_decode($out,true);
  foreach(($j['records']??[]) as $rec){ $v=strval(($rec['fields']['ID']??'')); if (preg_match($re,$v,$m)) $max=max($max,intval($m[1],10)); }
  $offset=$j['offset']??null;
}while($offset);
$nextId = sprintf('%s-%04d',$prefix,$max+1);

// 2) Build fields - ПРАВИЛЬНАЯ ЛОГИКА ДЛЯ LINKED RECORDS
$nameRuCandidates = ['Name (RU)','Название (RU)'];
$nameEnCandidates = ['Name (EN)','Название (EN)'];
$idCandidates     = ['ID'];

// Поле для связанной записи региона (Linked Record)
$linkField = $cfg['airtable_registry']['tables']['city']['linkField'] ?? 'Regions';
// Поле для кода региона (текстовое)
$regionCodeField = $cfg['airtable_registry']['tables']['city']['regionCodeField'] ?? 'Region';

// ПРАВИЛЬНАЯ ЛОГИКА: создаём город с Linked Record и текстовым кодом региона
$attempts = [];
foreach ($idCandidates as $fid){
  foreach ($nameRuCandidates as $fru){
    foreach ($nameEnCandidates as $fen){
      $baseFields = [ 
        $fid => $nextId, 
        $fru => $name_ru, 
        $fen => $name_en
      ];
      
      // Добавляем связанную запись региона (Linked Record)
      if ($regionId !== '') {
        $baseFields[$linkField] = [['id' => $regionId]];
      }
      
      // Добавляем код региона (текстовое поле)
      if ($regionRid !== '') {
        $baseFields[$regionCodeField] = $regionRid;
      }
      
      $attempts[] = ['fields' => $baseFields];
    }
  }
}

$created = null; $lastErr=null; $lastResp=null; $lastReq=null;
foreach ($attempts as $payload){
  list($code,$out,$err)=air_call('POST', "$BASE_ID/$CITY_TABLE_ID", $API_KEY, $payload, ['typecast'=>'true']);
  if ($code<300){ $created=json_decode($out,true); $lastReq=$payload; break; }
  $lastErr=$err; $lastResp=json_decode($out,true); $lastReq=$payload;
}

if (!$created){
  log_err('City create failed (all attempts)',['response'=>$lastResp,'request'=>$lastReq]);
  http_response_code(500); 
  echo json_encode([
    'ok'=>false,
    'error'=>'Airtable 422',
    'details'=>$lastResp?:['message'=>'Unprocessable Entity'],
    'debug'=>[
      'attempts_count'=>count($attempts),
      'last_request'=>$lastReq,
      'last_response'=>$lastResp,
      'region_id'=>$regionId,
      'region_rid'=>$regionRid,
      'link_field'=>$linkField,
      'region_code_field'=>$regionCodeField
    ]
  ], JSON_UNESCAPED_UNICODE); 
  exit;
}

// Проверяем, сохранились ли связанная запись и код региона
$linkedOk = false;
if (!empty($created['id'])) {
  $fields = $created['fields'] ?? [];
  
  // Проверяем связанную запись региона
  $regionLinked = false;
  if ($regionId !== '' && isset($fields[$linkField])) {
    foreach ($fields[$linkField] as $linkedRec) {
      if (isset($linkedRec['id']) && $linkedRec['id'] === $regionId) {
        $regionLinked = true;
        break;
      }
    }
  }
  
  // Проверяем код региона
  $regionCodeSaved = false;
  if ($regionRid !== '' && isset($fields[$regionCodeField]) && $fields[$regionCodeField] === $regionRid) {
    $regionCodeSaved = true;
  }
  
  // Считаем успешным если есть хотя бы одна из связей
  $linkedOk = $regionLinked || $regionCodeSaved;
}

echo json_encode(['ok'=>true,'record_id'=>$created['id']??null,'city_id'=>$nextId,'type'=>$type,'linked'=>$linkedOk], JSON_UNESCAPED_UNICODE);


