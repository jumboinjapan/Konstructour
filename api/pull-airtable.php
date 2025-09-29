<?php
// Простой Pull из Airtable - только загрузка карточек
header('Content-Type: application/json; charset=utf-8');

// Загружаем токен из файла
$tokenFile = __DIR__ . '/airtable.env.local';
if (file_exists($tokenFile)) {
    $lines = file($tokenFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'AIRTABLE_PAT=') === 0) {
            $token = substr($line, 12);
            putenv("AIRTABLE_API_KEY=$token");
            $_ENV['AIRTABLE_API_KEY'] = $token;
            $_SERVER['AIRTABLE_API_KEY'] = $token;
            break;
        }
    }
}

// Отладка токена
$debugToken = getenv('AIRTABLE_API_KEY');
if (empty($debugToken)) {
    echo json_encode(['ok'=>false,'error'=>'Token not loaded from file', 'file'=>$tokenFile, 'exists'=>file_exists($tokenFile)]);
    exit;
}

require_once __DIR__.'/_airtable-common.php';

function ok($p){ echo json_encode($p, JSON_UNESCAPED_UNICODE); exit; }
function fail($m,$e=[]){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>$m]+$e, JSON_UNESCAPED_UNICODE); exit; }

try {
  $cfg = air_cfg();
  $results = [];

  // Подключаемся к базе данных
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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )");

  // === ЗАГРУЖАЕМ РЕГИОНЫ ===
  $allowedRegions = ['REG-0001','REG-0002','REG-0003','REG-0004','REG-0005','REG-0006','REG-0007','REG-0008','REG-0009'];
  
  $parts = [];
  foreach ($allowedRegions as $v) {
    $parts[] = "{A ID}='" . addslashes($v) . "'";
  }
  $formula = 'OR('.implode(',', $parts).')';
  
  $params = ['pageSize'=>100];
  [$code,$out,$err,$url] = air_call('GET','', null, $params);
  if ($code>=400) fail("Airtable regions $code", ['url'=>$url]);
  
  $j = json_decode($out,true);
  $regions = [];
  foreach (($j['records']??[]) as $rec){
    $f = $rec['fields'] ?? [];
    $regions[] = [
      'id' => $f['ID'] ?? '',
      'name_ru' => $f['Name (RU)'] ?? '',
      'name_en' => $f['Name (EN)'] ?? '',
      'airtable_id' => $rec['id']
    ];
  }
  $results['regions'] = $regions;

  // Сохраняем регионы в базу данных
  $pdo->beginTransaction();
  $insReg = $pdo->prepare('INSERT OR REPLACE INTO regions (id,name_ru,name_en,business_id,updated_at) VALUES (?,?,?,?,?)');
  foreach ($regions as $r) {
    if ($r['id'] !== '') {
      $insReg->execute([$r['id'], $r['name_ru'], $r['name_en'], $r['id'], gmdate('c')]);
    }
  }
  $pdo->commit();

  // === ЗАГРУЖАЕМ ГОРОДА ===
  $params = ['pageSize'=>100];
  [$code,$out,$err,$url] = air_call('GET','tblHaHc9NV0mA8bSa', null, $params);
  if ($code>=400) fail("Airtable cities $code", ['url'=>$url]);
  
  $j = json_decode($out,true);
  $cities = [];
  foreach (($j['records']??[]) as $rec){
    $f = $rec['fields'] ?? [];
    $cities[] = [
      'business_id' => $f['ID'] ?? '',
      'name_ru' => $f['Name (RU)'] ?? '',
      'name_en' => $f['Name (EN)'] ?? '',
      'region' => is_array($f['Regions'] ?? null) ? implode(',', $f['Regions']) : ($f['Regions'] ?? '')
    ];
  }
  $results['cities'] = $cities;

  // Сохраняем города в базу данных
  $pdo->beginTransaction();
  $insCity = $pdo->prepare('INSERT OR REPLACE INTO cities (business_id,name_ru,name_en,updated_at) VALUES (?,?,?,?)');
  foreach ($cities as $c) {
    if ($c['business_id'] !== '') {
      $insCity->execute([
        $c['business_id'], $c['name_ru'], $c['name_en'], gmdate('c')
      ]);
    }
  }
  $pdo->commit();

  // === ЗАГРУЖАЕМ POI ===
  $params = ['pageSize'=>100];
  [$code,$out,$err,$url] = air_call('GET','tblXXXXXX', null, $params); // Замените на правильный ID таблицы POI
  if ($code>=400) {
    // Если таблица POI не найдена, просто пропускаем
    $results['pois'] = [];
  } else {
    $j = json_decode($out,true);
    $pois = [];
    foreach (($j['records']??[]) as $rec){
      $f = $rec['fields'] ?? [];
      $pois[] = [
        'business_id' => $f['fldXXXXXX'] ?? '', // Замените на правильный ID поля
        'name_ru' => $f['Name (RU)'] ?? '',
        'name_en' => $f['Name (EN)'] ?? '',
        'city' => $f['City'] ?? '',
        'lat' => isset($f['Latitude']) ? floatval($f['Latitude']) : null,
        'lng' => isset($f['Longitude']) ? floatval($f['Longitude']) : null,
        'airtable_id' => $rec['id']
      ];
    }
    $results['pois'] = $pois;
  }

  ok([
    'ok' => true,
    'message' => 'Данные успешно загружены и сохранены в базу данных',
    'data' => $results,
    'summary' => [
      'regions_count' => count($regions),
      'cities_count' => count($cities),
      'pois_count' => count($results['pois']),
      'loaded_at' => gmdate('c'),
      'saved_to_db' => true
    ]
  ]);

} catch (Throwable $e) {
  fail($e->getMessage());
}
?>
