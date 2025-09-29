<?php
// Тестирование синхронизации между локальной БД и Airtable
require_once 'database.php';
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

function testSync() {
    $db = new Database();
    $config = include 'config.php';
    
    // Airtable settings
    $baseId = 'apppwhjFN82N9zNqm';
    $pat = getenv('AIRTABLE_PAT') ?: 'PLACEHOLDER_FOR_REAL_API_KEY';
    
    if ($pat === 'PLACEHOLDER_FOR_REAL_API_KEY') {
        return "❌ Airtable token not configured";
    }
    
    $results = [];
    
    // 1. Тест подключения к Airtable
    try {
        $ch = curl_init("https://api.airtable.com/v0/{$baseId}/tblbSajWkzI8X7M4U?maxRecords=1");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $pat,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $results[] = "❌ Airtable connection failed: " . $error;
        } elseif ($httpCode !== 200) {
            $results[] = "❌ Airtable HTTP error: " . $httpCode;
        } else {
            $results[] = "✅ Airtable connection successful";
        }
    } catch (Exception $e) {
        $results[] = "❌ Airtable connection error: " . $e->getMessage();
    }
    
    // 2. Тест подключения к локальной БД
    try {
        $regions = $db->getRegions();
        $results[] = "✅ Local DB connection successful (" . count($regions) . " regions)";
    } catch (Exception $e) {
        $results[] = "❌ Local DB connection error: " . $e->getMessage();
    }
    
    // 3. Тест функций обновления/удаления
    $functions = [
        'updateRegion' => method_exists($db, 'updateRegion'),
        'deleteRegion' => method_exists($db, 'deleteRegion'),
        'updateCity' => method_exists($db, 'updateCity'),
        'deleteCity' => method_exists($db, 'deleteCity'),
        'updatePoi' => method_exists($db, 'updatePoi'),
        'deletePoi' => method_exists($db, 'deletePoi')
    ];
    
    foreach ($functions as $func => $exists) {
        if ($exists) {
            $results[] = "✅ Function {$func} exists";
        } else {
            $results[] = "❌ Function {$func} missing";
        }
    }
    
    // 4. Тест API endpoints
    $endpoints = [
        'update-region' => 'POST /api/data-api.php?action=update-region',
        'delete-region' => 'POST /api/data-api.php?action=delete-region',
        'update-city' => 'POST /api/data-api.php?action=update-city',
        'delete-city' => 'POST /api/data-api.php?action=delete-city',
        'update-poi' => 'POST /api/data-api.php?action=update-poi',
        'delete-poi' => 'POST /api/data-api.php?action=delete-poi',
        'sync-airtable' => 'GET /api/sync-airtable.php',
        'bidirectional-sync' => 'GET /api/bidirectional-sync.php',
        'sync-diagnostics' => 'GET /api/sync-diagnostics.php'
    ];
    
    foreach ($endpoints as $action => $endpoint) {
        $url = "http://" . $_SERVER['HTTP_HOST'] . "/api/data-api.php?action=" . $action;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_NOBODY => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 405) { // 405 = Method Not Allowed (endpoint exists)
            $results[] = "✅ Endpoint {$action} accessible";
        } else {
            $results[] = "❌ Endpoint {$action} not accessible (HTTP {$httpCode})";
        }
    }
    
    return $results;
}

$testResults = testSync();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест синхронизации</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .result { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        h1 { color: #333; }
        .section { margin: 20px 0; }
        .section h2 { color: #666; border-bottom: 2px solid #eee; padding-bottom: 5px; }
    </style>
</head>
<body>
    <h1>🔍 Диагностика синхронизации между локальной БД и Airtable</h1>
    
    <div class="section">
        <h2>📊 Результаты тестирования</h2>
        <?php foreach ($testResults as $result): ?>
            <div class="result <?php echo strpos($result, '✅') === 0 ? 'success' : 'error'; ?>">
                <?php echo $result; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="section">
        <h2>🔧 Доступные инструменты</h2>
        <div class="result info">
            <strong>Диагностика:</strong> <a href="/api/sync-diagnostics.php" target="_blank">/api/sync-diagnostics.php</a>
        </div>
        <div class="result info">
            <strong>Двусторонняя синхронизация:</strong> <a href="/api/bidirectional-sync.php" target="_blank">/api/bidirectional-sync.php</a>
        </div>
        <div class="result info">
            <strong>Синхронизация из Airtable:</strong> <a href="/api/sync-airtable.php" target="_blank">/api/sync-airtable.php</a>
        </div>
    </div>
    
    <div class="section">
        <h2>📋 Инструкции по синхронизации</h2>
        <div class="result info">
            <strong>1. Односторонняя синхронизация (Airtable → Локальная БД):</strong><br>
            • Данные из Airtable загружаются в локальную БД<br>
            • Используется при инициализации системы<br>
            • Не влияет на данные в Airtable
        </div>
        <div class="result info">
            <strong>2. Двусторонняя синхронизация:</strong><br>
            • Синхронизирует изменения в обе стороны<br>
            • Локальные изменения переносятся в Airtable<br>
            • Airtable изменения обновляют локальную БД<br>
            • Рекомендуется для продакшена
        </div>
        <div class="result info">
            <strong>3. Обновление и удаление:</strong><br>
            • Изменения в админ-панели сохраняются только в локальной БД<br>
            • Для синхронизации с Airtable нужно запустить двустороннюю синхронизацию<br>
            • Удаление работает каскадно (регион → города → POI → билеты)
        </div>
    </div>
    
    <div class="section">
        <h2>⚠️ Важные замечания</h2>
        <div class="result error">
            <strong>Текущее состояние:</strong> Система работает в режиме "локальная БД + синхронизация"<br>
            • Изменения в админ-панели НЕ синхронизируются автоматически с Airtable<br>
            • Для полной синхронизации нужно запускать скрипты вручную<br>
            • Рекомендуется настроить автоматическую синхронизацию
        </div>
    </div>
</body>
</html>
