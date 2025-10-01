<?php
// Airtable to Database Sync
require_once 'database.php';
require_once 'config.php';

function syncFromAirtable() {
    $db = new Database();
    $config = include 'config.php';
    
    // Airtable settings
    $baseId = 'apppwhjFN82N9zNqm';
    
    // Получаем токен тем же способом, что и test-proxy.php
    $pat = ($config['airtable']['api_key'] ?? '')
        ?: (($config['airtable']['token'] ?? '')
        ?: (($config['airtable_pat'] ?? '')
        ?: (($config['airtable_registry']['api_key'] ?? '')
        ?: (($config['airtable_registry']['token'] ?? '')
        ?: (getenv('AIRTABLE_PAT') ?: (getenv('AIRTABLE_API_KEY') ?: ''))))));
    
    if (!$pat || $pat === 'PLACEHOLDER_FOR_REAL_API_KEY') {
        throw new Exception('Airtable token not configured');
    }
    
    // Отладочная информация (удалить в продакшене)
    error_log("Airtable PAT: " . substr($pat, 0, 10) . "...");
    
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
            
            // Отладочная информация
            error_log("City: " . $data['name_ru'] . " | Region ID: " . ($regionId ?? 'NULL') . " | Fields: " . json_encode(array_keys($record['fields'])));
            
            // Пропускаем города без региона
            if (!$regionId) {
                error_log("Skipping city " . $data['name_ru'] . " - no region ID");
                continue;
            }
            
            try {
                $db->saveCity($data);
                $results['cities']++;
            } catch (Exception $e) {
                error_log("Error saving city " . $data['name_ru'] . ": " . $e->getMessage());
                $results['errors'][] = "Error saving city " . $data['name_ru'] . ": " . $e->getMessage();
            }
        }
        
        // Sync POI - СТРОГО ТОЛЬКО ПО BUSINESS ID
        $pois = fetchAirtableData($baseId, 'tblVCmFcHRpXUT24y', $pat);
        foreach ($pois as $record) {
            // СТРОГО: Получаем Airtable record ID региона из поля Regions
            $regionAirtableId = null;
            if (isset($record['fields']['Regions'])) {
                $regions = $record['fields']['Regions'];
                if (is_array($regions) && !empty($regions)) {
                    $regionAirtableId = $regions[0];
                } elseif (is_string($regions)) {
                    $regionAirtableId = $regions;
                }
            }
            
            // СТРОГО: Найдем регион по Airtable record ID
            $regionId = null;
            if ($regionAirtableId && preg_match('/^rec[A-Za-z0-9]{14}$/', $regionAirtableId)) {
                $regions = $db->getRegions();
                foreach ($regions as $region) {
                    if ($region['id'] === $regionAirtableId) {
                        $regionId = $region['id'];
                        break;
                    }
                }
            }
            
            // СТРОГО: Найдем город по Airtable record ID из поля City Location
            $cityId = null;
            if (isset($record['fields']['City Location']) && is_array($record['fields']['City Location'])) {
                $cityAirtableId = $record['fields']['City Location'][0];
                if (preg_match('/^rec[A-Za-z0-9]{14}$/', $cityAirtableId)) {
                    // Ищем город по Airtable ID
                    $cities = $db->getAllCities();
                    foreach ($cities as $city) {
                        if ($city['id'] === $cityAirtableId) {
                            $cityId = $city['id'];
                            break;
                        }
                    }
                }
            }
            
            // Debug: log POI data
            error_log("POI: " . ($record['fields']['POI Name (RU)'] ?? 'Unknown') . 
                     " | Region Airtable ID: " . ($regionAirtableId ?? 'NULL') . 
                     " | Region ID: " . ($regionId ?? 'NULL') . 
                     " | City Airtable ID: " . ($record['fields']['City Location'][0] ?? 'NULL') . 
                     " | City ID: " . ($cityId ?? 'NULL'));
            
            // Обрабатываем категории правильно
            $categoriesRu = $record['fields']['POI Category (RU)'] ?? [];
            $categoriesEn = $record['fields']['POI Category (EN)'] ?? [];
            $category = is_array($categoriesRu) && count($categoriesRu) > 0 ? $categoriesRu[0] : null;
            
            $data = [
                'id' => $record['id'],
                'name_ru' => $record['fields']['POI Name (RU)'] ?? 'Unknown',
                'name_en' => $record['fields']['POI Name (EN)'] ?? null,
                'category' => $category,
                'categories_ru' => is_array($categoriesRu) ? $categoriesRu : [],
                'categories_en' => is_array($categoriesEn) ? $categoriesEn : [],
                'place_id' => $record['fields']['Place ID'] ?? null,
                'published' => $record['fields']['Published'] ?? false,
                'business_id' => $record['fields']['POI ID'] ?? null,
                'city_id' => $cityId,
                'region_id' => $regionId,
                'description' => $record['fields']['Description (RU)'] ?? $record['fields']['Description'] ?? null,
                'description_ru' => $record['fields']['Description (RU)'] ?? null,
                'description_en' => $record['fields']['Description (EN)'] ?? null,
                'prefecture_ru' => $record['fields']['Prefecture (RU)'] ?? null,
                'prefecture_en' => $record['fields']['Prefecture (EN)'] ?? null,
                'website' => $record['fields']['Website'] ?? null,
                'working_hours' => $record['fields']['Working Hours'] ?? null,
                'notes' => $record['fields']['Notes'] ?? null,
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
