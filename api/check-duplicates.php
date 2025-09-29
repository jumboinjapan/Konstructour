<?php
require_once 'database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();
    $connection = $db->getConnection();
    
    // Проверяем дубликаты в регионах
    $regionsDuplicates = $connection->query("
        SELECT name_ru, COUNT(*) as count 
        FROM regions 
        GROUP BY name_ru 
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Проверяем дубликаты в городах
    $citiesDuplicates = $connection->query("
        SELECT name_ru, COUNT(*) as count 
        FROM cities 
        GROUP BY name_ru 
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Проверяем дубликаты в POI
    $poisDuplicates = $connection->query("
        SELECT name_ru, COUNT(*) as count 
        FROM pois 
        GROUP BY name_ru 
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Проверяем дубликаты по airtable_id
    $regionsAirtableDuplicates = $connection->query("
        SELECT airtable_id, COUNT(*) as count 
        FROM regions 
        WHERE airtable_id IS NOT NULL
        GROUP BY airtable_id 
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $citiesAirtableDuplicates = $connection->query("
        SELECT airtable_id, COUNT(*) as count 
        FROM cities 
        WHERE airtable_id IS NOT NULL
        GROUP BY airtable_id 
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $poisAirtableDuplicates = $connection->query("
        SELECT airtable_id, COUNT(*) as count 
        FROM pois 
        WHERE airtable_id IS NOT NULL
        GROUP BY airtable_id 
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Общее количество записей
    $regionsCount = $connection->query("SELECT COUNT(*) FROM regions")->fetchColumn();
    $citiesCount = $connection->query("SELECT COUNT(*) FROM cities")->fetchColumn();
    $poisCount = $connection->query("SELECT COUNT(*) FROM pois")->fetchColumn();
    
    echo json_encode([
        'ok' => true,
        'total_counts' => [
            'regions' => $regionsCount,
            'cities' => $citiesCount,
            'pois' => $poisCount
        ],
        'duplicates_by_name' => [
            'regions' => $regionsDuplicates,
            'cities' => $citiesDuplicates,
            'pois' => $poisDuplicates
        ],
        'duplicates_by_airtable_id' => [
            'regions' => $regionsAirtableDuplicates,
            'cities' => $citiesAirtableDuplicates,
            'pois' => $poisAirtableDuplicates
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
