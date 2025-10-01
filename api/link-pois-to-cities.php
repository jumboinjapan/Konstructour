<?php
// Временный скрипт для автоматического связывания POI с городами по префектуре
header('Content-Type: application/json; charset=utf-8');
require_once 'database.php';

$db = new Database();
$pdo = $db->getConnection();

try {
    // Получаем все POI без city_id
    $stmt = $pdo->query("
        SELECT id, name_ru, region_id 
        FROM pois 
        WHERE city_id IS NULL
    ");
    $orphanPois = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $linked = 0;
    $errors = [];
    
    foreach ($orphanPois as $poi) {
        // Для каждого POI без города, ищем города в его регионе
        $stmt = $pdo->prepare("
            SELECT id, name_ru, name_en 
            FROM cities 
            WHERE region_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$poi['region_id']]);
        $city = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($city) {
            // Временно связываем POI с первым городом региона
            // (в идеале должна быть связь через Prefecture)
            $updateStmt = $pdo->prepare("
                UPDATE pois 
                SET city_id = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$city['id'], $poi['id']]);
            $linked++;
        } else {
            $errors[] = "POI {$poi['name_ru']} - no cities in region {$poi['region_id']}";
        }
    }
    
    echo json_encode([
        'ok' => true,
        'orphan_pois' => count($orphanPois),
        'linked' => $linked,
        'errors' => $errors,
        'message' => "Linked $linked POIs to cities. NOTE: This is temporary - please add City field in Airtable!"
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}

