<?php
/**
 * Диагностический скрипт для определения названий полей POI в Airtable
 * Выводит все поля первой записи POI с их названиями и типами
 */

header('Content-Type: application/json; charset=utf-8');

// Загружаем конфигурацию
$cfg = [];
$cfgFile = __DIR__.'/config.php';
if (file_exists($cfgFile)) {
    $cfg = require $cfgFile;
    if (!is_array($cfg)) $cfg = [];
}

// Получаем данные Airtable
$airReg = $cfg['airtable_registry'] ?? null;
if (!$airReg) {
    echo json_encode([
        'ok' => false, 
        'error' => 'airtable_registry не найден в config.php'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$baseId = $airReg['baseId'] ?? ($airReg['base_id'] ?? '');
$tables = $airReg['tables'] ?? [];
$poiTable = $tables['poi'] ?? [];
$tableId = $poiTable['tableId'] ?? ($poiTable['table_id'] ?? '');

// API Key
$pat = $airReg['api_key'] ?? ($cfg['airtable']['api_key'] ?? '');
if (!$pat) {
    $pat = $cfg['airtable']['token'] ?? '';
}
if (!$pat) {
    $pat = getenv('AIRTABLE_PAT') ?: '';
}

if (!$baseId || !$tableId || !$pat) {
    echo json_encode([
        'ok' => false,
        'error' => 'Отсутствуют данные Airtable',
        'baseId' => $baseId ? 'OK' : 'MISSING',
        'tableId' => $tableId ? 'OK' : 'MISSING',
        'api_key' => $pat ? 'OK' : 'MISSING'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Запрос к Airtable - получаем одну запись POI
$url = "https://api.airtable.com/v0/{$baseId}/{$tableId}?pageSize=1";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $pat
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10
]);

$resp = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    echo json_encode([
        'ok' => false,
        'error' => 'cURL error: ' . $err
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($code < 200 || $code >= 300) {
    echo json_encode([
        'ok' => false,
        'error' => 'Airtable вернул код ' . $code,
        'response' => json_decode($resp, true)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$json = json_decode($resp, true);
$records = $json['records'] ?? [];

if (empty($records)) {
    echo json_encode([
        'ok' => false,
        'error' => 'Таблица POI пуста'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Извлекаем первую запись
$firstRecord = $records[0];
$fields = $firstRecord['fields'] ?? [];

// Анализируем каждое поле
$fieldAnalysis = [];
foreach ($fields as $fieldName => $fieldValue) {
    $fieldAnalysis[$fieldName] = [
        'name' => $fieldName,
        'type' => gettype($fieldValue),
        'value' => is_array($fieldValue) ? 
            (count($fieldValue) > 5 ? '['.(count($fieldValue)).' items]' : $fieldValue) : 
            (is_string($fieldValue) && strlen($fieldValue) > 100 ? substr($fieldValue, 0, 100).'...' : $fieldValue),
        'is_array' => is_array($fieldValue),
        'array_length' => is_array($fieldValue) ? count($fieldValue) : null
    ];
}

// Выводим результат
echo json_encode([
    'ok' => true,
    'message' => 'Анализ полей POI в Airtable',
    'baseId' => $baseId,
    'tableId' => $tableId,
    'record_id' => $firstRecord['id'] ?? 'unknown',
    'total_fields' => count($fields),
    'fields' => $fieldAnalysis,
    'field_names' => array_keys($fields),
    'mapping_suggestion' => [
        'Найденные поля для маппинга:',
        '================================'
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>

