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
    
    // 1. Проверяем, что локальная база пустая (как должно быть без Airtable)
    $stats = $db->getAllValidData();
    $hasLocalData = $stats['stats']['regions'] > 0 || $stats['stats']['cities'] > 0 || $stats['stats']['pois'] > 0;
    
    if ($hasLocalData) {
        respond(false, [
            'error' => 'VIOLATION_OF_FILTERING_MD',
            'message' => 'Локальная база содержит данные без синхронизации с Airtable. Это нарушает Filtering.md.',
            'stats' => $stats['stats'],
            'action_required' => 'Очистите локальную базу данных: php -r "require_once \'api/database.php\'; (new Database())->clearAll();"'
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
    
    // 3. Проверяем, что data-api.php не делает прямых обращений к Airtable
    $dataApiContent = file_get_contents(__DIR__ . '/data-api.php');
    $airtableDirectCalls = [
        'AirtableDataSource',
        'getRegionsFromAirtable',
        'getCitiesFromAirtable', 
        'getPoisFromAirtable',
        'api.airtable.com'
    ];
    
    $foundDirectCalls = [];
    foreach ($airtableDirectCalls as $call) {
        if (strpos($dataApiContent, $call) !== false) {
            $foundDirectCalls[] = $call;
        }
    }
    
    if (!empty($foundDirectCalls)) {
        respond(false, [
            'error' => 'DIRECT_AIRTABLE_CALLS',
            'message' => 'data-api.php содержит прямые обращения к Airtable. Это нарушает Filtering.md.',
            'calls' => $foundDirectCalls,
            'action_required' => 'data-api.php должен работать только с локальной базой данных'
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
