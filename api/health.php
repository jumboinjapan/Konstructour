<?php
// Aggregated API health check with simple file cache
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$CACHE_TTL = 120; // seconds
$CACHE_FILE = __DIR__.'/.health_cache.json';

function respond($data){ echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

// Serve fresh cache if valid
if (is_file($CACHE_FILE)){
  $raw = file_get_contents($CACHE_FILE);
  if ($raw){
    $cache = json_decode($raw, true);
    if ($cache && isset($cache['ts']) && (time() - $cache['ts'] < $CACHE_TTL)){
      respond($cache);
    }
  }
}

// Build probes (use existing test-proxy for consistency)
$providers = ['openai','airtable','gsheets','gmaps','recaptcha','brilliantdirectory'];
$results = [];
foreach ($providers as $p){
  $url = '/api/test-proxy.php?provider='.$p;
  $full = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $url;
  $ch = curl_init($full);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
  $resp = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $ok = false; $msg = '—';
  if ($err){ $ok = false; $msg = $err; }
  else {
    $j = json_decode($resp, true);
    if (is_array($j)){
      $ok = !empty($j['ok']);
      $msg = isset($j['error']) ? $j['error'] : (isset($j['status']) ? ('HTTP '.$j['status']) : ($ok ? 'OK' : ''));
    } else {
      $msg = 'Invalid JSON';
    }
  }
  $results[$p] = [ 'ok' => $ok, 'text' => $msg, 'checked_at' => time() ];
}

$payload = [ 'ts' => time(), 'ttl' => $CACHE_TTL, 'results' => $results ];
@file_put_contents($CACHE_FILE, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
respond($payload);


