<?php
// Проверка структуры Airtable для правильного сопоставления полей
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fetchAirtableStructure($baseId, $tableId, $pat) {
    $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}?pageSize=1";
    
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
    return $data['records'][0]['fields'] ?? [];
}

try {
    $config = include 'config.php';
    $pat = getenv('AIRTABLE_PAT') ?: ($config['airtable']['api_key'] ?? 'PLACEHOLDER_FOR_REAL_API_KEY');
    $baseId = 'apppwhjFN82N9zNqm';
    
    if ($pat === 'PLACEHOLDER_FOR_REAL_API_KEY') {
        respond(false, ['error' => 'Airtable token not configured'], 400);
    }
    
    $structure = [];
    
    // Проверяем структуру таблиц
    $tables = [
        'regions' => 'tblbSajWkzI8X7M4U',
        'cities' => 'tblHaHc9NV0mA8bSa',
        'pois' => 'tbl8X7M4U'
    ];
    
    foreach ($tables as $name => $tableId) {
        try {
            $fields = fetchAirtableStructure($baseId, $tableId, $pat);
            $structure[$name] = [
                'table_id' => $tableId,
                'fields' => array_keys($fields),
                'sample_record' => $fields
            ];
        } catch (Exception $e) {
            $structure[$name] = [
                'table_id' => $tableId,
                'error' => $e->getMessage()
            ];
        }
    }
    
    respond(true, [
        'base_id' => $baseId,
        'structure' => $structure,
        'mapping_suggestions' => [
            'regions' => [
                'local_id' => 'id (Airtable Record ID)',
                'local_name_ru' => 'name_ru',
                'local_name_en' => 'name_en', 
                'local_business_id' => 'business_id',
                'airtable_fields' => 'Name (RU), Name (EN), ID'
            ],
            'cities' => [
                'local_id' => 'id (Airtable Record ID)',
                'local_name_ru' => 'name_ru',
                'local_name_en' => 'name_en',
                'local_business_id' => 'business_id',
                'local_type' => 'type',
                'local_region_id' => 'region_id',
                'airtable_fields' => 'Name (RU), Name (EN), A ID, Type, Region'
            ],
            'pois' => [
                'local_id' => 'id (Airtable Record ID)',
                'local_name_ru' => 'name_ru',
                'local_name_en' => 'name_en',
                'local_business_id' => 'business_id',
                'local_category' => 'category',
                'local_city_id' => 'city_id',
                'local_region_id' => 'region_id',
                'airtable_fields' => 'Name (RU), Name (EN), POI ID, Category, City ID, Region'
            ]
        ]
    ]);
    
} catch (Exception $e) {
    respond(false, ['error' => $e->getMessage()], 500);
}
?>
