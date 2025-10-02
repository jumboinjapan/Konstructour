<?php
// Тестовый скрипт для проверки деплоя

require_once __DIR__ . '/config.features.php';
require_once __DIR__ . '/secret-manager.php';

header('Content-Type: application/json; charset=utf-8');

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $results = [];
    
    // 1. Проверка фича-флагов
    $results['feature_flags'] = [
        'SYNC_REFERENCES_ENABLED' => defined('SYNC_REFERENCES_ENABLED') ? SYNC_REFERENCES_ENABLED : false,
        'SYNC_TICKETS_ENABLED' => defined('SYNC_TICKETS_ENABLED') ? SYNC_TICKETS_ENABLED : false,
        'BATCH_UPSERT_ENABLED' => defined('BATCH_UPSERT_ENABLED') ? BATCH_UPSERT_ENABLED : false,
        'RETRY_ENABLED' => defined('RETRY_ENABLED') ? RETRY_ENABLED : false,
    ];
    
    // 2. Проверка SecretManager
    $secretPath = SecretManager::path();
    $results['secret_manager'] = [
        'path' => $secretPath,
        'file_exists' => file_exists($secretPath),
        'file_readable' => file_exists($secretPath) && is_readable($secretPath),
        'php_user' => get_current_user(),
    ];
    
    // 3. Попытка загрузки токенов
    try {
        $tokens = SecretManager::load();
        $results['tokens'] = [
            'loaded' => true,
            'current_present' => !empty($tokens['current']['token']),
            'next_present' => !empty($tokens['next']['token']),
            'current_token_preview' => !empty($tokens['current']['token']) ? 
                substr($tokens['current']['token'], 0, 10) . '...' : null,
        ];
    } catch (Exception $e) {
        $results['tokens'] = [
            'loaded' => false,
            'error' => $e->getMessage(),
            'error_reason' => $e instanceof SecretError ? $e->reason : 'unknown',
        ];
    }
    
    // 4. Проверка Airtable клиента
    try {
        require_once __DIR__ . '/lib/airtable_client.php';
        $air = new Airtable('apppwhjFN82N9zNqm');
        $results['airtable_client'] = [
            'created' => true,
            'base_id' => 'apppwhjFN82N9zNqm',
        ];
    } catch (Exception $e) {
        $results['airtable_client'] = [
            'created' => false,
            'error' => $e->getMessage(),
        ];
    }
    
    // 5. Проверка новых API эндпоинтов
    $endpoints = [
        'diagnostics.php' => '/api/diagnostics.php',
        'reference-parity.php' => '/api/reference-parity.php',
        'tickets-discover.php' => '/api/tickets-discover.php',
        'sync-references.php' => '/api/sync-references.php',
        'sync-tickets.php' => '/api/sync-tickets.php',
        'poi-batch-sync.php' => '/api/poi-batch-sync.php',
    ];
    
    $results['endpoints'] = [];
    foreach ($endpoints as $file => $url) {
        $results['endpoints'][$file] = [
            'file_exists' => file_exists(__DIR__ . '/' . $file),
            'url' => $url,
        ];
    }
    
    // 6. Проверка миграций
    $migrationFiles = [
        'migrations_2025_10_02_simple.sql',
        'migrations_2025_10_02_safe.sql',
        'migrations_2025_10_02.sql',
    ];
    
    $results['migrations'] = [];
    foreach ($migrationFiles as $file) {
        $results['migrations'][$file] = file_exists(__DIR__ . '/sql/' . $file);
    }
    
    // 7. Общий статус
    $overallOk = $results['feature_flags']['SYNC_REFERENCES_ENABLED'] && 
                 $results['secret_manager']['file_exists'] &&
                 $results['tokens']['loaded'];
    
    respond($overallOk, [
        'message' => $overallOk ? 'Deployment test passed' : 'Deployment test failed',
        'results' => $results,
        'timestamp' => date('c'),
    ]);
    
} catch (Exception $e) {
    respond(false, [
        'error' => $e->getMessage(),
        'timestamp' => date('c'),
    ], 500);
}
?>
