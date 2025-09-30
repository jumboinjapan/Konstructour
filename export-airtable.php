<?php
// Export all data from Airtable
require_once 'api/database.php';

// Airtable configuration
$baseId = 'apppwhjFN82N9zNqm';
$pat = getenv('AIRTABLE_PAT') ?: 'PLACEHOLDER_FOR_REAL_API_KEY';

// Table IDs from sync script
$tables = [
    'regions' => 'tblbSajWkzI8X7M4U',
    'cities' => 'tblHaHc9NV0mA8bSa', 
    'pois' => 'tblVCmFcHRpXUT24y'
];

function fetchAirtableData($baseId, $tableId, $pat) {
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
        
        echo "Загружено " . count($records) . " записей из таблицы {$tableId}...\n";
        
    } while ($offset);
    
    return $allRecords;
}

function exportToJson($data, $filename) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents($filename, $json);
    echo "Данные экспортированы в {$filename}\n";
}

function exportToCsv($data, $filename) {
    if (empty($data)) {
        echo "Нет данных для экспорта в CSV\n";
        return;
    }
    
    $fp = fopen($filename, 'w');
    
    // Get all possible fields from all records
    $allFields = [];
    foreach ($data as $record) {
        if (isset($record['fields'])) {
            $allFields = array_merge($allFields, array_keys($record['fields']));
        }
    }
    $allFields = array_unique($allFields);
    
    // Write header
    fputcsv($fp, array_merge(['id', 'createdTime'], $allFields));
    
    // Write data
    foreach ($data as $record) {
        $row = [
            $record['id'] ?? '',
            $record['createdTime'] ?? ''
        ];
        
        foreach ($allFields as $field) {
            $value = $record['fields'][$field] ?? '';
            if (is_array($value)) {
                $value = implode('; ', $value);
            }
            $row[] = $value;
        }
        
        fputcsv($fp, $row);
    }
    
    fclose($fp);
    echo "Данные экспортированы в {$filename}\n";
}

echo "🚀 Начинаем экспорт данных из Airtable...\n\n";

if ($pat === 'PLACEHOLDER_FOR_REAL_API_KEY') {
    echo "❌ Ошибка: Не настроен API ключ Airtable\n";
    echo "Установите переменную окружения AIRTABLE_PAT с вашим токеном\n";
    echo "Пример: export AIRTABLE_PAT='your_token_here'\n";
    exit(1);
}

$exportDir = 'exports';
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0755, true);
}

$timestamp = date('Y-m-d_H-i-s');
$allData = [];

try {
    foreach ($tables as $tableName => $tableId) {
        echo "📊 Экспорт таблицы: {$tableName} (ID: {$tableId})\n";
        
        $records = fetchAirtableData($baseId, $tableId, $pat);
        $allData[$tableName] = $records;
        
        echo "✅ Загружено {$tableName}: " . count($records) . " записей\n\n";
        
        // Export individual table
        exportToJson($records, "{$exportDir}/{$tableName}_{$timestamp}.json");
        exportToCsv($records, "{$exportDir}/{$tableName}_{$timestamp}.csv");
    }
    
    // Export all data together
    exportToJson($allData, "{$exportDir}/all_data_{$timestamp}.json");
    
    echo "🎉 Экспорт завершен успешно!\n";
    echo "📁 Файлы сохранены в папке: {$exportDir}/\n";
    
    // Summary
    echo "\n📈 Сводка:\n";
    foreach ($allData as $tableName => $records) {
        echo "- {$tableName}: " . count($records) . " записей\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка экспорта: " . $e->getMessage() . "\n";
    exit(1);
}
?>
