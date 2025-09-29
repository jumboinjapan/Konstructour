<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/_airtable-common.php';

function ok($p){ echo json_encode($p, JSON_UNESCAPED_UNICODE); exit; }
function fail($m,$e=[]){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>$m]+$e, JSON_UNESCAPED_UNICODE); exit; }

try {
  // 1) Вход: список REG-ids
  $body = file_get_contents('php://input');
  $ids = [];
  if ($body) { $json = json_decode($body,true); if (is_array($json['ids'] ?? null)) $ids = $json['ids']; }
  if (!$ids) {
    // fallback на жёсткие 9 ID
    $ids = ['REG-0001','REG-0002','REG-0003','REG-0004','REG-0005','REG-0006','REG-0007','REG-0008','REG-0009'];
  }

  $cfg = air_cfg();
  $dbPath = getenv('SQLITE_PATH') ?: (__DIR__.'/../data/constructour.db');
  @mkdir(dirname($dbPath), 0775, true);
  $pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  // ensure schema
  $pdo->exec("CREATE TABLE IF NOT EXISTS regions (
    id TEXT PRIMARY KEY,
    name_ru TEXT NOT NULL,
    name_en TEXT,
    business_id TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )");

  // 2) Тянем ровно эти записи по формуле
  // fieldId business ID для Regions
  $F_ID = getenv('AIR_F_ID') ?: 'fldwlHyd89p3lRsQe';
  $pulled = 0; $remote = [];
  // Airtable допускает формулу OR({ID}="REG-0001",{ID}="REG-0002",...)
  $parts = array_map(fn($v)=>"{${F_ID}}='".addslashes($v)."'", $ids);
  $formula = 'OR('.implode(',', $parts).')';

  $params = ['pageSize'=>100, 'filterByFormula'=>$formula];
  [$code,$out,$err,$url] = air_call('GET','', null, $params);
  if ($code>=400) fail("Airtable $code", ['url'=>$url,'air_resp'=>json_decode($out,true)?:$out]);
  $j = json_decode($out,true);
  foreach (($j['records']??[]) as $rec){
    $f = $rec['fields'] ?? [];
    $remote[] = [
      'id'        => $f[$F_ID] ?? '',
      'name_ru'   => $f['Name (RU)'] ?? '',
      'name_en'   => $f['Name (EN)'] ?? '',
    ];
  }
  $pulled = count($remote);

  // 3) Апсерт и удаление лишних
  $sel = $pdo->prepare('SELECT id,name_ru,name_en FROM regions WHERE id=?');
  $ins = $pdo->prepare('INSERT INTO regions (id,name_ru,name_en,business_id,updated_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)');
  $upd = $pdo->prepare('UPDATE regions SET name_ru=?,name_en=?,business_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');

  $pdo->beginTransaction();
  $seen = [];
  foreach ($remote as $r){
    $rid = $r['id']; if ($rid==='') continue; $seen[$rid]=true;
    $sel->execute([$rid]); $row = $sel->fetch();
    if ($row) {
      if ($row['name_ru']!==$r['name_ru'] || $row['name_en']!==$r['name_en']) {
        $upd->execute([$r['name_ru'],$r['name_en'],$rid,$rid]);
      }
    } else {
      $ins->execute([$rid,$r['name_ru'],$r['name_en'],$rid]);
    }
  }
  // delete others
  $in  = "('".implode("','", array_map('addslashes', $ids))."')";
  $pdo->exec("DELETE FROM regions WHERE id NOT IN $in");
  $pdo->commit();

  ok(['ok'=>true,'summary'=>['pulled'=>$pulled,'kept'=>count($seen),'deleted'=>'done']]);

} catch (Throwable $e) {
  fail($e->getMessage());
}
?>
