<?php
// Диагностика структуры таблицы городов в Airtable
header('Content-Type: application/json; charset=utf-8');

// Загружаем конфигурацию
$cfg = require __DIR__.'/config.php';
$baseId = $cfg['airtable_registry']['baseId'] ?? '';
$tableId = $cfg['airtable_registry']['tables']['city']['tableId'] ?? '';
$apiKey = $cfg['airtable_registry']['api_key'] ?? '';

if (!$apiKey || $apiKey === 'PLACEHOLDER_FOR_REAL_API_KEY') {
    echo json_encode([
        'error' => 'Airtable API key not configured',
        'message' => 'Please set your Airtable API key in config.php',
        'config' => [
            'baseId' => $baseId,
            'tableId' => $tableId,
            'apiKey' => $apiKey
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Функция для вызова Airtable API
function airCall($method, $url, $apiKey, $data = null) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [$httpCode, $response, $error];
}

try {
    // Получаем структуру таблицы
    $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}?pageSize=1";
    list($code, $response, $error) = airCall('GET', $url, $apiKey);
    
    if ($code !== 200) {
        echo json_encode([
            'error' => 'Airtable API error',
            'httpCode' => $code,
            'response' => $response,
            'curlError' => $error
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $data = json_decode($response, true);
    $record = $data['records'][0] ?? null;
    
    if (!$record) {
        echo json_encode([
            'error' => 'No records found in cities table',
            'response' => $data
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $fields = $record['fields'] ?? [];
    $fieldNames = array_keys($fields);
    
    // Анализируем поля
    $analysis = [
        'totalRecords' => $data['recordCount'] ?? 'unknown',
        'fields' => $fieldNames,
        'fieldAnalysis' => []
    ];
    
    foreach ($fieldNames as $fieldName) {
        $value = $fields[$fieldName];
        $analysis['fieldAnalysis'][$fieldName] = [
            'type' => gettype($value),
            'value' => $value,
            'isArray' => is_array($value),
            'arrayCount' => is_array($value) ? count($value) : null
        ];
    }
    
    // Ищем поля, связанные с регионами
    $regionFields = [];
    foreach ($fieldNames as $fieldName) {
        if (stripos($fieldName, 'region') !== false || 
            stripos($fieldName, 'регион') !== false ||
            stripos($fieldName, 'prefecture') !== false ||
            stripos($fieldName, 'префектура') !== false) {
            $regionFields[] = $fieldName;
        }
    }
    
    $analysis['regionFields'] = $regionFields;
    
    // Ищем поля с ID
    $idFields = [];
    foreach ($fieldNames as $fieldName) {
        if (stripos($fieldName, 'id') !== false || 
            stripos($fieldName, 'ID') !== false) {
            $idFields[] = $fieldName;
        }
    }
    
    $analysis['idFields'] = $idFields;
    
    echo json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Exception occurred',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
