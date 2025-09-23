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
// Resolve presence of keys
$presence = [
  'openai' => !empty($cfg['openai']['api_key'] ?? ''),
  'airtable' => !empty($cfg['airtable']['api_key'] ?? '')
               || !empty($cfg['airtable']['token'] ?? '')
               || !empty($cfg['airtable_pat'] ?? ''),
  'gsheets' => !empty($cfg['gsheets']['api_key'] ?? ''),
  'gmaps' => !empty($cfg['gmaps']['api_key'] ?? ''),
  'recaptcha' => !empty($cfg['recaptcha']['secret'] ?? ''),
  'brilliantdirectory' => !empty($cfg['brilliantdb']['api_key'] ?? ''),
];

// Do NOT hit external APIs (real keys in use). Report presence as connectivity state.
$providers = ['openai','airtable','gsheets','gmaps','recaptcha','brilliantdirectory'];
$results = [];
foreach ($providers as $p){
  $now = time();
  if (empty($presence[$p])) { $results[$p] = [ 'ok' => null, 'text' => 'Ожидание', 'checked_at' => $now ]; continue; }

  // По умолчанию: наличие ключа = ок (для большинства провайдеров)
  $ok = true; $text = 'Подключено';

  if ($p === 'airtable'){
    // Для Airtable делаем реальный лёгкий тест: whoami, а если задана таблица — один запрос к таблице
    $pat = $cfg['airtable']['api_key'] ?? ($cfg['airtable']['token'] ?? ($cfg['airtable_pat'] ?? ''));
    $airReg = $cfg['airtable_registry'] ?? [];
    $baseId = $airReg['baseId'] ?? ($airReg['base_id'] ?? ($cfg['airtable']['base_id'] ?? ''));
    // Соберём список всех таблиц из mapping: country, region, city, poi
    $tablesToCheck = [];
    if (!empty($airReg['tables']) && is_array($airReg['tables'])){
      foreach (['country','region','city','poi'] as $k){
        $tid = $airReg['tables'][$k]['tableId'] ?? '';
        if ($tid) $tablesToCheck[] = $tid;
      }
    } elseif (!empty($cfg['airtable']['table'])) {
      $tablesToCheck[] = $cfg['airtable']['table'];
    }

    $ok = false; $text = 'Ошибка';
    if ($pat){
      // 1) whoami
      $ch = curl_init('https://api.airtable.com/v0/meta/whoami');
      curl_setopt_array($ch, [ CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$pat], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8 ]);
      $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
      if ($code>=200 && $code<300){
        $ok = true; $text = 'Подключено';
        // 2) если есть baseId и таблицы — проверим каждую (1 запись)
        if ($baseId && !empty($tablesToCheck)){
          foreach ($tablesToCheck as $tbl){
            $url = 'https://api.airtable.com/v0/'.rawurlencode($baseId).'/'.rawurlencode($tbl).'?maxRecords=1';
            $ch = curl_init($url);
            curl_setopt_array($ch, [ CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$pat], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8 ]);
            $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            if (!($code>=200 && $code<300)) {
              $ok=false; $text='Ошибка';
              // логируем отказ проверки конкретной таблицы
              @file_get_contents(__DIR__.'/error-log.php?action=add', false, stream_context_create(['http'=>[
                'method'=>'POST','header'=>"Content-Type: application/json\r\n", 'content'=>json_encode(['type'=>'error','msg'=>'Airtable health table check failed','ctx'=>['table'=>$tbl,'code'=>$code]])
              ]]));
              break;
            }
          }
        }
      } else if ($code===401){
        $ok = false; $text = '401 Unauthorized';
        @file_get_contents(__DIR__.'/error-log.php?action=add', false, stream_context_create(['http'=>[
          'method'=>'POST','header'=>"Content-Type: application/json\r\n", 'content'=>json_encode(['type'=>'error','msg'=>'Airtable whoami unauthorized','ctx'=>['code'=>$code]])
        ]]));
      }
    }
  }

  $results[$p] = [ 'ok' => $ok, 'text' => $text, 'checked_at' => $now ];
}

$payload = [ 'ts' => time(), 'ttl' => $CACHE_TTL, 'results' => $results ];
@file_put_contents($CACHE_FILE, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
respond($payload);


