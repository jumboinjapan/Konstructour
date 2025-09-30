<?php
// Count data in Airtable database
require_once 'api/database.php';

// Airtable configuration
$baseId = 'apppwhjFN82N9zNqm';
$pat = getenv('AIRTABLE_PAT') ?: 'PLACEHOLDER_FOR_REAL_API_KEY';

// Table IDs
$tables = [
    'regions' => 'tblbSajWkzI8X7M4U',
    'cities' => 'tblHaHc9NV0mA8bSa', 
    'pois' => 'tblVCmFcHRpXUT24y'
];

function countAirtableRecords($baseId, $tableId, $pat) {
    $totalCount = 0;
    $offset = null;
    
    do {
        $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}?pageSize=100";
        if ($offset) {
            $url .= "&offset=" . urlencode($offset);
        }
        
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
            throw new Exception("Airtable API error: HTTP {$httpCode} for table {$tableId}");
        }
        
        $data = json_decode($response, true);
        $records = $data['records'] ?? [];
        $totalCount += count($records);
        
        $offset = $data['offset'] ?? null;
        
        echo "Загружено " . count($records) . " записей из таблицы {$tableId}...\n";
        
    } while ($offset);
    
    return $totalCount;
}

function analyzeCitiesData($baseId, $tableId, $pat) {
    $allRecords = [];
    $offset = null;
    
    do {
        $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}?pageSize=100";
        if ($offset) {
            $url .= "&offset=" . urlencode($offset);
        }
        
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
            throw new Exception("Airtable API error: HTTP {$httpCode} for table {$tableId}");
        }
        
        $data = json_decode($response, true);
        $records = $data['records'] ?? [];
        $allRecords = array_merge($allRecords, $records);
        
        $offset = $data['offset'] ?? null;
        
    } while ($offset);
    
    // Analyze city types
    $cityCount = 0;
    $locationCount = 0;
    $unknownCount = 0;
    $typeStats = [];
    
    foreach ($allRecords as $record) {
        $fields = $record['fields'] ?? [];
        $type = $fields['Type'] ?? $fields['type'] ?? $fields['Тип'] ?? 'unknown';
        
        if (stripos($type, 'city') !== false) {
            $cityCount++;
        } elseif (stripos($type, 'location') !== false) {
            $locationCount++;
        } else {
            $unknownCount++;
        }
        
        $typeStats[$type] = ($typeStats[$type] ?? 0) + 1;
    }
    
    return [
        'total' => count($allRecords),
        'cities' => $cityCount,
        'locations' => $locationCount,
        'unknown' => $unknownCount,
        'type_stats' => $typeStats
    ];
}

echo "🔍 Подсчет данных в Airtable...\n\n";

if ($pat === 'PLACEHOLDER_FOR_REAL_API_KEY') {
    echo "❌ Ошибка: Не настроен API ключ Airtable\n";
    echo "Установите переменную окружения AIRTABLE_PAT с вашим токеном\n";
    echo "Пример: export AIRTABLE_PAT='your_token_here'\n";
    echo "\nИли используйте скрипт настройки:\n";
    echo "./setup-airtable-token.sh\n";
    exit(1);
}

try {
    $results = [];
    
    foreach ($tables as $tableName => $tableId) {
        echo "📊 Подсчет записей в таблице: {$tableName}\n";
        $count = countAirtableRecords($baseId, $tableId, $pat);
        $results[$tableName] = $count;
        echo "✅ {$tableName}: {$count} записей\n\n";
    }
    
    // Детальный анализ городов/локаций
    echo "🏙️ Детальный анализ городов/локаций:\n";
    $cityAnalysis = analyzeCitiesData($baseId, $tables['cities'], $pat);
    
    echo "📈 Статистика по типам:\n";
    echo "- Всего записей: {$cityAnalysis['total']}\n";
    echo "- Города (city): {$cityAnalysis['cities']}\n";
    echo "- Локации (location): {$cityAnalysis['locations']}\n";
    echo "- Неизвестный тип: {$cityAnalysis['unknown']}\n\n";
    
    echo "📋 Детальная статистика по типам:\n";
    foreach ($cityAnalysis['type_stats'] as $type => $count) {
        echo "- '{$type}': {$count} записей\n";
    }
    
    echo "\n🎯 ИТОГО:\n";
    echo "================\n";
    foreach ($results as $table => $count) {
        echo "{$table}: {$count}\n";
    }
    
    echo "\n🏙️ Города/локации: {$cityAnalysis['total']} (cities: {$cityAnalysis['cities']}, locations: {$cityAnalysis['locations']})\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
?>
