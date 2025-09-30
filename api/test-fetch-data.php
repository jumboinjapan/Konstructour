<?php
// Тест получения данных из Airtable
require_once 'config.php';

$config = include 'config.php';

// Получаем токен
$pat = ($config['airtable']['api_key'] ?? '')
    ?: (($config['airtable']['token'] ?? '')
    ?: (($config['airtable_pat'] ?? '')
    ?: (($config['airtable_registry']['api_key'] ?? '')
    ?: (($config['airtable_registry']['token'] ?? '')
    ?: (getenv('AIRTABLE_PAT') ?: (getenv('AIRTABLE_API_KEY') ?: ''))))));

$baseId = 'apppwhjFN82N9zNqm';

echo "=== Тест получения данных из Airtable ===\n\n";

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
    
    echo "URL: $url\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response length: " . strlen($response) . " bytes\n";
    
    if ($httpCode !== 200) {
        echo "Error response: " . substr($response, 0, 500) . "\n";
        throw new Exception("Airtable API error: HTTP {$httpCode}");
    }
    
    $data = json_decode($response, true);
    echo "Records count: " . count($data['records'] ?? []) . "\n";
    
    return $data['records'] ?? [];
}

try {
    // Тест регионов
    echo "--- Регионы ---\n";
    $regions = fetchAirtableData($baseId, 'tblbSajWkzI8X7M4U', $pat);
    echo "Найдено регионов: " . count($regions) . "\n";
    
    if (!empty($regions)) {
        echo "Первый регион:\n";
        print_r($regions[0]);
    }
    
    echo "\n--- Города ---\n";
    $cities = fetchAirtableData($baseId, 'tblHaHc9NV0mA8bSa', $pat);
    echo "Найдено городов: " . count($cities) . "\n";
    
    if (!empty($cities)) {
        echo "Первый город:\n";
        print_r($cities[0]);
    }
    
    echo "\n--- POI ---\n";
    $pois = fetchAirtableData($baseId, 'tblVCmFcHRpXUT24y', $pat);
    echo "Найдено POI: " . count($pois) . "\n";
    
    if (!empty($pois)) {
        echo "Первый POI:\n";
        print_r($pois[0]);
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
?>
