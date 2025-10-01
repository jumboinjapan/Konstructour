<?php
// Debug POI loading
header('Content-Type: application/json; charset=utf-8');
require_once 'database.php';

$db = new Database();

// Get all POIs with their city relationships
$stmt = $db->getConnection()->query("
    SELECT 
        p.id as poi_id,
        p.name_ru as poi_name,
        p.business_id as poi_business_id,
        p.category,
        p.city_id,
        p.region_id,
        c.id as city_record_id,
        c.name_ru as city_name,
        r.id as region_record_id,
        r.name_ru as region_name
    FROM pois p
    LEFT JOIN cities c ON p.city_id = c.id
    LEFT JOIN regions r ON p.region_id = r.id
    LIMIT 10
");

$pois = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all cities
$cities = $db->getCitiesByRegion('1'); // Region 1 example

echo json_encode([
    'ok' => true,
    'debug' => [
        'total_pois' => count($pois),
        'pois' => $pois,
        'cities_in_region_1' => $cities
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

