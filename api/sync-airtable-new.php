<?php
// api/sync-airtable-new.php
require_once __DIR__ . '/sync-lib.php';

function fetchAirtableData($baseId, $tableId, $pat) {
    $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}?pageSize=100";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $pat],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Airtable API error: HTTP {$httpCode}");
    }
    
    $data = json_decode($response, true);
    return $data['records'] ?? [];
}

function extractLinkedRecordId($fields, $fieldNames) {
    foreach ($fieldNames as $fieldName) {
        if (isset($fields[$fieldName])) {
            $value = $fields[$fieldName];
            if (is_array($value) && !empty($value)) {
                return $value[0];
            } elseif (is_string($value)) {
                return $value;
            }
        }
    }
    return null;
}

// Получаем токен
$config = include 'config.php';
$pat = ($config['airtable']['api_key'] ?? '')
    ?: (($config['airtable']['token'] ?? '')
    ?: (($config['airtable_pat'] ?? '')
    ?: (($config['airtable_registry']['api_key'] ?? '')
    ?: (($config['airtable_registry']['token'] ?? '')
    ?: (getenv('AIRTABLE_PAT') ?: (getenv('AIRTABLE_API_KEY') ?: ''))))));

if (!$pat || $pat === 'PLACEHOLDER_FOR_REAL_API_KEY') {
    echo json_encode(['ok' => false, 'error' => 'Airtable token not configured']);
    exit;
}

$baseId = 'apppwhjFN82N9zNqm';

try {
    // Получаем данные из Airtable
    $regions = fetchAirtableData($baseId, 'tblbSajWkzI8X7M4U', $pat);
    $cities = fetchAirtableData($baseId, 'tblHaHc9NV0mA8bSa', $pat);
    $pois = fetchAirtableData($baseId, 'tblVCmFcHRpXUT24y', $pat);

    $db = konstructour_db();

    $db->beginTransaction();

    // 1) Регионы
    $rc = 0;
    foreach ($regions as $record) {
        $data = [
            'id' => $record['id'],
            'name_ru' => $record['fields']['Name (RU)'] ?? $record['fields']['Название (RU)'] ?? 'Unknown',
            'name_en' => $record['fields']['Name (EN)'] ?? $record['fields']['Название (EN)'] ?? null,
            'business_id' => $record['fields']['ID'] ?? null
        ];
        saveRegion($db, $data);
        $rc++;
    }

    // 2) Города
    $cc = 0;
    foreach ($cities as $record) {
        $regionId = extractLinkedRecordId($record['fields'], ['Region', 'Регион', 'Regions', 'Регионы']);
        
        if (!$regionId) {
            error_log("Skipping city " . ($record['fields']['Name (RU)'] ?? 'Unknown') . " - no region ID");
            continue;
        }
        
        $data = [
            'id' => $record['id'],
            'name_ru' => $record['fields']['Name (RU)'] ?? $record['fields']['Название (RU)'] ?? 'Unknown',
            'name_en' => $record['fields']['Name (EN)'] ?? $record['fields']['Название (EN)'] ?? null,
            'business_id' => $record['fields']['ID'] ?? $record['fields']['Идентификатор'] ?? null,
            'type' => $record['fields']['Type'] ?? 'city',
            'region_id' => $regionId
        ];
        saveCity($db, $data);
        $cc++;
    }

    // 3) POI
    $pc = 0;
    foreach ($pois as $record) {
        $regionId = extractLinkedRecordId($record['fields'], ['Region', 'Регион', 'Regions', 'Регионы']);
        $cityId = extractLinkedRecordId($record['fields'], ['City', 'Город', 'Cities', 'Города']);
        
        $data = [
            'id' => $record['id'],
            'name_ru' => $record['fields']['Name (RU)'] ?? $record['fields']['Название (RU)'] ?? 'Unknown',
            'name_en' => $record['fields']['Name (EN)'] ?? $record['fields']['Название (EN)'] ?? null,
            'category' => $record['fields']['Category'] ?? $record['fields']['Категория'] ?? null,
            'place_id' => $record['fields']['Place ID'] ?? null,
            'published' => $record['fields']['Published'] ?? false,
            'business_id' => $record['fields']['ID'] ?? null,
            'city_id' => $cityId,
            'region_id' => $regionId,
            'description' => $record['fields']['Description'] ?? $record['fields']['Описание'] ?? null,
            'latitude' => $record['fields']['Latitude'] ?? null,
            'longitude' => $record['fields']['Longitude'] ?? null
        ];
        savePoi($db, $data);
        $pc++;
    }

    $db->commit();

    // Запишем точные итоги
    $db->prepare("INSERT INTO sync_log (table_name, action, record_id, timestamp) VALUES ('regions','upsert',:n, CURRENT_TIMESTAMP)")
       ->execute([':n'=>"count=$rc"]);
    $db->prepare("INSERT INTO sync_log (table_name, action, record_id, timestamp) VALUES ('cities','upsert',:n, CURRENT_TIMESTAMP)")
       ->execute([':n'=>"count=$cc"]);
    $db->prepare("INSERT INTO sync_log (table_name, action, record_id, timestamp) VALUES ('pois','upsert',:n, CURRENT_TIMESTAMP)")
       ->execute([':n'=>"count=$pc"]);

    echo json_encode(['ok'=>true,'regions'=>$rc,'cities'=>$cc,'pois'=>$pc]);

} catch (Throwable $e) {
    // Любая ошибка — откат
    if ($db->inTransaction()) { $db->rollBack(); }

    // Развёрнутый лог в error_log и машинный ответ
    error_log("[SYNC] FAILED: ".$e->getMessage()." at ".$e->getFile().":".$e->getLine());
    error_log("[SYNC] TRACE: ".$e->getTraceAsString());

    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    http_response_code(500);
}
?>
