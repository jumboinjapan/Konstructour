<?php
/**
 * Диагностический скрипт для проверки конфигурации Airtable
 * GET /api/debug-airtable.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once 'database.php';

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // Загружаем конфигурацию
    $cfg = [];
    $cfgFile = __DIR__.'/config.php';
    if (file_exists($cfgFile)) {
        $cfg = require $cfgFile;
        if (!is_array($cfg)) $cfg = [];
    }
    
    $airReg = $cfg['airtable_registry'] ?? null;
    
    if (!$airReg) {
        respond(false, ['error' => 'Airtable registry not configured'], 500);
    }
    
    $baseId = $airReg['baseId'] ?? ($airReg['base_id'] ?? '');
    $tables = $airReg['tables'] ?? [];
    $poiTable = $tables['poi'] ?? [];
    $tableId = $poiTable['tableId'] ?? ($poiTable['table_id'] ?? '');
    $pat = $airReg['api_key'] ?? ($cfg['airtable']['api_key'] ?? '');
    
    $result = [
        'config' => [
            'baseId' => $baseId,
            'tableId' => $tableId,
            'hasApiKey' => !empty($pat),
            'apiKeyLength' => strlen($pat)
        ],
        'airtable_registry' => $airReg,
        'tables' => $tables
    ];
    
    // Проверяем доступность Airtable API
    if ($baseId && $tableId && $pat) {
        $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $pat,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result['airtable_test'] = [
            'url' => $url,
            'http_code' => $code,
            'curl_error' => $err,
            'response' => $resp ? json_decode($resp, true) : null
        ];
        
        if ($code === 200) {
            $result['status'] = 'success';
            $result['message'] = 'Airtable API доступен';
        } else {
            $result['status'] = 'error';
            $result['message'] = "Airtable API недоступен: HTTP $code";
        }
    } else {
        $result['status'] = 'error';
        $result['message'] = 'Неполная конфигурация Airtable';
    }
    
    respond(true, $result);
    
} catch (Exception $e) {
    respond(false, [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], 500);
}
