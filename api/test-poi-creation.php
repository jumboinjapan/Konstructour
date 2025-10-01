<?php
/**
 * Тест создания POI для проверки миграции
 */

header('Content-Type: application/json; charset=utf-8');

require_once 'database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Тестовые данные
    $testData = [
        'name_ru' => 'Тестовый POI',
        'name_en' => 'Test POI',
        'prefecture_ru' => 'Токио',
        'prefecture_en' => 'Tokyo',
        'categories_ru' => json_encode(['Достопримечательность']),
        'categories_en' => json_encode(['Attraction']),
        'description_ru' => 'Тестовое описание',
        'description_en' => 'Test description',
        'website' => 'https://example.com',
        'working_hours' => '9:00-18:00',
        'notes' => 'Тестовые заметки',
        'business_id' => 'POI-TEST-001',
        'city_id' => 'test-city',
        'region_id' => 'test-region'
    ];
    
    // Пробуем вставить тестовую запись
    $sql = "INSERT INTO pois (
        name_ru, name_en, prefecture_ru, prefecture_en, 
        categories_ru, categories_en, description_ru, description_en,
        website, working_hours, notes, business_id, city_id, region_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        $testData['name_ru'],
        $testData['name_en'],
        $testData['prefecture_ru'],
        $testData['prefecture_en'],
        $testData['categories_ru'],
        $testData['categories_en'],
        $testData['description_ru'],
        $testData['description_en'],
        $testData['website'],
        $testData['working_hours'],
        $testData['notes'],
        $testData['business_id'],
        $testData['city_id'],
        $testData['region_id']
    ]);
    
    if ($result) {
        echo json_encode([
            'ok' => true,
            'message' => 'Тестовый POI создан успешно',
            'data' => $testData
        ]);
    } else {
        echo json_encode([
            'ok' => false,
            'error' => 'Не удалось создать тестовый POI',
            'sql_error' => $stmt->errorInfo()
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
