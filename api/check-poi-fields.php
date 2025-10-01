<?php
/**
 * Проверка соответствия полей POI между локальной БД и Airtable
 * GET /api/check-poi-fields.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once 'database.php';

function respondCheck($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Получаем структуру локальной БД
    $stmt = $conn->query("PRAGMA table_info(pois)");
    $dbColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dbColumns[] = $row['name'];
    }
    
    // Загружаем конфигурацию Airtable напрямую
    require_once 'config-read.php';
    $cfg = getConfig();
    $airReg = $cfg['airtable_registry'] ?? null;
    if (!$airReg) {
        respondCheck(false, ['error' => 'Airtable registry not configured'], 500);
    }
    
    $baseId = $airReg['baseId'] ?? ($airReg['base_id'] ?? '');
    $tables = $airReg['tables'] ?? [];
    $poiTable = $tables['poi'] ?? [];
    $tableId = $poiTable['tableId'] ?? ($poiTable['table_id'] ?? '');
    $pat = $airReg['api_key'] ?? ($cfg['airtable']['api_key'] ?? '');
    
    if (!$baseId || !$tableId || !$pat) {
        respondCheck(false, ['error' => 'Incomplete Airtable configuration'], 500);
    }
    
    // Получаем схему таблицы Airtable
    $url = "https://api.airtable.com/v0/meta/bases/{$baseId}/tables";
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
    
    if ($code !== 200) {
        respondCheck(false, [
            'error' => "Failed to get Airtable schema: HTTP $code",
            'response' => $resp
        ], 500);
    }
    
    $schema = json_decode($resp, true);
    $airtableFields = [];
    
    // Находим таблицу POI
    foreach ($schema['tables'] as $table) {
        if ($table['id'] === $tableId) {
            foreach ($table['fields'] as $field) {
                $airtableFields[] = $field['name'];
            }
            break;
        }
    }
    
    // Определяем маппинг полей
    $fieldMapping = [
        // Локальная БД => Airtable
        'id' => 'Airtable Record ID',
        'business_id' => 'POI ID',
        'name_ru' => 'POI Name (RU)',
        'name_en' => 'POI Name (EN)',
        'prefecture_ru' => 'Prefecture (RU)',
        'prefecture_en' => 'Prefecture (EN)',
        'categories_ru' => 'POI Category (RU)',
        'categories_en' => 'POI Category (EN)',
        'place_id' => 'Place ID',
        'description_ru' => 'Description (RU)',
        'description_en' => 'Description (EN)',
        'website' => 'Website',
        'working_hours' => 'Working Hours',
        'notes' => 'Notes',
        'city_id' => 'City Location',
        'region_id' => 'Regions'
    ];
    
    // Проверяем соответствие полей
    $missingInAirtable = [];
    $missingInDB = [];
    $mappingStatus = [];
    
    foreach ($fieldMapping as $dbField => $airtableField) {
        $dbExists = in_array($dbField, $dbColumns);
        $airtableExists = in_array($airtableField, $airtableFields);
        
        $mappingStatus[$dbField] = [
            'airtable_field' => $airtableField,
            'db_exists' => $dbExists,
            'airtable_exists' => $airtableExists,
            'status' => $dbExists && $airtableExists ? 'ok' : 'missing'
        ];
        
        if ($dbExists && !$airtableExists) {
            $missingInAirtable[] = $airtableField;
        }
        if (!$dbExists && $airtableExists) {
            $missingInDB[] = $dbField;
        }
    }
    
    // Дополнительные поля в Airtable (не в маппинге)
    $extraAirtableFields = [];
    foreach ($airtableFields as $field) {
        if (!in_array($field, array_values($fieldMapping))) {
            $extraAirtableFields[] = $field;
        }
    }
    
    // Дополнительные поля в БД (не в маппинге)
    $extraDBFields = [];
    foreach ($dbColumns as $field) {
        if (!in_array($field, array_keys($fieldMapping))) {
            $extraDBFields[] = $field;
        }
    }
    
    $result = [
        'database_columns' => $dbColumns,
        'airtable_fields' => $airtableFields,
        'field_mapping' => $mappingStatus,
        'missing_in_airtable' => $missingInAirtable,
        'missing_in_db' => $missingInDB,
        'extra_airtable_fields' => $extraAirtableFields,
        'extra_db_fields' => $extraDBFields,
        'summary' => [
            'total_db_columns' => count($dbColumns),
            'total_airtable_fields' => count($airtableFields),
            'mapped_fields' => count(array_filter($mappingStatus, fn($s) => $s['status'] === 'ok')),
            'missing_fields' => count($missingInAirtable) + count($missingInDB)
        ]
    ];
    
    respondCheck(true, $result);
    
} catch (Exception $e) {
    respondCheck(false, [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], 500);
}
