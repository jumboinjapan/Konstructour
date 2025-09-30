<?php
// api/data-parity.php
require_once __DIR__ . '/secret-airtable.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function get_airtable_counts() {
  try {
    $pick = get_airtable_tokens_with_failover('airtable_whoami_check');
    $token = $pick['token'];
    
    // Получаем конфигурацию таблиц
    $config = require __DIR__ . '/config.php';
    $baseId = $config['airtable_registry']['baseId'] ?? '';
    $regionTable = $config['airtable_registry']['tables']['region']['tableId'] ?? '';
    $cityTable = $config['airtable_registry']['tables']['city']['tableId'] ?? '';
    
    if (!$baseId || !$regionTable || !$cityTable) {
      throw new Exception('Missing table configuration');
    }
    
    $counts = [];
    
    // Считаем регионы
    $url = "https://api.airtable.com/v0/{$baseId}/{$regionTable}?pageSize=1";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
      $data = json_decode($response, true);
      $counts['regions'] = $data['records'] ? count($data['records']) : 0;
    } else {
      $counts['regions'] = null;
    }
    
    // Считаем города
    $url = "https://api.airtable.com/v0/{$baseId}/{$cityTable}?pageSize=1";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
      $data = json_decode($response, true);
      $counts['cities'] = $data['records'] ? count($data['records']) : 0;
    } else {
      $counts['cities'] = null;
    }
    
    return $counts;
    
  } catch (Throwable $e) {
    error_log("[DATA-PARITY] Airtable error: " . $e->getMessage());
    return ['regions' => null, 'cities' => null];
  }
}

function get_sqlite_counts() {
  try {
    $db = konstructour_db();
    
    $regions = $db->query("SELECT COUNT(*) FROM regions")->fetchColumn();
    $cities = $db->query("SELECT COUNT(*) FROM cities")->fetchColumn();
    $pois = $db->query("SELECT COUNT(*) FROM pois")->fetchColumn();
    
    return [
      'regions' => (int)$regions,
      'cities' => (int)$cities,
      'pois' => (int)$pois
    ];
  } catch (Throwable $e) {
    error_log("[DATA-PARITY] SQLite error: " . $e->getMessage());
    return ['regions' => null, 'cities' => null, 'pois' => null];
  }
}

function check_orphans() {
  try {
    $db = konstructour_db();
    
    $cityOrphans = $db->query("
      SELECT COUNT(*) FROM cities c 
      LEFT JOIN regions r ON c.region_id = r.id 
      WHERE r.id IS NULL
    ")->fetchColumn();
    
    $poiCityOrphans = $db->query("
      SELECT COUNT(*) FROM pois p 
      LEFT JOIN cities c ON p.city_id = c.id 
      WHERE p.city_id IS NOT NULL AND c.id IS NULL
    ")->fetchColumn();
    
    $poiRegionOrphans = $db->query("
      SELECT COUNT(*) FROM pois p 
      LEFT JOIN regions r ON p.region_id = r.id 
      WHERE p.region_id IS NOT NULL AND r.id IS NULL
    ")->fetchColumn();
    
    return [
      'city_orphans' => (int)$cityOrphans,
      'poi_city_orphans' => (int)$poiCityOrphans,
      'poi_region_orphans' => (int)$poiRegionOrphans
    ];
  } catch (Throwable $e) {
    error_log("[DATA-PARITY] Orphans check error: " . $e->getMessage());
    return ['city_orphans' => null, 'poi_city_orphans' => null, 'poi_region_orphans' => null];
  }
}

try {
  $airtableCounts = get_airtable_counts();
  $sqliteCounts = get_sqlite_counts();
  $orphans = check_orphans();
  
  // Вычисляем расхождения
  $discrepancies = [];
  if ($airtableCounts['regions'] !== null && $sqliteCounts['regions'] !== null) {
    $discrepancies['regions'] = $airtableCounts['regions'] - $sqliteCounts['regions'];
  }
  if ($airtableCounts['cities'] !== null && $sqliteCounts['cities'] !== null) {
    $discrepancies['cities'] = $airtableCounts['cities'] - $sqliteCounts['cities'];
  }
  
  // Определяем статус
  $status = 'ok';
  $issues = [];
  
  // Проверяем расхождения (допускаем ±5%)
  foreach ($discrepancies as $entity => $diff) {
    $airtableCount = $airtableCounts[$entity];
    $sqliteCount = $sqliteCounts[$entity];
    $threshold = max(1, intval($airtableCount * 0.05)); // 5% или минимум 1
    
    if (abs($diff) > $threshold) {
      $status = 'warning';
      $issues[] = "Significant discrepancy in $entity: Airtable=$airtableCount, SQLite=$sqliteCount, diff=$diff";
    }
  }
  
  // Проверяем сирот
  foreach ($orphans as $type => $count) {
    if ($count > 0) {
      $status = 'error';
      $issues[] = "Found $count orphaned records: $type";
    }
  }
  
  echo json_encode([
    'ok' => $status === 'ok',
    'status' => $status,
    'timestamp' => date('c'),
    'airtable_counts' => $airtableCounts,
    'sqlite_counts' => $sqliteCounts,
    'discrepancies' => $discrepancies,
    'orphans' => $orphans,
    'issues' => $issues
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  
} catch (Throwable $e) {
  error_log("[DATA-PARITY] Fatal error: " . $e->getMessage());
  
  echo json_encode([
    'ok' => false,
    'status' => 'error',
    'error' => $e->getMessage(),
    'timestamp' => date('c')
  ], JSON_UNESCAPED_UNICODE);
  
  http_response_code(500);
}
?>
