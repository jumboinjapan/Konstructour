<?php
// Write server-side API credentials into api/config.php (merging providers)
// NOTE: Minimal gating: HTTPS + same-origin referer + POST JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond($ok, $data=[], $code=200){ http_response_code($code); echo json_encode(['ok'=>$ok]+$data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, ['error'=>'Invalid method'], 405);
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') respond(false, ['error'=>'HTTPS required'], 403);
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref && strpos($ref, (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/') !== 0) respond(false, ['error'=>'Invalid origin'], 403);

// Optional admin token protection: create api/admin-token.php returning ['token'=>'...']
$adminTokenCfg = __DIR__.'/admin-token.php';
if (file_exists($adminTokenCfg)){
  $cfgToken = require $adminTokenCfg; $cfgToken = is_array($cfgToken)?($cfgToken['token']??''):'';
  $hdrToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
  if (!$cfgToken || !$hdrToken || !hash_equals($cfgToken, $hdrToken)){
    respond(false, ['error'=>'Auth token required'], 401);
  }
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!$payload || !is_array($payload)) respond(false, ['error'=>'Invalid JSON'], 400);

$providersAllowed = ['openai','airtable','gsheets','gmaps','recaptcha','brilliantdb','databases'];

// Bootstrap: allow setting admin token once if file does not exist yet
$adminTokenFile = __DIR__.'/admin-token.php';
if (!file_exists($adminTokenFile) && !empty($payload['admin_token'])){
  $tokenExport = "<?php\nreturn ['token'=>'".str_replace(["\\","'"],["\\\\","\\'"], $payload['admin_token'])."'];\n";
  if (file_put_contents($adminTokenFile, $tokenExport) === false) {
    respond(false, ['error'=>'Token write failed'], 500);
  }
  @chmod($adminTokenFile, 0600);
}
$incoming = array_intersect_key($payload, array_flip($providersAllowed));
if (!$incoming) respond(false, ['error'=>'No providers'], 400);

// Load current
$cfg = [];
$cfgFile = __DIR__.'/config.php';
if (file_exists($cfgFile)) { $cfg = require $cfgFile; if (!is_array($cfg)) $cfg = []; }

// Merge
foreach ($incoming as $prov=>$data){
  if (!is_array($data)) continue;
  if ($prov === 'databases'){
    // Merge per-scope (regions/tours/templates)
    $cfg['databases'] = $cfg['databases'] ?? [];
    foreach ($data as $scope=>$scopeData){
      if (!is_array($scopeData)) continue;
      $cfg['databases'][$scope] = array_merge($cfg['databases'][$scope] ?? [], $scopeData);
    }
  } else {
    $cfg[$prov] = array_merge($cfg[$prov] ?? [], $data);
  }
}

// Backup current config (retain last 10)
$backupsDir = __DIR__.'/backups';
if (!is_dir($backupsDir)) { @mkdir($backupsDir, 0700, true); }
if (file_exists($cfgFile)){
  $stamp = date('Ymd-His');
  @copy($cfgFile, $backupsDir.'/config.php.'.$stamp.'.bak');
  // prune old backups
  $files = @glob($backupsDir.'/config.php.*.bak');
  if (is_array($files) && count($files) > 10){
    sort($files); // oldest first
    foreach (array_slice($files, 0, count($files)-10) as $old){ @unlink($old); }
  }
}

// Export to PHP file with safe perms (atomic write)
$export = "<?php\nreturn ".var_export($cfg, true).";\n";
$tmp = $cfgFile.'.tmp';
if (file_put_contents($tmp, $export) === false) respond(false, ['error'=>'Write failed (tmp)'], 500);
if (!@rename($tmp, $cfgFile)) { @unlink($tmp); respond(false, ['error'=>'Write failed (rename)'], 500); }
@chmod($cfgFile, 0600);

respond(true, ['message'=>'Saved','providers'=>array_keys($incoming)]);


