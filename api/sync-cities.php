<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/_airtable-common.php';

try {
  // ==== КОНФИГ ====
  $BASE_ID = getenv('AIRTABLE_BASE_ID') ?: 'apppwhjFN82N9zNqm';
  $TABLE_ID_CITIES = getenv('AIRTABLE_TABLE_ID_CITIES') ?: 'tblHaHc9NV0mA8bSa'; // замените при необходимости

  $F_ID      = getenv('AIR_C_ID')      ?: 'ID';
  $F_RU      = getenv('AIR_C_RU')      ?: 'Name (RU)';
  $F_EN      = getenv('AIR_C_EN')      ?: 'Name (EN)';
  $F_REGION  = getenv('AIR_C_REGION')  ?: 'Region ID';
  $F_LAT     = getenv('AIR_C_LAT')     ?: 'Lat';
  $F_LNG     = getenv('AIR_C_LNG')     ?: 'Lng';
  $F_PLACEID = getenv('AIR_C_PLACEID') ?: 'Google Place ID';
  $F_UPDATED = getenv('AIR_C_UPDATED') ?: 'updated_at';
  $F_DELETED = getenv('AIR_C_DELETED') ?: 'is_deleted';

  // ==== SQLite ====
  $dbPath = getenv('SQLITE_PATH') ?: (__DIR__.'/../data/constructour.db');
  @mkdir(dirname($dbPath), 0775, true);
  $pdo = new PDO('sqlite:'.$dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $pdo->exec("CREATE TABLE IF NOT EXISTS sync_state (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
  $pdo->prepare("INSERT OR IGNORE INTO sync_state(key,value) VALUES('last_sync_cities','1970-01-01T00:00:00Z')")->execute();

  $getState = $pdo->prepare("SELECT value FROM sync_state WHERE key='last_sync_cities'");
  $setState = $pdo->prepare("REPLACE INTO sync_state(key,value) VALUES('last_sync_cities',?)");

  $nowIso = gmdate('c');
  $lastSync = ($getState->execute() && ($r=$getState->fetch())) ? $r['value'] : '1970-01-01T00:00:00Z';
  $full = (($_GET['full'] ?? '')==='1') || (($_POST['full'] ?? '')==='1');
  if ($full) $lastSync = '1970-01-01T00:00:00Z';

  // ==== Airtable list ====
  $cfg = air_cfg($TABLE_ID_CITIES); // не используется напрямую в air_call
  $pulled = 0; $offset=null; $remote = [];
  do {
    $params = ['pageSize'=>100];
    if (!$full && $F_UPDATED) $params['filterByFormula'] = "VALUE({$F_UPDATED}) > VALUE(\"{$lastSync}\")";
    if ($offset) $params['offset']=$offset;
    [$code,$out,$err,$url] = air_call('GET', '', null, $params);
    if ($code>=400) {
      http_response_code(500);
      echo json_encode(['ok'=>false,'error'=>"Airtable $code on list",'url'=>$url,'details'=>json_decode($out,true)?:$out,'summary'=>null], JSON_UNESCAPED_UNICODE);
      exit;
    }
    $j = json_decode($out,true);
    foreach (($j['records']??[]) as $rec){
      $f = $rec['fields'] ?? [];
      $remote[] = [
        'airtable_id'  => $rec['id'],
        'identifier'   => strval($f[$F_ID] ?? ''),
        'name_ru'      => strval($f[$F_RU] ?? ''),
        'name_en'      => strval($f[$F_EN] ?? ''),
        'region_ident' => strval($f[$F_REGION] ?? ''),
        'lat'          => isset($f[$F_LAT]) ? floatval($f[$F_LAT]) : null,
        'lng'          => isset($f[$F_LNG]) ? floatval($f[$F_LNG]) : null,
        'place_id'     => strval($f[$F_PLACEID] ?? ''),
        'updated_at'   => strval($f[$F_UPDATED] ?? $rec['createdTime']),
        'is_deleted'   => !empty($f[$F_DELETED]) ? 1 : 0,
      ];
    }
    $pulled += count($j['records'] ?? []);
    $offset = $j['offset'] ?? null;
  } while ($offset);

  // ==== Local upserts (Airtable -> SQLite) — без ON CONFLICT (совместимо со старыми SQLite)
  $pdo->exec("CREATE TABLE IF NOT EXISTS cities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier TEXT NOT NULL UNIQUE,
    name_ru TEXT NOT NULL,
    name_en TEXT NOT NULL,
    region_ident TEXT,
    lat REAL, lng REAL, place_id TEXT,
    airtable_id TEXT UNIQUE,
    updated_at TEXT NOT NULL,
    is_deleted INTEGER NOT NULL DEFAULT 0
  )");

  $selId = $pdo->prepare("SELECT id FROM cities WHERE identifier=?");
  $ins   = $pdo->prepare("INSERT INTO cities (identifier,name_ru,name_en,region_ident,lat,lng,place_id,airtable_id,updated_at,is_deleted)
                          VALUES (?,?,?,?,?,?,?,?,?,?)");
  $upd   = $pdo->prepare("UPDATE cities SET name_ru=?,name_en=?,region_ident=?,lat=?,lng=?,place_id=?,airtable_id=COALESCE(?,airtable_id),updated_at=?,is_deleted=? WHERE identifier=?");

  $updated_local = 0;
  $pdo->beginTransaction();
  foreach ($remote as $r){
    if ($r['identifier']==='') continue;
    $selId->execute([$r['identifier']]);
    $exists = $selId->fetchColumn();
    if ($exists) {
      $upd->execute([
        $r['name_ru'], $r['name_en'], $r['region_ident'],
        $r['lat'], $r['lng'], $r['place_id'],
        $r['airtable_id'], $r['updated_at'], $r['is_deleted'],
        $r['identifier']
      ]);
    } else {
      $ins->execute([
        $r['identifier'], $r['name_ru'], $r['name_en'], $r['region_ident'],
        $r['lat'], $r['lng'], $r['place_id'], $r['airtable_id'],
        $r['updated_at'], $r['is_deleted']
      ]);
    }
    $updated_local++;
  }
  $pdo->commit();

  // ==== SQLite -> Airtable (incremental or full)
  $selLocal = $pdo->prepare("SELECT * FROM cities WHERE updated_at > ?");
  $selLocal->execute([$lastSync]);
  $locals = $selLocal->fetchAll();
  $pushed = 0; $updated_air = 0;

  foreach ($locals as $loc){
    $fields = [
      $F_ID      => $loc['identifier'],
      $F_RU      => $loc['name_ru'],
      $F_EN      => $loc['name_en'],
      $F_REGION  => $loc['region_ident'],
      $F_LAT     => $loc['lat'],
      $F_LNG     => $loc['lng'],
      $F_PLACEID => $loc['place_id'],
      $F_UPDATED => $loc['updated_at'],
      $F_DELETED => !!$loc['is_deleted']
    ];
    if ($loc['airtable_id']) {
      [$c,$o,$e,$u] = air_call('PATCH', $loc['airtable_id'], ['fields'=>$fields]);
      if ($c>=400) continue; $updated_air++;
    } else {
      [$c,$o,$e,$u] = air_call('POST', '', ['fields'=>$fields]);
      if ($c>=400) continue; $resp = json_decode($o,true);
      if (!empty($resp['id'])) $pdo->prepare("UPDATE cities SET airtable_id=? WHERE identifier=?")->execute([$resp['id'],$loc['identifier']]);
      $pushed++;
    }
  }

  $setState->execute([$nowIso]);

  // Итоговые количества для наглядности
  $local_total = (int)$pdo->query("SELECT COUNT(*) FROM cities WHERE is_deleted=0")->fetchColumn();
  $remote_total = (int)$pulled; // при full=1 это общее число записей в Airtable

  echo json_encode([
    'ok'=>true,
    'summary'=>[
      'started_at'=> $lastSync,
      'finished_at'=> $nowIso,
      'pulled'=> $pulled,
      'updated_local'=> $updated_local,
      'pushed'=> $pushed,
      'updated_air'=> $updated_air,
      'deleted_local'=> 0,
      'deleted_air'=> 0,
      'remote_total'=> $remote_total,
      'local_total'=> $local_total
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'summary'=>null], JSON_UNESCAPED_UNICODE);
}
?>
