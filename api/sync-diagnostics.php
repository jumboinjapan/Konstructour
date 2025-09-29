<?php
// Диагностика синхронизации между локальной БД и Airtable
require_once 'database.php';
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fetchAirtableData($baseId, $tableId, $pat, $maxRecords = 100) {
    $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}?maxRecords={$maxRecords}";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $pat,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("CURL Error: " . $error);
    }
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP Error: " . $httpCode . " - " . $response);
    }
    
    $data = json_decode($response, true);
    return $data['records'] ?? [];
}

try {
    $db = new Database();
    $config = include 'config.php';
    
    // Airtable settings
    $baseId = 'apppwhjFN82N9zNqm';
    $pat = getenv('AIRTABLE_PAT') ?: 'PLACEHOLDER_FOR_REAL_API_KEY';
    
    if ($pat === 'PLACEHOLDER_FOR_REAL_API_KEY') {
        respond(false, ['error' => 'Airtable token not configured'], 400);
    }
    
    $diagnostics = [
        'airtable_connection' => false,
        'local_db_connection' => false,
        'sync_status' => [],
        'data_comparison' => [],
        'recommendations' => []
    ];
    
    // 1. Проверка подключения к Airtable
    try {
        $regions = fetchAirtableData($baseId, 'tblbSajWkzI8X7M4U', $pat, 5);
        $diagnostics['airtable_connection'] = true;
        $diagnostics['airtable_regions_count'] = count($regions);
    } catch (Exception $e) {
        $diagnostics['airtable_error'] = $e->getMessage();
    }
    
    // 2. Проверка подключения к локальной БД
    try {
        $localRegions = $db->getRegions();
        $diagnostics['local_db_connection'] = true;
        $diagnostics['local_regions_count'] = count($localRegions);
    } catch (Exception $e) {
        $diagnostics['local_db_error'] = $e->getMessage();
    }
    
    // 3. Сравнение данных
    if ($diagnostics['airtable_connection'] && $diagnostics['local_db_connection']) {
        $airtableRegions = [];
        foreach ($regions as $record) {
            $airtableRegions[] = [
                'id' => $record['id'],
                'name_ru' => $record['fields']['Name (RU)'] ?? $record['fields']['Название (RU)'] ?? 'Unknown',
                'business_id' => $record['fields']['ID'] ?? null
            ];
        }
        
        $localRegions = array_map(function($region) {
            return [
                'id' => $region['id'],
                'name_ru' => $region['name_ru'],
                'business_id' => $region['business_id']
            ];
        }, $localRegions);
        
        // Находим различия
        $airtableIds = array_column($airtableRegions, 'id');
        $localIds = array_column($localRegions, 'id');
        
        $onlyInAirtable = array_diff($airtableIds, $localIds);
        $onlyInLocal = array_diff($localIds, $airtableIds);
        $inBoth = array_intersect($airtableIds, $localIds);
        
        $diagnostics['data_comparison'] = [
            'airtable_only' => count($onlyInAirtable),
            'local_only' => count($onlyInLocal),
            'in_both' => count($inBoth),
            'airtable_ids' => array_values($onlyInAirtable),
            'local_ids' => array_values($onlyInLocal)
        ];
        
        // Проверяем синхронизацию полей для общих записей
        $syncIssues = [];
        foreach ($inBoth as $id) {
            $airtableRecord = array_filter($airtableRegions, fn($r) => $r['id'] === $id)[0] ?? null;
            $localRecord = array_filter($localRegions, fn($r) => $r['id'] === $id)[0] ?? null;
            
            if ($airtableRecord && $localRecord) {
                if ($airtableRecord['name_ru'] !== $localRecord['name_ru']) {
                    $syncIssues[] = "Region {$id}: name_ru mismatch";
                }
                if ($airtableRecord['business_id'] !== $localRecord['business_id']) {
                    $syncIssues[] = "Region {$id}: business_id mismatch";
                }
            }
        }
        
        $diagnostics['sync_issues'] = $syncIssues;
    }
    
    // 4. Рекомендации
    $recommendations = [];
    
    if (!$diagnostics['airtable_connection']) {
        $recommendations[] = "Настроить Airtable API токен в переменной окружения AIRTABLE_PAT";
    }
    
    if (!$diagnostics['local_db_connection']) {
        $recommendations[] = "Проверить подключение к локальной базе данных";
    }
    
    if ($diagnostics['data_comparison']['airtable_only'] > 0) {
        $recommendations[] = "Запустить синхронизацию из Airtable в локальную БД";
    }
    
    if ($diagnostics['data_comparison']['local_only'] > 0) {
        $recommendations[] = "Данные в локальной БД не синхронизированы с Airtable - требуется двусторонняя синхронизация";
    }
    
    if (count($diagnostics['sync_issues']) > 0) {
        $recommendations[] = "Обнаружены расхождения в данных - требуется обновление синхронизации";
    }
    
    $diagnostics['recommendations'] = $recommendations;
    
    // 5. Проверка функций обновления/удаления
    $diagnostics['update_delete_functions'] = [
        'update_region' => method_exists($db, 'updateRegion'),
        'delete_region' => method_exists($db, 'deleteRegion'),
        'update_city' => method_exists($db, 'updateCity'),
        'delete_city' => method_exists($db, 'deleteCity'),
        'update_poi' => method_exists($db, 'updatePoi'),
        'delete_poi' => method_exists($db, 'deletePoi')
    ];
    
    // 6. Проверка API endpoints
    $diagnostics['api_endpoints'] = [
        'update_region' => 'POST /api/data-api.php?action=update-region',
        'delete_region' => 'POST /api/data-api.php?action=delete-region',
        'update_city' => 'POST /api/data-api.php?action=update-city',
        'delete_city' => 'POST /api/data-api.php?action=delete-city',
        'update_poi' => 'POST /api/data-api.php?action=update-poi',
        'delete_poi' => 'POST /api/data-api.php?action=delete-poi',
        'sync_airtable' => 'GET /api/sync-airtable.php'
    ];
    
    respond(true, $diagnostics);
    
} catch (Exception $e) {
    respond(false, ['error' => $e->getMessage()], 500);
}
?>
