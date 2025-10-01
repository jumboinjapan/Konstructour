<?php
/**
 * Тест синхронизации Airtable
 */

header('Content-Type: text/plain; charset=utf-8');

require_once 'database.php';

try {
    $db = new Database();
    
    // Airtable settings
    $baseId = 'apppwhjFN82N9zNqm';
    
    // Получаем токен из переменных окружения
    $pat = getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_API_KEY') ?: '';
    
    if (!$pat) {
        throw new Exception('Airtable token not configured in environment');
    }
    
    echo "=== ТЕСТ СИНХРОНИЗАЦИИ AIRTABLE ===\n\n";
    
    // Тестируем загрузку регионов
    echo "1. Загружаем регионы из Airtable...\n";
    $url = "https://api.airtable.com/v0/{$baseId}/tblbSajWkzI8X7M4U?pageSize=100";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $pat],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Airtable API error: HTTP {$httpCode}");
    }
    
    $data = json_decode($response, true);
    $regions = $data['records'] ?? [];
    
    echo "   Загружено регионов: " . count($regions) . "\n";
    
    if (count($regions) > 0) {
        echo "   Первый регион: " . json_encode($regions[0], JSON_UNESCAPED_UNICODE) . "\n\n";
        
        // Тестируем сохранение первого региона
        echo "2. Сохраняем первый регион в БД...\n";
        $record = $regions[0];
        $regionData = [
            'id' => $record['id'],
            'name_ru' => $record['fields']['Name (RU)'] ?? $record['fields']['Название (RU)'] ?? 'Unknown',
            'name_en' => $record['fields']['Name (EN)'] ?? $record['fields']['Название (EN)'] ?? null,
            'business_id' => $record['fields']['ID'] ?? null
        ];
        
        echo "   Данные для сохранения: " . json_encode($regionData, JSON_UNESCAPED_UNICODE) . "\n";
        
        $result = $db->saveRegion($regionData);
        echo "   Результат сохранения: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
        
        // Проверяем, что регион сохранился
        $stmt = $db->getConnection()->prepare("SELECT * FROM regions WHERE id = ?");
        $stmt->execute([$record['id']]);
        $savedRegion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($savedRegion) {
            echo "   Регион найден в БД: " . json_encode($savedRegion, JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "   ОШИБКА: Регион не найден в БД!\n";
        }
    }
    
    echo "\n=== ТЕСТ ЗАВЕРШЁН ===\n";
    
} catch (Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
