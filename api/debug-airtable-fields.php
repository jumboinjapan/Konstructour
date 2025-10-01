<?php
// api/debug-airtable-fields.php
// Отладка полей Airtable для диагностики синхронизации

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once 'secret-airtable.php';

try {
    // Получаем токен Airtable из переменных окружения
    $token = getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_API_KEY');
    
    if (!$token) {
        // Пробуем загрузить из файла секретов
        try {
            $tokens = load_airtable_tokens();
            $token = $tokens['current'] ?? null;
        } catch (Exception $e) {
            // Игнорируем ошибки загрузки секретов
        }
    }
    
    if (!$token) {
        // Используем тестовый токен для диагностики
        $token = 'pat' . str_repeat('A', 14) . '.' . str_repeat('B', 22);
    }
    
    $baseId = 'apppwhjFN82N9zNqm';
    $tables = [
        'regions' => 'tblbSajWkzI8X7M4U',
        'cities' => 'tblHaHc9NV0mA8bSa',
        'pois' => 'tblVCmFcHRpXUT24y'
    ];
    
    $result = [];
    
    foreach ($tables as $tableName => $tableId) {
        $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}?maxRecords=1";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $result[$tableName] = [
                'error' => "HTTP $httpCode",
                'response' => $response
            ];
            continue;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['records']) && count($data['records']) > 0) {
            $record = $data['records'][0];
            $fields = $record['fields'] ?? [];
            
            $result[$tableName] = [
                'ok' => true,
                'record_id' => $record['id'],
                'fields' => array_keys($fields),
                'field_values' => $fields
            ];
        } else {
            $result[$tableName] = [
                'ok' => true,
                'records_count' => 0,
                'message' => 'No records found'
            ];
        }
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'message' => 'Debug failed'
    ]);
}
?>
