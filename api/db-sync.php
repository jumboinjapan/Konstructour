<?php
// Minimal DB sync proxy (Airtable create/list placeholder)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond($ok, $data=[], $code=200){ http_response_code($code); echo json_encode(['ok'=>$ok]+$data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, ['error'=>'Invalid method'], 405);
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') respond(false, ['error'=>'HTTPS required'], 403);
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref && strpos($ref, (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/') !== 0) respond(false, ['error'=>'Invalid origin'], 403);

// Optional admin token
$adminTokenCfg = __DIR__.'/admin-token.php';
if (file_exists($adminTokenCfg)){
  $cfgToken = require $adminTokenCfg; $cfgToken = is_array($cfgToken)?($cfgToken['token']??''):'{}';
  $hdrToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
  if (!$cfgToken || !$hdrToken || !hash_equals($cfgToken, $hdrToken)){
    respond(false, ['error'=>'Auth token required'], 401);
  }
}

$raw = file_get_contents('php://input');
$req = json_decode($raw, true);
if (!$req || !is_array($req)) respond(false, ['error'=>'Invalid JSON'], 400);

$scope = $req['scope'] ?? '';
$action = $req['action'] ?? '';
$payload = $req['data'] ?? [];
if (!$scope || !$action) respond(false, ['error'=>'Missing params'], 400);

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

  if ($action === 'create'){
    // Accept either fields directly or shorthand payload
    $fields = $payload['fields'] ?? null;
    if (!$fields){
      $name = $payload['name'] ?? '';
      if ($name){ $fields = ['Name'=>$name]; }
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

  // Not implemented actions yet
  respond(false, ['error'=>'Action not implemented'], 400);
}

respond(false, ['error'=>'Provider not implemented'], 400);


