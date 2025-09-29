<?php
require_once 'database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();
    $connection = $db->getConnection();
    
    // Получаем структуру таблицы regions
    $stmt = $connection->query("PRAGMA table_info(regions)");
    $regionsColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Получаем структуру таблицы cities
    $stmt = $connection->query("PRAGMA table_info(cities)");
    $citiesColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Получаем структуру таблицы pois
    $stmt = $connection->query("PRAGMA table_info(pois)");
    $poisColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'ok' => true,
        'regions_columns' => $regionsColumns,
        'cities_columns' => $citiesColumns,
        'pois_columns' => $poisColumns
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
