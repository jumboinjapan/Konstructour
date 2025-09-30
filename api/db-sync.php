<?php
// Minimal DB sync proxy (Airtable create/list placeholder)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond($ok, $data=[], $code=200){ http_response_code($code); echo json_encode(['ok'=>$ok]+$data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

// Try to determine which field should store business Region ID
function detectIdFieldForRegion($airReg, $baseUrl, $pat){
  // Prefer explicit mapping from registry
  if (!empty($airReg['tables']['region']['idField'])) return $airReg['tables']['region']['idField'];
  if (!empty($airReg['tables']['region']['id_field'])) return $airReg['tables']['region']['id_field'];
  // Probe first record to see existing field names
  $url = $baseUrl.'?pageSize=1';
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER=>[
      'Authorization: Bearer '.$pat
    ],
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>10
  ]);
  $resp = curl_exec($ch); $err = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  if ($err || $code < 200 || $code >= 300) return null;
  $json = json_decode($resp, true);
  $records = $json['records'] ?? [];
  if (!$records) return null;
  $fields = $records[0]['fields'] ?? [];
  $candidates = ['Region ID','Регион ID','ID','RegionID','РегионID'];
  foreach ($candidates as $name){ if (array_key_exists($name, $fields)) return $name; }
  return null;
}

// Determine a safe name field to write into. Prefer registry label only if it exists; otherwise fallback to 'Name'.
function determineNameField($airReg, $scope, $baseUrl, $pat){
  $label = null;
  if (!empty($airReg['tables']) && is_array($airReg['tables'])){
    $map = [ 'regions'=>'region', 'cities'=>'city', 'pois'=>'poi' ];
    $key = $map[$scope] ?? '';
    if ($key && !empty($airReg['tables'][$key]['label'])){ $label = $airReg['tables'][$key]['label']; }
  }
  if (!$label || $label === 'Name') return 'Name';
  // Verify the label exists by probing one record; if table empty or field missing, fallback to 'Name'
  $url = $baseUrl.'?pageSize=1';
  $ch = curl_init($url);
  curl_setopt_array($ch, [ CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$pat], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8 ]);
  $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  if (!($code>=200 && $code<300)) return 'Name';
  $json = json_decode($resp, true);
  $recs = $json['records'] ?? [];
  if (!$recs) return 'Name';
  $fields = $recs[0]['fields'] ?? [];
  return array_key_exists($label, $fields) ? $label : 'Name';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, ['error'=>'Invalid method'], 405);
// Allow HTTP for local/admin pages; production usually fronted by TLS terminator
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref && strpos($ref, (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].'/') !== 0) {
  respond(false, ['error'=>'Invalid origin'], 403);
}

$raw = file_get_contents('php://input');
$req = json_decode($raw, true);
if (!$req || !is_array($req)) respond(false, ['error'=>'Invalid JSON'], 400);

$scope = $req['scope'] ?? '';
$action = $req['action'] ?? '';
$payload = $req['data'] ?? [];
if (!$scope || !$action) respond(false, ['error'=>'Missing params'], 400);

// Admin token проверка отключена для db-sync (запросы приходят только из админки)

// Load config
$cfg = [];
$cfgFile = __DIR__.'/config.php';
if (file_exists($cfgFile)) { $cfg = require $cfgFile; if (!is_array($cfg)) $cfg = []; }
$dbCfg = $cfg['databases'][$scope] ?? null;

// Prefer new Airtable registry if present and scope is hierarchical entity
$airReg = $cfg['airtable_registry'] ?? null;
$provider = $dbCfg['provider'] ?? '';
$baseId = '';
$table = '';
$pat = $cfg['airtable']['api_key'] ?? '';
// tolerate alternate locations for PAT
if (!$pat) { $pat = $cfg['airtable']['token'] ?? ''; }
if (!$pat) { $pat = $cfg['airtable_pat'] ?? ''; }
if (!$pat) { $pat = getenv('AIRTABLE_PAT') ?: ''; }

if ($airReg && in_array($scope, ['regions','cities','pois'], true)){
  $regBase = $airReg['baseId'] ?? ($airReg['base_id'] ?? '');
  $tables = $airReg['tables'] ?? [];
  $map = [ 'regions'=>'region', 'cities'=>'city', 'pois'=>'poi' ];
  $key = $map[$scope];
  $tbl = $tables[$key] ?? [];
  $baseId = $regBase;
  $table = $tbl['tableId'] ?? ($tbl['table_id'] ?? '');
  $provider = 'airtable';
  // fallback PAT из реестра, если задан
  if (!$pat){
    if (!empty($airReg['api_key'])) { $pat = $airReg['api_key']; }
    elseif (!empty($airReg['token'])) { $pat = $airReg['token']; }
  }
}

if ($provider === 'airtable'){
  // Fallback to old databases config если реестр не использован
  if (!$baseId){ $baseId = $dbCfg['base_id'] ?? ''; }
  if (!$table){ $table = $dbCfg['table_id'] ?? ''; }
  // Дополнительные источники PAT, если не задан
  if (!$pat){
    if (!empty($cfg['airtable']['token'])) { $pat = $cfg['airtable']['token']; }
    elseif (!empty($cfg['airtable_pat'])) { $pat = $cfg['airtable_pat']; }
    elseif (getenv('AIRTABLE_PAT')) { $pat = getenv('AIRTABLE_PAT'); }
  }
  // Жёсткие дефолты на случай отсутствия config.php, только для regions
  if ((!$baseId || !$table) && $scope==='regions'){
    $baseId = $baseId ?: 'apppwhjFN82N9zNqm';
    $table = $table ?: 'tblbSajWkzI8X7M4U';
  }
  if (!$baseId || !$table || !$pat) respond(false, ['error'=>'Airtable settings incomplete'], 400);

  $baseUrl = 'https://api.airtable.com/v0/'.rawurlencode($baseId).'/'.rawurlencode($table);

  if ($action === 'list'){
    // Optional default view from registry
    $viewId = '';
    if (!empty($airReg['tables'])){
      $map = [ 'regions'=>'region', 'cities'=>'city', 'pois'=>'poi' ];
      $key = $map[$scope] ?? '';
      $tbl = $airReg['tables'][$key] ?? [];
      $viewId = $tbl['viewId'] ?? ($tbl['view_id'] ?? '');
    }
    $url = $baseUrl.'?pageSize=100';
    if ($viewId){ $url .= '&view='.rawurlencode($viewId); }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_HTTPHEADER=>[
        'Authorization: Bearer '.$pat
      ],
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_TIMEOUT=>15
    ]);
    $resp = curl_exec($ch); $err = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($err) { @file_get_contents(__DIR__.'/error-log.php?action=add', false, stream_context_create(['http'=>[
      'method'=>'POST','header'=>"Content-Type: application/json\r\n", 'content'=>json_encode(['type'=>'error','msg'=>'Airtable list curl error','ctx'=>['scope'=>$scope,'err'=>$err]])
    ]])); respond(false, ['error'=>'Curl: '.$err], 500); }
    $json = json_decode($resp, true);
    if ($code >= 200 && $code < 300){
      // Optional filtering by parent
      $records = ($json['records'] ?? []);
      $flt = $payload['filter'] ?? [];
      if (!empty($flt)){
        $rid = $flt['regionId'] ?? '';
        $rname = $flt['regionName'] ?? '';
        $cid = $flt['cityId'] ?? '';
        $cname = $flt['cityName'] ?? '';
          $records = array_values(array_filter($records, function($rec) use ($scope,$rid,$rname,$cid,$cname){
          $fields = $rec['fields'] ?? [];
          $candidates = [];
          if ($scope==='cities'){ $candidates = ['Region','Регион','Regions','Регионы','region','Страна/Регион','Region Link','Регион (ссылка)','Регион → Города']; }
          if ($scope==='pois'){ 
            // Для POI проверяем связи с городом И регионом
            $cityCandidates = ['City','Город','city','Локация/Город'];
            $regionCandidates = ['Regions','Регионы','Region','Регион','region'];
            $candidates = array_merge($cityCandidates, $regionCandidates);
          }
            $hasFilter = (bool)($rid || $rname || $cid || $cname);
          foreach ($candidates as $fn){
            if (!array_key_exists($fn, $fields)) continue;
            $val = $fields[$fn];
            if (is_array($val)){
              foreach ($val as $v){
                if (is_string($v) && ($v===$rid || $v===$rname || $v===$cid || $v===$cname)) return true;
                if (is_array($v)){
                  if (($rid && isset($v['id']) && $v['id']===$rid) || ($cid && isset($v['id']) && $v['id']===$cid)) return true;
                  if (($rname && isset($v['name']) && $v['name']===$rname) || ($cname && isset($v['name']) && $v['name']===$cname)) return true;
                }
              }
            } else if (is_string($val)){
              if ($val===$rid || $val===$rname || $val===$cid || $val===$cname) return true;
            }
            
            // Дополнительная проверка для POI: если есть фильтр по городу, проверяем только городские поля
            if ($scope==='pois' && ($cid || $cname)){
              $cityFields = ['City','Город','city','Локация/Город'];
              if (in_array($fn, $cityFields)){
                if (is_array($val)){
                  foreach ($val as $v){
                    if (is_string($v) && ($v===$cid || $v===$cname)) return true;
                    if (is_array($v) && isset($v['id']) && $v['id']===$cid) return true;
                    if (is_array($v) && isset($v['name']) && $v['name']===$cname) return true;
                  }
                } else if (is_string($val) && ($val===$cid || $val===$cname)) return true;
              }
            }
          }
            // Если фильтр задан, но соответствующих полей/совпадений нет — запись не включаем.
            // Если фильтра нет — оставляем запись.
            return !$hasFilter;
        }));
      }
      // Normalize to simple array, tolerant field names
      $items = [];
      foreach ($records as $rec){
        $fields = $rec['fields'] ?? [];
        $name = '';
        // Prefer explicit RU name if present
        $candidates = ['Name (RU)','Название (RU)','Name','Название','Наименование','Title','Name (EN)','name','title'];
        foreach ($candidates as $fn){ if (isset($fields[$fn]) && $fields[$fn] !== '') { $name = $fields[$fn]; break; } }
        $items[] = [ 'id'=>($rec['id'] ?? ''), 'name'=>$name, 'fields'=>$fields ];
      }
      respond(true, ['items'=>$items]);
    }
    respond(false, ['error'=>'Airtable '.$code, 'response'=>$json], $code ?: 500);
  }

  if ($action === 'create'){
    // Accept either fields directly or shorthand payload
    $fields = $payload['fields'] ?? null;
    if (!$fields){
      $name = $payload['name'] ?? '';
      if ($name){
        // Choose a single safe name field
        $nameField = determineNameField($airReg ?? [], $scope, $baseUrl, $pat);
        $fields = [$nameField=>$name];
      }
      // Optional type for City/Location demo
      if (!empty($payload['type'])){ $fields['Type'] = $payload['type']; }
    }
    // Strict requirement: for regions we must store business Region ID using mapping
    if ($scope === 'regions'){
      $rid = $payload['rid'] ?? ($payload['businessId'] ?? ($payload['Region ID'] ?? ($payload['ID'] ?? '')));
      if ($rid === '' || $rid === null) respond(false, ['error'=>'Region ID is required'], 400);
      $idField = '';
      if (!empty($airReg['tables']['region']['idField'])) $idField = $airReg['tables']['region']['idField'];
      elseif (!empty($airReg['tables']['region']['id_field'])) $idField = $airReg['tables']['region']['id_field'];
      if (!$idField) $idField = 'Идентификатор';
      if (!is_array($fields)) $fields = [];
      $fields[$idField] = $rid;
    }
    if (!$fields || !is_array($fields)) respond(false, ['error'=>'No fields'], 400);

    $ch = curl_init($baseUrl);
    curl_setopt_array($ch, [
      CURLOPT_POST=>true,
      CURLOPT_HTTPHEADER=>[
        'Authorization: Bearer '.$pat,
        'Content-Type: application/json'
      ],
      CURLOPT_POSTFIELDS=>json_encode(['records'=>[['fields'=>$fields]]], JSON_UNESCAPED_UNICODE),
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_TIMEOUT=>15
    ]);
    $resp = curl_exec($ch); $err = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($err) respond(false, ['error'=>'Curl: '.$err], 500);
    $json = json_decode($resp, true);
    if ($code >= 200 && $code < 300){ respond(true, ['result'=>$json]); }
    @file_get_contents(__DIR__.'/error-log.php?action=add', false, stream_context_create(['http'=>[
      'method'=>'POST','header'=>"Content-Type: application/json\r\n", 'content'=>json_encode(['type'=>'error','msg'=>'Airtable create failed','ctx'=>['scope'=>$scope,'code'=>$code,'response'=>$json]])
    ]]));
    respond(false, ['error'=>'Airtable '.$code, 'response'=>$json], $code ?: 500);
  }

  if ($action === 'update'){
    $id = $payload['id'] ?? '';
    if (!$id) respond(false, ['error'=>'Missing id'], 400);
    $fields = $payload['fields'] ?? [];
    if (!$fields){
      if (!empty($payload['name'])){
        $nameField = determineNameField($airReg ?? [], $scope, $baseUrl, $pat);
        $fields[$nameField] = $payload['name'];
      }
      if (!empty($payload['type'])){ $fields['Type'] = $payload['type']; }
    }
    // Allow updating Region ID for regions if mapping provided
    if ($scope === 'regions'){
      $rid = $payload['rid'] ?? ($payload['businessId'] ?? ($payload['Region ID'] ?? ($payload['ID'] ?? '')));
      if ($rid !== '' && $rid !== null){
        $idField = '';
        if (!empty($airReg['tables']['region']['idField'])) $idField = $airReg['tables']['region']['idField'];
        elseif (!empty($airReg['tables']['region']['id_field'])) $idField = $airReg['tables']['region']['id_field'];
        if ($idField){ $fields[$idField] = $rid; }
      }
    }
    $ch = curl_init($baseUrl);
    curl_setopt_array($ch, [
      CURLOPT_CUSTOMREQUEST=>'PATCH',
      CURLOPT_HTTPHEADER=>[
        'Authorization: Bearer '.$pat,
        'Content-Type: application/json'
      ],
      CURLOPT_POSTFIELDS=>json_encode(['records'=>[['id'=>$id,'fields'=>$fields]]], JSON_UNESCAPED_UNICODE),
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_TIMEOUT=>15
    ]);
    $resp = curl_exec($ch); $err = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($err) { @file_get_contents(__DIR__.'/error-log.php?action=add', false, stream_context_create(['http'=>[
      'method'=>'POST','header'=>"Content-Type: application/json\r\n", 'content'=>json_encode(['type'=>'error','msg'=>'Airtable update curl error','ctx'=>['scope'=>$scope,'err'=>$err]])
    ]])); respond(false, ['error'=>'Curl: '.$err], 500); }
    $json = json_decode($resp, true);
    if ($code >= 200 && $code < 300){ respond(true, ['result'=>$json]); }
    @file_get_contents(__DIR__.'/error-log.php?action=add', false, stream_context_create(['http'=>[
      'method'=>'POST','header'=>"Content-Type: application/json\r\n", 'content'=>json_encode(['type'=>'error','msg'=>'Airtable update failed','ctx'=>['scope'=>$scope,'code'=>$code,'response'=>$json]])
    ]]));
    respond(false, ['error'=>'Airtable '.$code, 'response'=>$json], $code ?: 500);
  }

  if ($action === 'delete'){
    $id = $payload['id'] ?? '';
    if (!$id) respond(false, ['error'=>'Missing id'], 400);
    $url = $baseUrl.'?'.http_build_query(['records[]'=>$id]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_CUSTOMREQUEST=>'DELETE',
      CURLOPT_HTTPHEADER=>[
        'Authorization: Bearer '.$pat
      ],
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_TIMEOUT=>15
    ]);
    $resp = curl_exec($ch); $err = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($err) { @file_get_contents(__DIR__.'/error-log.php?action=add', false, stream_context_create(['http'=>[
      'method'=>'POST','header'=>"Content-Type: application/json\r\n", 'content'=>json_encode(['type'=>'error','msg'=>'Airtable delete curl error','ctx'=>['scope'=>$scope,'err'=>$err]])
    ]])); respond(false, ['error'=>'Curl: '.$err], 500); }
    $json = json_decode($resp, true);
    if ($code >= 200 && $code < 300){ respond(true, ['result'=>$json]); }
    @file_get_contents(__DIR__.'/error-log.php?action=add', false, stream_context_create(['http'=>[
      'method'=>'POST','header'=>"Content-Type: application/json\r\n", 'content'=>json_encode(['type'=>'error','msg'=>'Airtable delete failed','ctx'=>['scope'=>$scope,'code'=>$code,'response'=>$json]])
    ]]));
    respond(false, ['error'=>'Airtable '.$code, 'response'=>$json], $code ?: 500);
  }

  // Not implemented actions yet
  respond(false, ['error'=>'Action not implemented'], 400);
}

respond(false, ['error'=>'Provider not implemented'], 400);


