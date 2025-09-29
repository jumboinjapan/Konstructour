<?php
// /api/bidirectional-sync-v2.php — Реальный двусторонний синк РЕГИОНОВ (без identifier)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/_airtable-common.php';

$F_ID = getenv('AIR_F_ID') ?: 'fldwlHyd89p3lRsQe'; // Business ID
$F_RU = getenv('AIR_F_RU') ?: 'Name (RU)';
$F_EN = getenv('AIR_F_EN') ?: 'Name (EN)';
$F_UPDATED = getenv('AIR_R_UPDATED') ?: 'updated_at';
$F_DELETED = getenv('AIR_R_DELETED') ?: 'is_deleted';

function ok($p){ echo json_encode($p, JSON_UNESCAPED_UNICODE); exit; }
function fail($m,$e=[]){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>$m]+$e, JSON_UNESCAPED_UNICODE); exit; }

try {
  $t0 = microtime(true);
  $startedIso = gmdate('c');
  $full = ((($_GET['full'] ?? '')==='1') || (($_POST['full'] ?? '')==='1'));

  $cfg = air_cfg();

  $dbPath = getenv('SQLITE_PATH') ?: (__DIR__ . '/../data/constructour.db');
  @mkdir(dirname($dbPath), 0775, true);
  $pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  // ensure schema regions
  $pdo->exec("CREATE TABLE IF NOT EXISTS regions (
    id TEXT PRIMARY KEY,
    name_ru TEXT NOT NULL,
    name_en TEXT,
    business_id TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )");
  $pdo->exec("CREATE TABLE IF NOT EXISTS sync_state (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
  $pdo->prepare("INSERT OR IGNORE INTO sync_state(key,value) VALUES('last_sync_regions','1970-01-01T00:00:00Z')")->execute();
  $getState = $pdo->prepare("SELECT value FROM sync_state WHERE key='last_sync_regions'");
  $setState = $pdo->prepare("REPLACE INTO sync_state(key,value) VALUES('last_sync_regions',?)");
  $lastSync = ($getState->execute() && ($r=$getState->fetch())) ? $r['value'] : '1970-01-01T00:00:00Z';
  if ($full) $lastSync = '1970-01-01T00:00:00Z';

  // подтягиваем только 9 ID (жёсткое зеркало)
  $allowed = ['REG-0001','REG-0002','REG-0003','REG-0004','REG-0005','REG-0006','REG-0007','REG-0008','REG-0009'];
  if (!empty($_GET['ids'])) $allowed = array_values(array_filter(array_map('trim', explode(',', $_GET['ids']))));
  $parts = array_map(fn($v)=>"{${F_ID}}='".addslashes($v)."'", $allowed);
  $formula = 'OR('.implode(',', $parts).')';

  // pull from Airtable
  $pulled = 0; $offset = null; $remote = [];
  do {
    $params = ['pageSize'=>100, 'filterByFormula'=>$formula];
    if ($offset) $params['offset']=$offset;
    [$code,$out,$err,$url] = air_call('GET','', null, $params);
    if ($code>=400) fail("Airtable $code on list", ['url'=>$url,'air_resp'=>json_decode($out,true)?:$out,'summary'=>null]);
    $j = json_decode($out,true);
    foreach (($j['records']??[]) as $rec){
      $f = $rec['fields'] ?? [];
      $remote[] = [
        'id'        => strval($f[$F_ID] ?? ''),
        'name_ru'   => strval($f[$F_RU] ?? ''),
        'name_en'   => strval($f[$F_EN] ?? ''),
        'updated_at'=> strval($f[$F_UPDATED] ?? $rec['createdTime'])
      ];
    }
    $pulled += count($j['records'] ?? []);
    $offset = $j['offset'] ?? null;
  } while ($offset);

  // upsert
  $sel = $pdo->prepare('SELECT id,name_ru,name_en,updated_at FROM regions WHERE id=?');
  $ins = $pdo->prepare('INSERT INTO regions (id,name_ru,name_en,business_id,updated_at) VALUES (?,?,?,?,?)');
  $upd = $pdo->prepare('UPDATE regions SET name_ru=?,name_en=?,business_id=?,updated_at=? WHERE id=?');

  $pdo->beginTransaction();
  foreach ($remote as $r){
    if ($r['id']==='') continue;
    $sel->execute([$r['id']]);
    $row = $sel->fetch();
    if ($row){
      if ($row['name_ru']!==$r['name_ru'] || $row['name_en']!==$r['name_en'] || $row['updated_at']!==$r['updated_at']){
        $upd->execute([$r['name_ru'],$r['name_en'],$r['id'],$r['updated_at'],$r['id']]);
      }
    } else {
      $ins->execute([$r['id'],$r['name_ru'],$r['name_en'],$r['id'],$r['updated_at']]);
    }
  }
  // delete others not in allowed
  $in = "('".implode("','", array_map('addslashes', $allowed))."')";
  $pdo->exec("DELETE FROM regions WHERE id NOT IN $in");
  $pdo->commit();

  $setState->execute([gmdate('c')]);
  ok(['ok'=>true,'summary'=>['pulled'=>$pulled,'kept'=>count($allowed),'deleted'=>'done']]);

} catch (Throwable $e) {
  fail($e->getMessage(), ['summary'=>null]);
}
?>
