<?php
// Export all data from local SQLite database
require_once 'api/database.php';

function exportToJson($data, $filename) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents($filename, $json);
    echo "Данные экспортированы в {$filename}\n";
}

function exportToCsv($data, $filename, $headers = null) {
    if (empty($data)) {
        echo "Нет данных для экспорта в CSV\n";
        return;
    }
    
    $fp = fopen($filename, 'w');
    
    if ($headers) {
        fputcsv($fp, $headers);
    } else {
        // Use first record keys as headers
        fputcsv($fp, array_keys($data[0]));
    }
    
    foreach ($data as $row) {
        fputcsv($fp, $row);
    }
    
    fclose($fp);
    echo "Данные экспортированы в {$filename}\n";
}

echo "🚀 Начинаем экспорт данных из локальной базы данных...\n\n";

$exportDir = 'exports';
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0755, true);
}

$timestamp = date('Y-m-d_H-i-s');

try {
    $db = new Database();
    $allData = [];
    
    // Export regions
    echo "📊 Экспорт регионов...\n";
    $regions = $db->getRegions();
    $allData['regions'] = $regions;
    echo "✅ Регионы: " . count($regions) . " записей\n";
    
    // Export cities
    echo "📊 Экспорт городов...\n";
    $cities = $db->getConnection()->query("SELECT * FROM cities ORDER BY name_ru")->fetchAll(PDO::FETCH_ASSOC);
    $allData['cities'] = $cities;
    echo "✅ Города: " . count($cities) . " записей\n";
    
    // Export POIs
    echo "📊 Экспорт POI...\n";
    $pois = $db->getConnection()->query("SELECT * FROM pois ORDER BY name_ru")->fetchAll(PDO::FETCH_ASSOC);
    $allData['pois'] = $pois;
    echo "✅ POI: " . count($pois) . " записей\n";
    
    // Export tickets
    echo "📊 Экспорт билетов...\n";
    $tickets = $db->getConnection()->query("SELECT * FROM tickets ORDER BY created_at")->fetchAll(PDO::FETCH_ASSOC);
    $allData['tickets'] = $tickets;
    echo "✅ Билеты: " . count($tickets) . " записей\n";
    
    // Export sync log
    echo "📊 Экспорт лога синхронизации...\n";
    $syncLog = $db->getConnection()->query("SELECT * FROM sync_log ORDER BY timestamp")->fetchAll(PDO::FETCH_ASSOC);
    $allData['sync_log'] = $syncLog;
    echo "✅ Лог синхронизации: " . count($syncLog) . " записей\n";
    
    // Export individual tables
    foreach ($allData as $tableName => $data) {
        exportToJson($data, "{$exportDir}/local_{$tableName}_{$timestamp}.json");
        exportToCsv($data, "{$exportDir}/local_{$tableName}_{$timestamp}.csv");
    }
    
    // Export all data together
    exportToJson($allData, "{$exportDir}/local_all_data_{$timestamp}.json");
    
    echo "\n🎉 Экспорт завершен успешно!\n";
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
