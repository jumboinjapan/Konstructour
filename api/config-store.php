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

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!$payload || !is_array($payload)) respond(false, ['error'=>'Invalid JSON'], 400);

$providersAllowed = ['openai','airtable','gsheets','gmaps','recaptcha','brilliantdb'];
$incoming = array_intersect_key($payload, array_flip($providersAllowed));
if (!$incoming) respond(false, ['error'=>'No providers'], 400);

// Load current
$cfg = [];
$cfgFile = __DIR__.'/config.php';
if (file_exists($cfgFile)) { $cfg = require $cfgFile; if (!is_array($cfg)) $cfg = []; }

// Merge
foreach ($incoming as $prov=>$data){ if (is_array($data)) { $cfg[$prov] = array_merge($cfg[$prov] ?? [], $data); } }

// Export to PHP file with safe perms
$export = "<?php\nreturn ".var_export($cfg, true).";\n";
if (file_put_contents($cfgFile, $export) === false) respond(false, ['error'=>'Write failed'], 500);
@chmod($cfgFile, 0600);

respond(true, ['message'=>'Saved','providers'=>array_keys($incoming)]);


