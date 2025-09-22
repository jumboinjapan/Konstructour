<?php
// Minimal DB sync proxy (Airtable create/list placeholder)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond($ok, $data=[], $code=200){ http_response_code($code); echo json_encode(['ok'=>$ok]+$data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

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

// Optional admin token: only required for mutating actions
$adminTokenCfg = __DIR__.'/admin-token.php';
if (file_exists($adminTokenCfg) && in_array($action, ['create','update','delete'], true)){
  $cfgToken = require $adminTokenCfg; $cfgToken = is_array($cfgToken)?($cfgToken['token']??''):'{}';
  $hdrToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
  if (!$cfgToken || !$hdrToken || !hash_equals($cfgToken, $hdrToken)){
    respond(false, ['error'=>'Auth token required'], 401);
  }
}

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

if ($airReg && in_array($scope, ['regions','cities','pois'], true)){
  $regBase = $airReg['baseId'] ?? ($airReg['base_id'] ?? '');
  $tables = $airReg['tables'] ?? [];
  $map = [ 'regions'=>'region', 'cities'=>'city', 'pois'=>'poi' ];
  $key = $map[$scope];
  $tbl = $tables[$key] ?? [];
  $baseId = $regBase;
  $table = $tbl['tableId'] ?? ($tbl['table_id'] ?? '');
  $provider = 'airtable';
}

if ($provider === 'airtable'){
  // Fallback to old databases config if registry was not used
  if (!$baseId){ $baseId = $dbCfg['base_id'] ?? ''; }
  if (!$table){ $table = $dbCfg['table_id'] ?? ''; }
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
    if ($err) respond(false, ['error'=>'Curl: '.$err], 500);
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
          if ($scope==='cities'){ $candidates = ['Region','Регион','region','Страна/Регион']; }
          if ($scope==='pois'){ $candidates = ['City','Город','city','Локация/Город']; }
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
          }
          // if no candidate field or no match, keep if no filter provided
          return ($scope!=='cities' && $scope!=='pois') ? true : false;
        }));
      }
      // Normalize to simple array, tolerant field names
      $items = [];
      foreach ($records as $rec){
        $fields = $rec['fields'] ?? [];
        $name = '';
        foreach (['Name','Название','Наименование','Title','name','title'] as $fn){ if (isset($fields[$fn]) && $fields[$fn] !== '') { $name = $fields[$fn]; break; } }
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
        // Duplicate into common name variants for compatibility
        $fields = ['Name'=>$name, 'Название'=>$name, 'Title'=>$name];
      }
      // Optional type for City/Location demo
      if (!empty($payload['type'])){ $fields['Type'] = $payload['type']; }
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
    respond(false, ['error'=>'Airtable '.$code, 'response'=>$json], $code ?: 500);
  }

  if ($action === 'update'){
    $id = $payload['id'] ?? '';
    if (!$id) respond(false, ['error'=>'Missing id'], 400);
    $fields = $payload['fields'] ?? [];
    if (!$fields){
      if (!empty($payload['name'])){ $fields['Name'] = $payload['name']; }
      if (!empty($payload['type'])){ $fields['Type'] = $payload['type']; }
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
    if ($err) respond(false, ['error'=>'Curl: '.$err], 500);
    $json = json_decode($resp, true);
    if ($code >= 200 && $code < 300){ respond(true, ['result'=>$json]); }
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
    if ($err) respond(false, ['error'=>'Curl: '.$err], 500);
    $json = json_decode($resp, true);
    if ($code >= 200 && $code < 300){ respond(true, ['result'=>$json]); }
    respond(false, ['error'=>'Airtable '.$code, 'response'=>$json], $code ?: 500);
  }

  // Not implemented actions yet
  respond(false, ['error'=>'Action not implemented'], 400);
}

respond(false, ['error'=>'Provider not implemented'], 400);


