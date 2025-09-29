<?php
// Чистый синхронизатор регионов и городов
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/_airtable-common.php';

function ok($p){ echo json_encode($p, JSON_UNESCAPED_UNICODE); exit; }
function fail($m,$e=[]){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>$m]+$e, JSON_UNESCAPED_UNICODE); exit; }

try {
  $startedAt = gmdate('c');
  $full = (($_GET['full'] ?? '') === '1');
  
  $cfg = air_cfg();
  $dbPath = getenv('SQLITE_PATH') ?: (__DIR__.'/../data/constructour.db');
  @mkdir(dirname($dbPath), 0775, true);
  $pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Создаем таблицы
  $pdo->exec("CREATE TABLE IF NOT EXISTS regions (
    id TEXT PRIMARY KEY,
    name_ru TEXT NOT NULL,
    name_en TEXT,
    business_id TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )");
  
  $pdo->exec("CREATE TABLE IF NOT EXISTS cities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    business_id TEXT NOT NULL UNIQUE,
    name_ru TEXT NOT NULL,
    name_en TEXT,
    region_ident TEXT,
    lat REAL, lng REAL, place_id TEXT,
    airtable_id TEXT UNIQUE,
    updated_at TEXT NOT NULL,
    is_deleted INTEGER NOT NULL DEFAULT 0
  )");

  $summary = [
    'regions' => ['pulled' => 0, 'updated' => 0, 'created' => 0, 'deleted' => 0],
    'cities' => ['pulled' => 0, 'updated' => 0, 'created' => 0, 'deleted' => 0]
  ];

  // === СИНХРОНИЗАЦИЯ РЕГИОНОВ ===
  $allowedRegions = ['REG-0001','REG-0002','REG-0003','REG-0004','REG-0005','REG-0006','REG-0007','REG-0008','REG-0009'];
  
  // Получаем регионы из Airtable
  $parts = [];
  foreach ($allowedRegions as $v) {
    $parts[] = "{fldwlHyd89p3lRsQe}='" . addslashes($v) . "'";
  }
  $formula = 'OR('.implode(',', $parts).')';
  
  $params = ['pageSize'=>100, 'filterByFormula'=>$formula];
  [$code,$out,$err,$url] = air_call('GET','', null, $params);
  if ($code>=400) fail("Airtable regions $code", ['url'=>$url]);
  
  $j = json_decode($out,true);
  $regions = [];
  foreach (($j['records']??[]) as $rec){
    $f = $rec['fields'] ?? [];
    $regions[] = [
      'id' => $f['fldwlHyd89p3lRsQe'] ?? '',
      'name_ru' => $f['Name (RU)'] ?? '',
      'name_en' => $f['Name (EN)'] ?? '',
    ];
  }
  $summary['regions']['pulled'] = count($regions);

  // Сохраняем регионы в БД
  $pdo->beginTransaction();
  $selReg = $pdo->prepare('SELECT id,name_ru,name_en FROM regions WHERE id=?');
  $insReg = $pdo->prepare('INSERT INTO regions (id,name_ru,name_en,business_id,updated_at) VALUES (?,?,?,?,?)');
  $updReg = $pdo->prepare('UPDATE regions SET name_ru=?,name_en=?,business_id=?,updated_at=? WHERE id=?');
  
  $seenRegions = [];
  foreach ($regions as $r) {
    if ($r['id'] === '') continue;
    $seenRegions[$r['id']] = true;
    
    $selReg->execute([$r['id']]);
    $row = $selReg->fetch();
    if ($row) {
      if ($row['name_ru'] !== $r['name_ru'] || $row['name_en'] !== $r['name_en']) {
        $updReg->execute([$r['name_ru'], $r['name_en'], $r['id'], $startedAt, $r['id']]);
        $summary['regions']['updated']++;
      }
    } else {
      $insReg->execute([$r['id'], $r['name_ru'], $r['name_en'], $r['id'], $startedAt]);
      $summary['regions']['created']++;
    }
  }
  
  // Удаляем лишние регионы
  $inReg = "('".implode("','", array_map('addslashes', $allowedRegions))."')";
  $summary['regions']['deleted'] = $pdo->exec("DELETE FROM regions WHERE id NOT IN $inReg");
  $pdo->commit();

  // === СИНХРОНИЗАЦИЯ ГОРОДОВ ===
  $params = ['pageSize'=>100];
  [$code,$out,$err,$url] = air_call('GET','tblbSajWkzI8X7M4U', null, $params);
  if ($code>=400) fail("Airtable cities $code", ['url'=>$url]);
  
  $j = json_decode($out,true);
  $cities = [];
  foreach (($j['records']??[]) as $rec){
    $f = $rec['fields'] ?? [];
    $cities[] = [
      'business_id' => $f['fldkJevgUbtAbM4vr'] ?? '',
      'name_ru' => $f['Name (RU)'] ?? '',
      'name_en' => $f['Name (EN)'] ?? '',
      'region_ident' => $f['Region'] ?? '',
      'lat' => isset($f['Latitude']) ? floatval($f['Latitude']) : null,
      'lng' => isset($f['Longitude']) ? floatval($f['Longitude']) : null,
      'place_id' => $f['Place ID'] ?? '',
      'airtable_id' => $rec['id'],
      'updated_at' => $startedAt,
      'is_deleted' => 0
    ];
  }
  $summary['cities']['pulled'] = count($cities);

  // Сохраняем города в БД
  $pdo->beginTransaction();
  $selCity = $pdo->prepare('SELECT business_id FROM cities WHERE business_id=?');
  $insCity = $pdo->prepare('INSERT INTO cities (business_id,name_ru,name_en,region_ident,lat,lng,place_id,airtable_id,updated_at,is_deleted) VALUES (?,?,?,?,?,?,?,?,?,?)');
  $updCity = $pdo->prepare('UPDATE cities SET name_ru=?,name_en=?,region_ident=?,lat=?,lng=?,place_id=?,airtable_id=?,updated_at=?,is_deleted=? WHERE business_id=?');
  
  foreach ($cities as $c) {
    if ($c['business_id'] === '') continue;
    
    $selCity->execute([$c['business_id']]);
    $exists = $selCity->fetch();
    if ($exists) {
      $updCity->execute([
        $c['name_ru'], $c['name_en'], $c['region_ident'], 
        $c['lat'], $c['lng'], $c['place_id'], 
        $c['airtable_id'], $c['updated_at'], $c['is_deleted'], 
        $c['business_id']
      ]);
      $summary['cities']['updated']++;
    } else {
      $insCity->execute([
        $c['business_id'], $c['name_ru'], $c['name_en'], $c['region_ident'],
        $c['lat'], $c['lng'], $c['place_id'], $c['airtable_id'],
        $c['updated_at'], $c['is_deleted']
      ]);
      $summary['cities']['created']++;
    }
  }
  $pdo->commit();

  // Итоговая статистика
  $regionsTotal = $pdo->query("SELECT COUNT(*) as cnt FROM regions")->fetch()['cnt'];
  $citiesTotal = $pdo->query("SELECT COUNT(*) as cnt FROM cities")->fetch()['cnt'];

  ok([
    'ok' => true,
    'summary' => [
      'started_at' => $startedAt,
      'finished_at' => gmdate('c'),
      'regions' => $summary['regions'],
      'cities' => $summary['cities'],
      'totals' => [
        'regions_remote' => $summary['regions']['pulled'],
        'regions_local' => $regionsTotal,
        'cities_remote' => $summary['cities']['pulled'],
        'cities_local' => $citiesTotal
      ]
    ]
  ]);

} catch (Throwable $e) {
  fail($e->getMessage());
}
?>
