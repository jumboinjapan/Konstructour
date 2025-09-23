<?php
// /api/cities-backfill.php
// Link all cities without Region/Регион/Regions/Регионы to a provided region (by rec id or name)
// GET/POST: region_rec_id, region_name (one of them required)
header('Content-Type: application/json; charset=utf-8');

$API_KEY = getenv('AIRTABLE_API_KEY') ?: getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_TOKEN');
$BASE_ID = getenv('AIRTABLE_BASE_ID') ?: '';
$CITY_TABLE_ID = getenv('AIRTABLE_CITIES_TABLE_ID') ?: '';
$REGION_TABLE_ID = '';

$cfgFile = __DIR__.'/config.php';
if (file_exists($cfgFile)){
  $cfg = require $cfgFile; if (is_array($cfg)){
    if (!$API_KEY) $API_KEY = $cfg['airtable']['api_key'] ?? ($cfg['airtable']['token'] ?? ($cfg['airtable_pat'] ?? ($cfg['airtable_registry']['api_key'] ?? '')));
    if ($BASE_ID==='') $BASE_ID = $cfg['airtable_registry']['baseId'] ?? ($cfg['airtable_registry']['base_id'] ?? ($cfg['airtable']['base_id'] ?? $BASE_ID));
    if ($CITY_TABLE_ID==='') $CITY_TABLE_ID = $cfg['airtable_registry']['tables']['city']['tableId'] ?? $CITY_TABLE_ID;
    $REGION_TABLE_ID = $cfg['airtable_registry']['tables']['region']['tableId'] ?? '';
  }
}
if (!$API_KEY || !$BASE_ID || !$CITY_TABLE_ID){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Missing Airtable settings']); exit; }

$regionId = trim((string)($_GET['region_rec_id'] ?? $_POST['region_rec_id'] ?? ''));
$regionName = trim((string)($_GET['region_name'] ?? $_POST['region_name'] ?? ''));
if ($regionId==='' && $regionName===''){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'region_rec_id or region_name required']); exit; }

function air_call_b($method, $path, $apiKey, $payload=null, $query=[]){
  $url = "https://api.airtable.com/v0/$path"; if ($query) $url .= (strpos($url,'?')===false?'?':'&').http_build_query($query);
  $ch = curl_init($url);
  $headers = ["Authorization: Bearer $apiKey"]; if (!is_null($payload)) $headers[]='Content-Type: application/json';
  curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25]);
  if (!is_null($payload)) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  $out = curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch); return [$code,$out,$err];
}

// Resolve regionId by name if needed
if ($regionId==='' && $regionName!=='' && $REGION_TABLE_ID!==''){
  $offset=null; do{
    list($cr,$or,$er)=air_call_b('GET', "$BASE_ID/".rawurlencode($REGION_TABLE_ID), $API_KEY, null, ['pageSize'=>100,'offset'=>$offset]);
    if ($cr>=300) break; $jr=json_decode($or,true);
    foreach(($jr['records']??[]) as $rec){
      $f = $rec['fields'] ?? [];
      $names = [$f['Name (RU)']??'', $f['Название (RU)']??'', $f['Name']??'', $f['Название']??'', $f['Name (EN)']??''];
      foreach($names as $nm){ if ($nm && is_string($nm) && $nm===$regionName){ $regionId=$rec['id']; break 2; } }
    }
    $offset=$jr['offset']??null;
  }while($offset);
}
if ($regionId===''){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Region not found by name']); exit; }

$linkFields = ['Region','Регион','Regions','Регионы'];

// Scan cities without link and patch
$updated = 0; $checked=0; $offset=null;
do{
  list($cc,$oc,$ec)=air_call_b('GET', "$BASE_ID/".rawurlencode($CITY_TABLE_ID), $API_KEY, null, ['pageSize'=>100,'offset'=>$offset]);
  if ($cc>=300) break; $jc=json_decode($oc,true);
  foreach(($jc['records']??[]) as $rec){
    $checked++;
    $fields = $rec['fields'] ?? [];
    $hasLink = false; foreach($linkFields as $lf){ if (!empty($fields[$lf])) { $hasLink=true; break; } }
    if ($hasLink) continue;
    foreach($linkFields as $lf){
      $patch = ['fields'=>[ $lf => [ ['id'=>$regionId] ] ]];
      list($c2,$o2,$e2)=air_call_b('PATCH', "$BASE_ID/".rawurlencode($CITY_TABLE_ID).'/'.rawurlencode($rec['id']), $API_KEY, $patch);
      if ($c2<300){ $updated++; break; }
    }
  }
  $offset=$jc['offset']??null;
}while($offset);

echo json_encode(['ok'=>true,'checked'=>$checked,'updated'=>$updated]);


