<?php
// Read selected parts of server config as JSON for admin UI
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond($ok, $data=[], $code=200){ http_response_code($code); echo json_encode(['ok'=>$ok]+$data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'GET') respond(false, ['error'=>'Invalid method'], 405);
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') respond(false, ['error'=>'HTTPS required'], 403);
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref && strpos($ref, (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/') !== 0) respond(false, ['error'=>'Invalid origin'], 403);

// Optional admin token protection
$adminTokenCfg = __DIR__.'/admin-token.php';
if (file_exists($adminTokenCfg)){
  $cfgToken = require $adminTokenCfg; $cfgToken = is_array($cfgToken)?($cfgToken['token']??''):'{}';
  $hdrToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
  if (!$cfgToken || !$hdrToken || !hash_equals($cfgToken, $hdrToken)){
    respond(false, ['error'=>'Auth token required'], 401);
  }
}

$cfgFile = __DIR__.'/config.php';
$cfg = [];
if (file_exists($cfgFile)) { $cfg = require $cfgFile; if (!is_array($cfg)) $cfg = []; }

$out = [
  'databases' => [],
  'airtable_registry' => null
];
if (!empty($cfg['databases']) && is_array($cfg['databases'])){
  foreach ($cfg['databases'] as $scope=>$data){
    if (!is_array($data)) continue;
    $out['databases'][$scope] = array_intersect_key($data, array_flip(['provider','base_id','table_id']));
  }
  // Fallback: построить минимальный airtable_registry из databases, если реестр не сохранён
  if (empty($cfg['airtable_registry'])){
    $reg = [ 'baseId' => '', 'tables' => [ 'country'=>[], 'region'=>[], 'city'=>[], 'poi'=>[] ] ];
    if (!empty($cfg['databases']['regions']['base_id'])) $reg['baseId'] = $cfg['databases']['regions']['base_id'];
    if (!empty($cfg['databases']['regions']['table_id'])) $reg['tables']['region']['tableId'] = $cfg['databases']['regions']['table_id'];
    // опционально: если в databases есть другие сущности
    foreach (['country','city','poi'] as $k){
      if (!empty($cfg['databases'][$k]['table_id'])) $reg['tables'][$k]['tableId'] = $cfg['databases'][$k]['table_id'];
    }
    $out['airtable_registry'] = $reg;
  } else {
    $out['airtable_registry'] = $cfg['airtable_registry'];
  }
} else {
  // если databases отсутствует, но есть сохранённый реестр, отдадим его
  if (!empty($cfg['airtable_registry'])) $out['airtable_registry'] = $cfg['airtable_registry'];
}

respond(true, $out);


