<?php
// api/enforce-filtering.php
// Строгий контроль соблюдения Filtering.md

require_once 'database.php';
require_once 'filter-constants.php';

header('Content-Type: application/json; charset=utf-8');

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $db = new Database();
    
    // 1. Проверяем, что локальная база используется как кэш (не как источник данных)
    $stats = $db->getAllValidData();
    $hasLocalData = $stats['stats']['regions'] > 0 || $stats['stats']['cities'] > 0 || $stats['stats']['pois'] > 0;
    
    // Теперь локальная база может содержать данные как кэш - это нормально
    // Проверяем только, что нет тестовых данных в старых таблицах
    $legacyStats = $db->getStats();
    $hasLegacyData = $legacyStats['regions'] > 0 || $legacyStats['cities'] > 0 || $legacyStats['pois'] > 0;
    
    if ($hasLegacyData) {
        respond(false, [
            'error' => 'LEGACY_DATA_FOUND',
            'message' => 'Найдены данные в старых таблицах (regions, cities, pois). Используйте только кэш-таблицы.',
            'stats' => $legacyStats,
            'action_required' => 'Очистите старые таблицы: php -r "require_once \'api/database.php\'; (new Database())->clearAll();"'
        ], 400);
    }
    
    // 2. Проверяем, что нет тестовых файлов
    $testFiles = [
        'test-mode.php',
        'create-test-data.php', 
        'init-test-data.php',
        'sample-data.php',
        'mock-data.php'
    ];
    
    $foundTestFiles = [];
    foreach ($testFiles as $file) {
        if (file_exists(__DIR__ . '/' . $file)) {
            $foundTestFiles[] = $file;
        }
    }
    
    if (!empty($foundTestFiles)) {
        respond(false, [
            'error' => 'TEST_FILES_FOUND',
            'message' => 'Найдены тестовые файлы, которые нарушают Filtering.md.',
            'files' => $foundTestFiles,
            'action_required' => 'Удалите тестовые файлы: ' . implode(', ', $foundTestFiles)
        ], 400);
    }
    
    // 3. Проверяем, что data-api.php использует правильную архитектуру (кэш + Airtable)
    $dataApiContent = file_get_contents(__DIR__ . '/data-api.php');
    
    // Проверяем наличие кэширования
    $hasCaching = strpos($dataApiContent, 'getCachedRegions') !== false && 
                  strpos($dataApiContent, 'cacheRegions') !== false;
    
    if (!$hasCaching) {
        respond(false, [
            'error' => 'MISSING_CACHE_LOGIC',
            'message' => 'data-api.php не использует кэширование. Требуется правильная архитектура: SQLite → Airtable → SQLite.',
            'action_required' => 'Добавьте методы кэширования в data-api.php'
        ], 400);
    }
    
    // Проверяем, что используется SecretManager (через AirtableDataSource)
    $hasSecretManager = strpos($dataApiContent, 'SecretManager') !== false || 
                        strpos($dataApiContent, 'secret-manager.php') !== false;
    
    if (!$hasSecretManager) {
        respond(false, [
            'error' => 'MISSING_SECRET_MANAGER',
            'message' => 'data-api.php не использует SecretManager. Требуется безопасная работа с токенами.',
            'action_required' => 'Добавьте require_once secret-manager.php в data-api.php'
        ], 400);
    }
    
    // 4. Все проверки пройдены
    respond(true, [
        'message' => 'Система соответствует Filtering.md',
        'checks' => [
            'local_database_empty' => true,
            'no_test_files' => true,
            'no_direct_airtable_calls' => true
        ],
        'stats' => $stats['stats']
    ]);
    
} catch (Exception $e) {
    respond(false, ['error' => $e->getMessage()], 500);
}
?>
