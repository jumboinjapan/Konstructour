<?php
// Двусторонняя синхронизация между локальной БД и Airtable
require_once 'database.php';
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fetchAirtableData($baseId, $tableId, $pat, $maxRecords = 100) {
    $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}?maxRecords={$maxRecords}";
    
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
    return $data['records'] ?? [];
}

function updateAirtableRecord($baseId, $tableId, $pat, $recordId, $fields) {
    $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}/{$recordId}";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $pat,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode(['fields' => $fields]),
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
    
    return json_decode($response, true);
}

function createAirtableRecord($baseId, $tableId, $pat, $fields) {
    $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $pat,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode(['records' => [['fields' => $fields]]]),
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
    return $data['records'][0] ?? null;
}

function deleteAirtableRecord($baseId, $tableId, $pat, $recordId) {
    $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}/{$recordId}";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
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
    
    return true;
}

try {
    $db = new Database();
    $config = include 'config.php';
    
    // Airtable settings
    $baseId = 'apppwhjFN82N9zNqm';
    $pat = getenv('AIRTABLE_PAT') ?: 'PLACEHOLDER_FOR_REAL_API_KEY';
    
    if ($pat === 'PLACEHOLDER_FOR_REAL_API_KEY') {
        respond(false, ['error' => 'Airtable token not configured'], 400);
    }
    
    $action = $_GET['action'] ?? 'full';
    $results = [
        'airtable_to_local' => 0,
        'local_to_airtable' => 0,
        'updates' => 0,
        'deletes' => 0,
        'errors' => []
    ];
    
    if ($action === 'full' || $action === 'airtable_to_local') {
        // Синхронизация из Airtable в локальную БД
        try {
            // Регионы
            $airtableRegions = fetchAirtableData($baseId, 'tblbSajWkzI8X7M4U', $pat);
            foreach ($airtableRegions as $record) {
                $data = [
                    'id' => $record['id'],
                    'name_ru' => $record['fields']['Name (RU)'] ?? $record['fields']['Название (RU)'] ?? 'Unknown',
                    'name_en' => $record['fields']['Name (EN)'] ?? $record['fields']['Название (EN)'] ?? null,
                    'business_id' => $record['fields']['ID'] ?? null
                ];
                $db->saveRegion($data);
                $results['airtable_to_local']++;
            }
            
            // Города
            $airtableCities = fetchAirtableData($baseId, 'tblHaHc9NV0mA8bSa', $pat);
            foreach ($airtableCities as $record) {
                $regionId = $record['fields']['Region'] ?? $record['fields']['Регион'] ?? null;
                if (is_array($regionId)) {
                    $regionId = $regionId[0] ?? null;
                }
                
                $data = [
                    'id' => $record['id'],
                    'name_ru' => $record['fields']['Name (RU)'] ?? $record['fields']['Название (RU)'] ?? 'Unknown',
                    'name_en' => $record['fields']['Name (EN)'] ?? $record['fields']['Название (EN)'] ?? null,
                    'business_id' => $record['fields']['A ID'] ?? $record['fields']['ID'] ?? null,
                    'type' => $record['fields']['Type'] ?? 'city',
                    'region_id' => $regionId
                ];
                $db->saveCity($data);
                $results['airtable_to_local']++;
            }
            
            // POI
            $airtablePois = fetchAirtableData($baseId, 'tbl8X7M4U', $pat);
            foreach ($airtablePois as $record) {
                $cityId = $record['fields']['City ID'] ?? $record['fields']['A ID'] ?? null;
                if (is_array($cityId)) {
                    $cityId = $cityId[0] ?? null;
                }
                
                $data = [
                    'id' => $record['id'],
                    'name_ru' => $record['fields']['Name (RU)'] ?? $record['fields']['Название (RU)'] ?? 'Unknown',
                    'name_en' => $record['fields']['Name (EN)'] ?? $record['fields']['Название (EN)'] ?? null,
                    'category' => $record['fields']['Category'] ?? $record['fields']['Категория'] ?? '',
                    'description' => $record['fields']['Description'] ?? $record['fields']['Описание'] ?? '',
                    'business_id' => $record['fields']['POI ID'] ?? $record['fields']['ID'] ?? null,
                    'city_id' => $cityId
                ];
                $db->savePoi($data);
                $results['airtable_to_local']++;
            }
            
        } catch (Exception $e) {
            $results['errors'][] = "Airtable to Local sync error: " . $e->getMessage();
        }
    }
    
    if ($action === 'full' || $action === 'local_to_airtable') {
        // Синхронизация из локальной БД в Airtable
        try {
            // Получаем все локальные данные
            $localRegions = $db->getRegions();
            $localCities = $db->getAllCities();
            $localPois = $db->getAllPois();
            
            // Создаем маппинг для быстрого поиска
            $airtableRegions = fetchAirtableData($baseId, 'tblbSajWkzI8X7M4U', $pat);
            $airtableCities = fetchAirtableData($baseId, 'tblHaHc9NV0mA8bSa', $pat);
            $airtablePois = fetchAirtableData($baseId, 'tbl8X7M4U', $pat);
            
            $airtableRegionIds = array_column($airtableRegions, 'id');
            $airtableCityIds = array_column($airtableCities, 'id');
            $airtablePoiIds = array_column($airtablePois, 'id');
            
            // Синхронизируем регионы
            foreach ($localRegions as $region) {
                if (in_array($region['id'], $airtableRegionIds)) {
                    // Обновляем существующую запись
                    $fields = [
                        'Name (RU)' => $region['name_ru'],
                        'ID' => $region['business_id']
                    ];
                    if ($region['name_en']) {
                        $fields['Name (EN)'] = $region['name_en'];
                    }
                    
                    updateAirtableRecord($baseId, 'tblbSajWkzI8X7M4U', $pat, $region['id'], $fields);
                    $results['updates']++;
                } else {
                    // Создаем новую запись
                    $fields = [
                        'Name (RU)' => $region['name_ru'],
                        'ID' => $region['business_id']
                    ];
                    if ($region['name_en']) {
                        $fields['Name (EN)'] = $region['name_en'];
                    }
                    
                    createAirtableRecord($baseId, 'tblbSajWkzI8X7M4U', $pat, $fields);
                    $results['local_to_airtable']++;
                }
            }
            
            // Синхронизируем города
            foreach ($localCities as $city) {
                if (in_array($city['id'], $airtableCityIds)) {
                    // Обновляем существующую запись
                    $fields = [
                        'Name (RU)' => $city['name_ru'],
                        'A ID' => $city['business_id'],
                        'Type' => $city['type']
                    ];
                    if ($city['name_en']) {
                        $fields['Name (EN)'] = $city['name_en'];
                    }
                    if ($city['region_id']) {
                        $fields['Region'] = [$city['region_id']];
                    }
                    
                    updateAirtableRecord($baseId, 'tblHaHc9NV0mA8bSa', $pat, $city['id'], $fields);
                    $results['updates']++;
                } else {
                    // Создаем новую запись
                    $fields = [
                        'Name (RU)' => $city['name_ru'],
                        'A ID' => $city['business_id'],
                        'Type' => $city['type']
                    ];
                    if ($city['name_en']) {
                        $fields['Name (EN)'] = $city['name_en'];
                    }
                    if ($city['region_id']) {
                        $fields['Region'] = [$city['region_id']];
                    }
                    
                    createAirtableRecord($baseId, 'tblHaHc9NV0mA8bSa', $pat, $fields);
                    $results['local_to_airtable']++;
                }
            }
            
            // Синхронизируем POI
            foreach ($localPois as $poi) {
                if (in_array($poi['id'], $airtablePoiIds)) {
                    // Обновляем существующую запись
                    $fields = [
                        'Name (RU)' => $poi['name_ru'],
                        'POI ID' => $poi['business_id'],
                        'Category' => $poi['category']
                    ];
                    if ($poi['name_en']) {
                        $fields['Name (EN)'] = $poi['name_en'];
                    }
                    if ($poi['description']) {
                        $fields['Description'] = $poi['description'];
                    }
                    if ($poi['city_id']) {
                        $fields['City ID'] = [$poi['city_id']];
                    }
                    
                    updateAirtableRecord($baseId, 'tbl8X7M4U', $pat, $poi['id'], $fields);
                    $results['updates']++;
                } else {
                    // Создаем новую запись
                    $fields = [
                        'Name (RU)' => $poi['name_ru'],
                        'POI ID' => $poi['business_id'],
                        'Category' => $poi['category']
                    ];
                    if ($poi['name_en']) {
                        $fields['Name (EN)'] = $poi['name_en'];
                    }
                    if ($poi['description']) {
                        $fields['Description'] = $poi['description'];
                    }
                    if ($poi['city_id']) {
                        $fields['City ID'] = [$poi['city_id']];
                    }
                    
                    createAirtableRecord($baseId, 'tbl8X7M4U', $pat, $fields);
                    $results['local_to_airtable']++;
                }
            }
            
        } catch (Exception $e) {
            $results['errors'][] = "Local to Airtable sync error: " . $e->getMessage();
        }
    }
    
    respond(true, $results);
    
} catch (Exception $e) {
    respond(false, ['error' => $e->getMessage()], 500);
}
?>
