<?php
/**
 * Тест создания POI через веб-интерфейс
 */

header('Content-Type: application/json; charset=utf-8');

// Симулируем данные, которые отправляет веб-интерфейс
$testData = [
    'name_ru' => 'Тестовый POI из веб-интерфейса',
    'name_en' => 'Test POI from web interface',
    'prefecture_ru' => 'Токио',
    'prefecture_en' => 'Tokyo',
    'categories_ru' => ['Буддийский храм'],
    'categories_en' => ['Buddhist Temple'],
    'description_ru' => 'Тестовое описание из веб-интерфейса',
    'description_en' => 'Test description from web interface',
    'website' => 'https://example.com',
    'working_hours' => '9:00-18:00',
    'notes' => 'Тестовые заметки из веб-интерфейса',
    'place_id' => 'test_place_id',
    'city_id' => 'test_city_id',  // Невалидный ID
    'region_id' => 'test_region_id'  // Невалидный ID
];

echo "=== ТЕСТ СОЗДАНИЯ POI ЧЕРЕЗ ВЕБ-ИНТЕРФЕЙС ===\n\n";

echo "Отправляемые данные:\n";
echo json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "Отправляем запрос к save-poi.php...\n";

$ch = curl_init('https://www.konstructour.com/api/save-poi.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($testData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP код: $httpCode\n";
if ($error) {
    echo "Ошибка cURL: $error\n";
}

echo "Ответ сервера:\n";
$decoded = json_decode($response, true);
if ($decoded) {
    echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo $response . "\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЁН ===\n";
?>
