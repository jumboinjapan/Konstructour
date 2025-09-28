<?php
// Airtable to Database Sync
require_once 'database.php';
require_once 'config.php';

function syncFromAirtable() {
    $db = new Database();
    $config = include 'config.php';
    
    // Airtable settings
    $baseId = 'apppwhjFN82N9zNqm';
    $pat = getenv('AIRTABLE_PAT') ?: 'PLACEHOLDER_FOR_REAL_API_KEY';
    
    if (!$pat) {
        throw new Exception('Airtable token not configured');
    }
    
    $results = [
        'regions' => 0,
        'cities' => 0,
        'pois' => 0,
        'errors' => []
    ];
    
    try {
        // Sync Regions
        $regions = fetchAirtableData($baseId, 'tblbSajWkzI8X7M4U', $pat);
        foreach ($regions as $record) {
            $data = [
                'id' => $record['id'],
                'name_ru' => $record['fields']['Name (RU)'] ?? $record['fields']['Название (RU)'] ?? 'Unknown',
                'name_en' => $record['fields']['Name (EN)'] ?? $record['fields']['Название (EN)'] ?? null,
                'business_id' => $record['fields']['ID'] ?? null
            ];
            $db->saveRegion($data);
            $results['regions']++;
            
            // Debug: log region data
            error_log("Region: " . $data['name_ru'] . 
                     " | ID: " . $data['id'] . 
                     " | Business ID: " . ($data['business_id'] ?? 'NULL'));
        }
        
        // Sync Cities
        $cities = fetchAirtableData($baseId, 'tblHaHc9NV0mA8bSa', $pat);
        foreach ($cities as $record) {
            $regionId = extractLinkedRecordId($record['fields'], ['Region', 'Регион', 'Regions', 'Регионы']);
            
            $data = [
                'id' => $record['id'],
                'name_ru' => $record['fields']['Name (RU)'] ?? $record['fields']['Название (RU)'] ?? 'Unknown',
                'name_en' => $record['fields']['Name (EN)'] ?? $record['fields']['Название (EN)'] ?? null,
                'business_id' => $record['fields']['ID'] ?? $record['fields']['Идентификатор'] ?? null,
                'type' => $record['fields']['Type'] ?? 'city',
                'region_id' => $regionId
            ];
            $db->saveCity($data);
            $results['cities']++;
        }
        
        // Sync POI - СТРОГО ТОЛЬКО ПО BUSINESS ID
        $pois = fetchAirtableData($baseId, 'tblVCmFcHRpXUT24y', $pat);
        foreach ($pois as $record) {
            // СТРОГО: Получаем Airtable Record ID региона из поля Regions
            $regionAirtableId = null;
            if (isset($record['fields']['Regions'])) {
                $regions = $record['fields']['Regions'];
                if (is_array($regions) && !empty($regions)) {
                    $regionAirtableId = $regions[0];
                } elseif (is_string($regions)) {
                    $regionAirtableId = $regions;
                }
            }
            
            // СТРОГО: Найдем регион по Airtable Record ID и получим его business ID
            $regionId = null;
            $regionBusinessId = null;
            if ($regionAirtableId) {
                $regions = $db->getRegions();
                foreach ($regions as $region) {
                    if ($region['id'] === $regionAirtableId) {
                        $regionId = $region['id'];
                        $regionBusinessId = $region['business_id'];
                        break;
                    }
                }
            }
            
            // СТРОГО: Найдем город по префектуре в найденном регионе
            $cityId = null;
            if ($regionId && isset($record['fields']['Prefecture (RU)'])) {
                $prefecture = $record['fields']['Prefecture (RU)'];
                $cities = $db->getCitiesByRegion($regionId);
                foreach ($cities as $city) {
                    // СТРОГО: Точное совпадение по названию префектуры
                    if ($city['name_ru'] === $prefecture) {
                        $cityId = $city['id'];
                        break;
                    }
                }
            }
            
            // Debug: log POI data
            error_log("POI: " . ($record['fields']['POI Name (RU)'] ?? 'Unknown') . 
                     " | Region Airtable ID: " . ($regionAirtableId ?? 'NULL') . 
                     " | Region Business ID: " . ($regionBusinessId ?? 'NULL') . 
                     " | Region ID: " . ($regionId ?? 'NULL') . 
                     " | City Business ID: " . ($record['fields']['City ID'] ?? 'NULL') . 
                     " | City ID: " . ($cityId ?? 'NULL'));
            
            $data = [
                'id' => $record['id'],
                'name_ru' => $record['fields']['POI Name (RU)'] ?? 'Unknown',
                'name_en' => $record['fields']['POI Name (EN)'] ?? null,
                'category' => $record['fields']['POI Category (RU)'] ?? $record['fields']['Category'] ?? null,
                'place_id' => $record['fields']['Place ID'] ?? null,
                'published' => $record['fields']['Published'] ?? false,
                'business_id' => $record['fields']['POI ID'] ?? null,
                'city_id' => $cityId,
                'region_id' => $regionId,
                'description' => $record['fields']['Description (RU)'] ?? $record['fields']['Description'] ?? null,
                'latitude' => $record['fields']['Latitude'] ?? null,
                'longitude' => $record['fields']['Longitude'] ?? null
            ];
            $db->savePoi($data);
            $results['pois']++;
        }
        
        // Log sync
        $db->getConnection()->prepare("
            INSERT INTO sync_log (table_name, action, record_id) 
            VALUES (?, ?, ?)
        ")->execute(['all', 'sync', 'full_sync']);
        
    } catch (Exception $e) {
        $results['errors'][] = $e->getMessage();
    }
    
    return $results;
}

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

// Run sync if called directly
if (basename($_SERVER['PHP_SELF']) === 'sync-airtable.php') {
    try {
        $results = syncFromAirtable();
        echo json_encode($results, JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
    }
}
?>
