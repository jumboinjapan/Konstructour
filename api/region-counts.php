<?php
// api/region-counts.php - Быстрая загрузка счетчиков городов и POI для регионов
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); // Кэш на 5 минут

try {
  $pdo = getPdo();
  
  // Получаем счетчики городов для каждого региона
  $stmt = $pdo->query("
    SELECT 
      r.id as region_id,
      r.business_id as region_rid,
      r.name_ru as region_name,
      COUNT(DISTINCT c.id) as cities_count,
      COUNT(DISTINCT p.id) as pois_count
    FROM regions r
    LEFT JOIN cities c ON c.regionId = r.id
    LEFT JOIN pois p ON p.cityId = c.id OR p.regionId = r.id
    GROUP BY r.id, r.business_id, r.name_ru
    ORDER BY r.id
  ");
  
  $counts = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[$row['region_id']] = [
      'region_id' => $row['region_id'],
      'region_rid' => $row['region_rid'],
      'region_name' => $row['region_name'],
      'cities' => (int)$row['cities_count'],
      'pois' => (int)$row['pois_count']
    ];
  }
  
  echo json_encode([
    'ok' => true,
    'counts' => $counts,
    'timestamp' => date('c')
  ], JSON_UNESCAPED_UNICODE);
  
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => $e->getMessage()
  ]);
}

