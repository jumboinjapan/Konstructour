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
      $API_KEY = $cfg['airtable']['api_key'] ?? ($cfg['airtable']['token'] ?? ($cfg['airtable_pat'] ?? ($cfg['airtable_registry']['api_key'] ?? '')));
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
  foreach(($j['records']??[]) as $rec){ $v=strval(($rec['fields']['ID']??$rec['fields']['Идентификатор']??'')); if (preg_match($re,$v,$m)) $max=max($max,intval($m[1],10)); }
  $offset=$j['offset']??null;
}while($offset);
$nextId = sprintf('%s-%04d',$prefix,$max+1);

// 2) Build fields
$nameRuCandidates = ['Name (RU)','Название (RU)','Название','Name','Title'];
$nameEnCandidates = ['Name (EN)','Название (EN)','English Name','EN Name','Name (EN) '];
$idCandidates     = ['ID','Идентификатор','Идентификатор ','Id'];
$linkCandidates   = ['Region','Регион','Regions','Регионы','Region Link','Регион (ссылка)','Region → Cities','Регион → Города'];
// Если в реестре прописано точное имя поля-ссылки — используем его в приоритете
if (!empty($cfg['airtable_registry']['tables']['city']['linkField'])){
  $lf = $cfg['airtable_registry']['tables']['city']['linkField'];
  if (is_string($lf) && $lf!==''){ array_unshift($linkCandidates, $lf); }
}
// Кандидаты для сохранения бизнес-кода региона (RID), если он передан
$regionCodeCandidates = ['Region Code','RegionID','Регион ID','Регион код','Region Business ID','Идентификатор региона','Регион ID (код)','Region RID','Регион RID'];

// Build candidate payloads. Включаем ссылку на родительский регион сразу при создании.
$attempts = [];
foreach ($idCandidates as $fid){
  foreach ($nameRuCandidates as $fru){
    foreach ($nameEnCandidates as $fen){
      $baseFields = [ $fid=>$nextId, $fru=>$name_ru, $fen=>$name_en, 'Type'=>($type==='location'?'location':'city') ];
      // Варианты с немедленной линковкой по recordId
      if ($regionId!==''){
        foreach ($linkCandidates as $lf){
          $attempts[] = ['fields'=> $baseFields + [ $lf => [ ['id'=>$regionId] ] ], 'typecast'=>true ];
        }
      }
      // Варианты с линком по бизнес-коду региона (если он является отображаемым полем в Regions)
      if ($regionRid!==''){
        foreach ($linkCandidates as $lf){
          $attempts[] = ['fields'=> $baseFields + [ $lf => [ $regionRid ] ], 'typecast'=>true ];
        }
      }
      // Вариант без ссылки (если линк запретит создание — попробуем затем патчем)
      $attempts[] = ['fields'=>$baseFields, 'typecast'=>true ];
    }
  }
}

$created = null; $lastErr=null; $lastResp=null; $lastReq=null;
foreach ($attempts as $payload){
  list($code,$out,$err)=air_call('POST', "$BASE_ID/$CITY_TABLE_ID", $API_KEY, $payload);
  if ($code<300){ $created=json_decode($out,true); $lastReq=$payload; break; }
  $lastErr=$err; $lastResp=json_decode($out,true); $lastReq=$payload;
}

if (!$created){
  log_err('City create failed (all attempts)',['response'=>$lastResp,'request'=>$lastReq]);
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Airtable 422','details'=>$lastResp?:['message'=>'Unprocessable Entity']], JSON_UNESCAPED_UNICODE); exit;
}

// Try to link Region after creation (best-effort)
 $linkedOk = false;
 if (!empty($created['id'])){
   // 1) Try by recordId
   if ($regionId!==''){
     foreach ($linkCandidates as $lf){
       $patch = ['fields'=>[ $lf => [ ['id'=>$regionId] ] ]];
     list($c2,$o2,$e2)=air_call('PATCH', "$BASE_ID/$CITY_TABLE_ID/".rawurlencode($created['id']), $API_KEY, $patch+['typecast'=>true]);
       if ($c2<300){ $linkedOk=true; break; }
     }
   }
   // 2) Try by region name into link field directly (some bases accept name)
   if (!$linkedOk && $regionName!==''){
     foreach ($linkCandidates as $lf){
      $patch = ['fields'=>[ $lf => [ $regionName ] ], 'typecast'=>true];
      list($c2,$o2,$e2)=air_call('PATCH', "$BASE_ID/$CITY_TABLE_ID/".rawurlencode($created['id']), $API_KEY, $patch);
       if ($c2<300){ $linkedOk=true; break; }
     }
   }
  // 2b) Try by region business code (RID)
  if (!$linkedOk && $regionRid!==''){
    foreach ($linkCandidates as $lf){
      $patch = ['fields'=>[ $lf => [ $regionRid ] ], 'typecast'=>true];
      list($c2,$o2,$e2)=air_call('PATCH', "$BASE_ID/$CITY_TABLE_ID/".rawurlencode($created['id']), $API_KEY, $patch);
      if ($c2<300){ $linkedOk=true; break; }
    }
  }
 // 3) Fallback: resolve Region record by name via Regions table, then link by id
   if (!$linkedOk && $regionName!=='' && !empty($REGION_TABLE_ID)){
     $offset=null; do{
       list($cr,$or,$er)=air_call('GET', "$BASE_ID/".rawurlencode($REGION_TABLE_ID), $API_KEY, null, ['pageSize'=>100,'offset'=>$offset]);
       if ($cr>=300) break; $jr=json_decode($or,true);
       foreach(($jr['records']??[]) as $rec){
         $fields=$rec['fields']??[]; $names=[$fields['Name (RU)']??'', $fields['Название (RU)']??'', $fields['Name']??'', $fields['Название']??'', $fields['Name (EN)']??''];
         foreach($names as $nm){ if ($nm && is_string($nm) && $nm===$regionName){ $regionId=$rec['id']; break 2; } }
       }
       $offset=$jr['offset']??null;
     }while($offset);
     if ($regionId!==''){
       foreach ($linkCandidates as $lf){
         $patch = ['fields'=>[ $lf => [ ['id'=>$regionId] ] ]];
         list($c2,$o2,$e2)=air_call('PATCH', "$BASE_ID/$CITY_TABLE_ID/".rawurlencode($created['id']), $API_KEY, $patch+['typecast'=>true]);
         if ($c2<300){ $linkedOk=true; break; }
       }
     }
   }
   if (!$linkedOk){ log_err('City link-to-region failed', ['created'=>$created,'regionId'=>$regionId,'regionName'=>$regionName]); }
 }

 // 4) (optional) Проставим бизнес-код региона в поле города, если передан
 if (!empty($created['id']) && $regionRid!==''){
   foreach ($regionCodeCandidates as $rf){
     $patch = ['fields'=>[ $rf => $regionRid ]];
     list($c3,$o3,$e3)=air_call('PATCH', "$BASE_ID/$CITY_TABLE_ID/".rawurlencode($created['id']), $API_KEY, $patch);
     if ($c3<300){ break; }
   }
 }

echo json_encode(['ok'=>true,'record_id'=>$created['id']??null,'city_id'=>$nextId,'type'=>$type,'linked'=>$linkedOk], JSON_UNESCAPED_UNICODE);


