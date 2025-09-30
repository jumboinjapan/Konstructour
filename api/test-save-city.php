<?php
// Тест сохранения города
require_once 'database.php';

$db = new Database();

echo "=== Тест сохранения города ===\n\n";

// Тестовые данные
$testData = [
    'id' => 'test_city_' . time(),
    'name_ru' => 'Тестовый город',
    'name_en' => 'Test City',
    'business_id' => 'TEST-001',
    'type' => 'city',
    'region_id' => 'recDHGTVt7NMfEnlh' // ID региона из Airtable
];

echo "Тестовые данные:\n";
print_r($testData);

try {
    // Пытаемся сохранить город
    $result = $db->saveCity($testData);
    echo "\nРезультат saveCity: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
    
    // Проверяем, сохранился ли город
    $savedCity = $db->getCityById($testData['id']);
    if ($savedCity) {
        echo "Город найден в базе:\n";
        print_r($savedCity);
    } else {
        echo "Город НЕ найден в базе!\n";
    }
    
    // Проверяем общее количество городов
    $allCities = $db->getAllCities();
    echo "\nОбщее количество городов в базе: " . count($allCities) . "\n";
    
    // Пытаемся сохранить тот же город еще раз
    echo "\nПытаемся сохранить тот же город еще раз...\n";
    $result2 = $db->saveCity($testData);
    echo "Результат повторного сохранения: " . ($result2 ? 'SUCCESS' : 'FAILED') . "\n";
    
    $allCities2 = $db->getAllCities();
    echo "Общее количество городов после повторного сохранения: " . count($allCities2) . "\n";
    
    // Удаляем тестовый город
    $db->db->prepare("DELETE FROM cities WHERE id = ?")->execute([$testData['id']]);
    echo "\nТестовый город удален.\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
