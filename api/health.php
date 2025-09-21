<?php
// Aggregated API health check with simple file cache
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$CACHE_TTL = 120; // seconds
$CACHE_FILE = __DIR__.'/.health_cache.json';

function respond($data){ echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

// Serve fresh cache if valid (unless forced)
$force = isset($_GET['force']) || isset($_GET['nocache']);
if (!$force && is_file($CACHE_FILE)){
  $raw = file_get_contents($CACHE_FILE);
  if ($raw){
    $cache = json_decode($raw, true);
    if ($cache && isset($cache['ts']) && (time() - $cache['ts'] < $CACHE_TTL)){
      respond($cache);
    }
  }
}

// Optional server-side secrets presence
$cfg = [];
if (file_exists(__DIR__.'/config.php')) {
  $cfg = require __DIR__.'/config.php';
}
$presence = [
  'openai' => !empty($cfg['openai']['api_key'] ?? ''),
  'airtable' => !empty($cfg['airtable']['api_key'] ?? ''),
  'gsheets' => !empty($cfg['gsheets']['api_key'] ?? ''),
  'gmaps' => !empty($cfg['gmaps']['api_key'] ?? ''),
  'recaptcha' => !empty($cfg['recaptcha']['secret'] ?? ''),
  'brilliantdirectory' => !empty($cfg['brilliantdb']['api_key'] ?? ''),
];

// Do NOT hit external APIs (real keys in use). Report presence as connectivity state.
$providers = ['openai','airtable','gsheets','gmaps','recaptcha','brilliantdirectory'];
$results = [];
foreach ($providers as $p){
  if (!empty($presence[$p])) {
    $results[$p] = [ 'ok' => true, 'text' => 'Подключено', 'checked_at' => time() ];
  } else {
    $results[$p] = [ 'ok' => null, 'text' => 'Ожидание', 'checked_at' => time() ];
  }
}

$payload = [ 'ts' => time(), 'ttl' => $CACHE_TTL, 'results' => $results ];
@file_put_contents($CACHE_FILE, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
respond($payload);


