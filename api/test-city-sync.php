<?php
// Тест синхронизации городов
require_once 'database.php';
require_once 'config.php';

$db = new Database();
$config = include 'config.php';

// Получаем токен
$pat = ($config['airtable']['api_key'] ?? '')
    ?: (($config['airtable']['token'] ?? '')
    ?: (($config['airtable_pat'] ?? '')
    ?: (($config['airtable_registry']['api_key'] ?? '')
    ?: (($config['airtable_registry']['token'] ?? '')
    ?: (getenv('AIRTABLE_PAT') ?: (getenv('AIRTABLE_API_KEY') ?: ''))))));

$baseId = 'apppwhjFN82N9zNqm';

echo "=== Тест синхронизации городов ===\n\n";

// Получаем данные из Airtable
function fetchAirtableData($baseId, $tableId, $pat) {
    $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}?pageSize=100";
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
    return $data['records'] ?? [];
}

function extractLinkedRecordId($fields, $fieldNames) {
    foreach ($fieldNames as $fieldName) {
        if (isset($fields[$fieldName])) {
            $value = $fields[$fieldName];
            if (is_array($value) && !empty($value)) {
                return $value[0];
            } elseif (is_string($value)) {
                return $value;
            }
        }
    }
    return null;
}

try {
    // Получаем города из Airtable
    $cities = fetchAirtableData($baseId, 'tblHaHc9NV0mA8bSa', $pat);
    echo "Городов в Airtable: " . count($cities) . "\n\n";
    
    // Проверяем каждую запись
    foreach ($cities as $i => $record) {
        echo "Город " . ($i + 1) . ":\n";
        echo "  ID: " . $record['id'] . "\n";
        echo "  Name (RU): " . ($record['fields']['Name (RU)'] ?? 'N/A') . "\n";
        echo "  Name (EN): " . ($record['fields']['Name (EN)'] ?? 'N/A') . "\n";
        echo "  Business ID: " . ($record['fields']['ID'] ?? 'N/A') . "\n";
        
        // Проверяем связь с регионом
        $regionId = extractLinkedRecordId($record['fields'], ['Region', 'Регион', 'Regions', 'Регионы']);
        echo "  Region ID: " . ($regionId ?? 'N/A') . "\n";
        
        // Проверяем, есть ли этот город в базе
        $existingCity = $db->getCityById($record['id']);
        echo "  В базе данных: " . ($existingCity ? 'ДА' : 'НЕТ') . "\n";
        
        if ($existingCity) {
            echo "  Название в БД: " . $existingCity['name_ru'] . "\n";
            echo "  Region ID в БД: " . ($existingCity['region_id'] ?? 'N/A') . "\n";
        }
        
        echo "\n";
        
        if ($i >= 4) { // Показываем только первые 5
            echo "... и еще " . (count($cities) - 5) . " городов\n";
            break;
        }
    }
    
    // Проверяем общее количество в базе
    $dbCities = $db->getAllCities();
    echo "Городов в базе данных: " . count($dbCities) . "\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
?>
