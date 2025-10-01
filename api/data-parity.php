<?php
// api/data-parity.php
// Проверка целостности данных между Airtable и локальной базой

require_once 'database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $db = new Database();
    
    // Получаем статистику из локальной базы
    $regions = $db->getRegions();
    $cities = $db->getAllCities();
    $pois = $db->getPOIs();
    
    $sqliteCounts = [
        'regions' => count($regions),
        'cities' => count($cities),
        'pois' => count($pois)
    ];
    
    // Проверяем сиротские записи
    $orphans = [];
    
    // Проверяем города без регионов
    $orphanCities = 0;
    foreach ($cities as $city) {
        $regionExists = false;
        foreach ($regions as $region) {
            if ($region['id'] === $city['region_id']) {
                $regionExists = true;
                break;
            }
        }
        if (!$regionExists) {
            $orphanCities++;
        }
    }
    
    // Проверяем POI без городов
    $orphanPOIs = 0;
    foreach ($pois as $poi) {
        $cityExists = false;
        foreach ($cities as $city) {
            if ($city['id'] === $poi['city_id']) {
                $cityExists = true;
                break;
            }
        }
        if (!$cityExists) {
            $orphanPOIs++;
        }
    }
    
    $orphans = [
        'cities' => $orphanCities,
        'pois' => $orphanPOIs
    ];
    
    // Определяем статус
    $hasOrphans = $orphanCities > 0 || $orphanPOIs > 0;
    $status = $hasOrphans ? 'warning' : 'ok';
    
    echo json_encode([
        'ok' => true,
        'status' => $status,
        'counts' => [
            'sqlite' => $sqliteCounts
        ],
        'orphans' => $orphans,
        'message' => $hasOrphans ? 
            "Найдены сиротские записи: {$orphanCities} городов, {$orphanPOIs} POI" : 
            "Целостность данных в порядке",
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
?>
