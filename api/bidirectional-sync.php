<?php
// /api/bidirectional-sync.php — Реальный двусторонний синк РЕГИОНОВ
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/_airtable-common.php';

// Маппинг полей Airtable
$F_ID = getenv('AIR_F_ID') ?: 'ID';
$F_RU = getenv('AIR_F_RU') ?: 'Name (RU)';
$F_EN = getenv('AIR_F_EN') ?: 'Name (EN)';
$F_UPDATED = getenv('AIR_R_UPDATED') ?: 'updated_at';
$F_DELETED = getenv('AIR_R_DELETED') ?: 'is_deleted';

// Настройки БД
$dbPath = getenv('SQLITE_PATH') ?: (__DIR__ . '/../data/constructour.db');
@mkdir(dirname($dbPath), 0775, true);

function ok($p){ echo json_encode($p, JSON_UNESCAPED_UNICODE); exit; }
function fail($m,$e=[]){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>$m]+$e, JSON_UNESCAPED_UNICODE); exit; }

try {
  $t0 = microtime(true);
  $startedIso = gmdate('c');
  $full = ((($_GET['full'] ?? '')==='1') || (($_POST['full'] ?? '')==='1'));

  // 0) Проверка доступа к Airtable
  $cfg = air_cfg();

  // 1) Подключение к БД
  $pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $pdo->exec("CREATE TABLE IF NOT EXISTS sync_state (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
  $pdo->prepare("INSERT OR IGNORE INTO sync_state(key,value) VALUES('last_sync_regions','1970-01-01T00:00:00Z')")->execute();
  $getState = $pdo->prepare("SELECT value FROM sync_state WHERE key='last_sync_regions'");
  $setState = $pdo->prepare("REPLACE INTO sync_state(key,value) VALUES('last_sync_regions',?)");
  $lastSync = ($getState->execute() && ($r=$getState->fetch())) ? $r['value'] : '1970-01-01T00:00:00Z';
  if ($full) $lastSync = '1970-01-01T00:00:00Z';

  // 2) Тянем из Airtable
  $pulled = 0; $offset=null; $remote=[];
  do {
    $params = ['pageSize'=>100];
    if (!$full && $F_UPDATED) $params['filterByFormula'] = "VALUE({$F_UPDATED}) > VALUE(\"{$lastSync}\")";
    if ($offset) $params['offset']=$offset;
    [$code,$out,$err,$url] = air_call('GET','', null, $params);
    if ($code>=400) fail("Airtable $code on list", ['url'=>$url,'air_resp'=>json_decode($out,true)?:$out,'summary'=>null]);
    $j = json_decode($out,true);
    foreach (($j['records']??[]) as $rec){
      $f = $rec['fields'] ?? [];
      $remote[] = [
        'airtable_id' => $rec['id'],
        'identifier'  => strval($f[$F_ID] ?? ''),
        'name_ru'     => strval($f[$F_RU] ?? ''),
        'name_en'     => strval($f[$F_EN] ?? ''),
        'updated_at'  => strval($f[$F_UPDATED] ?? $rec['createdTime']),
        'is_deleted'  => !empty($f[$F_DELETED]) ? 1 : 0,
      ];
    }
    $pulled += count($j['records'] ?? []);
    $offset = $j['offset'] ?? null;
  } while ($offset);

  // 3) Апсерт в SQLite (без ON CONFLICT)
  $pdo->exec("CREATE TABLE IF NOT EXISTS regions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier TEXT NOT NULL UNIQUE,
    name_ru TEXT NOT NULL,
    name_en TEXT NOT NULL,
    airtable_id TEXT UNIQUE,
    updated_at TEXT NOT NULL,
    is_deleted INTEGER NOT NULL DEFAULT 0
  )");

  $selId = $pdo->prepare("SELECT id,name_ru,name_en,updated_at FROM regions WHERE identifier=?");
  $ins   = $pdo->prepare("INSERT INTO regions (identifier,name_ru,name_en,airtable_id,updated_at,is_deleted) VALUES (?,?,?,?,?,?)");
  $upd   = $pdo->prepare("UPDATE regions SET name_ru=?,name_en=?,airtable_id=COALESCE(?,airtable_id),updated_at=?,is_deleted=? WHERE identifier=?");

  $updated_local = 0; $inserted_local = 0;
  $pdo->beginTransaction();
  foreach ($remote as $r){
    if ($r['identifier']==='') continue;
    $selId->execute([$r['identifier']]);
    $row = $selId->fetch();
    if ($row) {
      // обновляем только если действительно изменилось
      if ($row['name_ru']!==$r['name_ru'] || $row['name_en']!==$r['name_en'] || $row['updated_at']!==$r['updated_at']) {
        $upd->execute([$r['name_ru'],$r['name_en'],$r['airtable_id'],$r['updated_at'],$r['is_deleted'],$r['identifier']]);
        $updated_local++;
      }
    } else {
      $ins->execute([$r['identifier'],$r['name_ru'],$r['name_en'],$r['airtable_id'],$r['updated_at'],$r['is_deleted']]);
      $inserted_local++;
    }
  }
  $pdo->commit();

  // 4) Локальные изменения → Airtable
  $selLocal = $pdo->prepare("SELECT * FROM regions WHERE updated_at > ?");
  $selLocal->execute([$lastSync]);
  $locals = $selLocal->fetchAll();
  $pushed = 0; $updated_air = 0;
  foreach ($locals as $loc){
    $fields = [ $F_ID=>$loc['identifier'], $F_RU=>$loc['name_ru'], $F_EN=>$loc['name_en'], $F_UPDATED=>$loc['updated_at'], $F_DELETED=>!!$loc['is_deleted'] ];
    if ($loc['airtable_id']) {
      [$c,$o,$e,$u] = air_call('PATCH', $loc['airtable_id'], ['fields'=>$fields]);
      if ($c<400) $updated_air++;
    } else {
      [$c,$o,$e,$u] = air_call('POST', '', ['fields'=>$fields]);
      if ($c<400) { $resp = json_decode($o,true); if (!empty($resp['id'])) { $pdo->prepare("UPDATE regions SET airtable_id=? WHERE identifier=?")->execute([$resp['id'],$loc['identifier']]); } $pushed++; }
    }
  }

  // 5) Завершение
  $setState->execute([gmdate('c')]);
  $summary = [
    'started_at'    => $startedIso,
    'finished_at'   => gmdate('c'),
    'duration_ms'   => (int) ((microtime(true)-$t0)*1000),
    'pulled'        => $pulled,
    'pushed'        => $pushed,
    'updated_local' => $updated_local,
    'updated_air'   => $updated_air,
    'deleted_local' => 0,
    'deleted_air'   => 0
  ];
  ok(['ok'=>true,'summary'=>$summary]);

} catch (Throwable $e) {
  fail($e->getMessage(), ['summary'=>null]);
}
?>
