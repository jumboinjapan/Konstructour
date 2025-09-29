<?php
require_once 'database.php';

function respond($success, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => $success,
        'message' => $data['message'] ?? ($success ? 'Success' : 'Error'),
        'results' => $data['results'] ?? $data,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function makeAirtableRequest($url, $method = 'GET', $data = null, $pat) {
    $ch = curl_init();
    
    $headers = [
        'Authorization: Bearer ' . $pat,
        'Content-Type: application/json'
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception("CURL Error: $curlError");
    }
    
    if ($httpCode >= 400) {
        throw new Exception("HTTP Error: $httpCode - $response");
    }
    
    return json_decode($response, true);
}

function filterAutomaticFields($fields) {
    // Удаляем автоматические поля Airtable, которые не должны синхронизироваться
    $automaticFields = [
        'Name (RU) (from Regions)',
        'Name (EN) (from Regions)',
        'Name (RU) (from Cities)',
        'Name (EN) (from Cities)',
        'Name (RU) (from POIs)',
        'Name (EN) (from POIs)'
    ];
    
    return array_diff_key($fields, array_flip($automaticFields));
}

function syncFromAirtable($db, $pat, $baseId, $tables) {
    $results = ['airtable_to_local' => 0, 'updates' => 0, 'errors' => []];
    
    try {
        // Синхронизация регионов
        $regionsUrl = "https://api.airtable.com/v0/$baseId/" . $tables['regions'];
        $regionsData = makeAirtableRequest($regionsUrl, 'GET', null, $pat);
        
        foreach ($regionsData['records'] as $record) {
            try {
                $fields = $record['fields'];
                $airtableId = $record['id'];
                
                $regionData = [
                    'name_ru' => $fields['Name (RU)'] ?? '',
                    'name_en' => $fields['Name (EN)'] ?? '',
                    'business_id' => $fields['ID'] ?? '',
                    'airtable_id' => $airtableId,
                    'airtable_updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Ищем существующий регион по Business ID
                $existing = $db->getConnection()->prepare("SELECT * FROM regions WHERE business_id = ?");
                $existing->execute([$regionData['business_id']]);
                $existingRegion = $existing->fetch(PDO::FETCH_ASSOC);
                
                if ($existingRegion) {
                    // Обновляем существующий регион
                    $update = $db->getConnection()->prepare("
                        UPDATE regions 
                        SET name_ru = ?, name_en = ?, airtable_id = ?, airtable_updated_at = ?
                        WHERE business_id = ?
                    ");
                    $update->execute([
                        $regionData['name_ru'],
                        $regionData['name_en'],
                        $regionData['airtable_id'],
                        $regionData['airtable_updated_at'],
                        $regionData['business_id']
                    ]);
                    $results['updates']++;
                } else {
                    // Создаем новый регион
                    $insert = $db->getConnection()->prepare("
                        INSERT INTO regions (name_ru, name_en, business_id, airtable_id, airtable_updated_at, local_updated_at)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $insert->execute([
                        $regionData['name_ru'],
                        $regionData['name_en'],
                        $regionData['business_id'],
                        $airtableId,
                        $regionData['airtable_updated_at'],
                        date('Y-m-d H:i:s')
                    ]);
                    $results['airtable_to_local']++;
                }
            } catch (Exception $e) {
                $results['errors'][] = "Region sync error: " . $e->getMessage();
            }
        }
        
        // Синхронизация городов
        $citiesUrl = "https://api.airtable.com/v0/$baseId/" . $tables['cities'];
        $citiesData = makeAirtableRequest($citiesUrl, 'GET', null, $pat);
        
        foreach ($citiesData['records'] as $record) {
            try {
                $fields = $record['fields'];
                $airtableId = $record['id'];
                
                // Получаем ID региона по Airtable ID
                $regionId = null;
                if (isset($fields['Regions']) && is_array($fields['Regions'])) {
                    $regionAirtableId = $fields['Regions'][0];
                    $regionQuery = $db->getConnection()->prepare("SELECT id FROM regions WHERE airtable_id = ?");
                    $regionQuery->execute([$regionAirtableId]);
                    $region = $regionQuery->fetch(PDO::FETCH_ASSOC);
                    if ($region) {
                        $regionId = $region['id'];
                    }
                }
                
                if (!$regionId) {
                    $results['errors'][] = "City sync error: Region not found for city " . ($fields['Name (RU)'] ?? 'Unknown');
                    continue;
                }
                
                $cityData = [
                    'name_ru' => $fields['Name (RU)'] ?? '',
                    'name_en' => $fields['Name (EN)'] ?? '',
                    'business_id' => $fields['ID'] ?? '',
                    'region_id' => $regionId,
                    'airtable_id' => $airtableId,
                    'airtable_updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Ищем существующий город по Business ID
                $existing = $db->getConnection()->prepare("SELECT * FROM cities WHERE business_id = ?");
                $existing->execute([$cityData['business_id']]);
                $existingCity = $existing->fetch(PDO::FETCH_ASSOC);
                
                if ($existingCity) {
                    // Обновляем существующий город
                    $update = $db->getConnection()->prepare("
                        UPDATE cities 
                        SET name_ru = ?, name_en = ?, region_id = ?, airtable_id = ?, airtable_updated_at = ?
                        WHERE business_id = ?
                    ");
                    $update->execute([
                        $cityData['name_ru'],
                        $cityData['name_en'],
                        $cityData['region_id'],
                        $cityData['airtable_id'],
                        $cityData['airtable_updated_at'],
                        $cityData['business_id']
                    ]);
                    $results['updates']++;
                } else {
                    // Создаем новый город
                    $insert = $db->getConnection()->prepare("
                        INSERT INTO cities (name_ru, name_en, business_id, region_id, airtable_id, airtable_updated_at, local_updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insert->execute([
                        $cityData['name_ru'],
                        $cityData['name_en'],
                        $cityData['business_id'],
                        $cityData['region_id'],
                        $airtableId,
                        $cityData['airtable_updated_at'],
                        date('Y-m-d H:i:s')
                    ]);
                    $results['airtable_to_local']++;
                }
            } catch (Exception $e) {
                $results['errors'][] = "Cities sync error: " . $e->getMessage();
            }
        }
        
        // Синхронизация POI
        $poisUrl = "https://api.airtable.com/v0/$baseId/" . $tables['pois'];
        $poisData = makeAirtableRequest($poisUrl, 'GET', null, $pat);
        
        foreach ($poisData['records'] as $record) {
            try {
                $fields = $record['fields'];
                $airtableId = $record['id'];
                
                // Получаем ID города по Airtable ID
                $cityId = null;
                if (isset($fields['Cities']) && is_array($fields['Cities'])) {
                    $cityAirtableId = $fields['Cities'][0];
                    $cityQuery = $db->getConnection()->prepare("SELECT id FROM cities WHERE airtable_id = ?");
                    $cityQuery->execute([$cityAirtableId]);
                    $city = $cityQuery->fetch(PDO::FETCH_ASSOC);
                    if ($city) {
                        $cityId = $city['id'];
                    }
                }
                
                if (!$cityId) {
                    $results['errors'][] = "POI sync error: City not found for POI " . ($fields['Name (RU)'] ?? 'Unknown');
                    continue;
                }
                
                $poiData = [
                    'name_ru' => $fields['Name (RU)'] ?? '',
                    'name_en' => $fields['Name (EN)'] ?? '',
                    'business_id' => $fields['ID'] ?? '',
                    'city_id' => $cityId,
                    'airtable_id' => $airtableId,
                    'airtable_updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Ищем существующий POI по Business ID
                $existing = $db->getConnection()->prepare("SELECT * FROM pois WHERE business_id = ?");
                $existing->execute([$poiData['business_id']]);
                $existingPoi = $existing->fetch(PDO::FETCH_ASSOC);
                
                if ($existingPoi) {
                    // Обновляем существующий POI
                    $update = $db->getConnection()->prepare("
                        UPDATE pois 
                        SET name_ru = ?, name_en = ?, city_id = ?, airtable_id = ?, airtable_updated_at = ?
                        WHERE business_id = ?
                    ");
                    $update->execute([
                        $poiData['name_ru'],
                        $poiData['name_en'],
                        $poiData['city_id'],
                        $poiData['airtable_id'],
                        $poiData['airtable_updated_at'],
                        $poiData['business_id']
                    ]);
                    $results['updates']++;
                } else {
                    // Создаем новый POI
                    $insert = $db->getConnection()->prepare("
                        INSERT INTO pois (name_ru, name_en, business_id, city_id, airtable_id, airtable_updated_at, local_updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insert->execute([
                        $poiData['name_ru'],
                        $poiData['name_en'],
                        $poiData['business_id'],
                        $poiData['city_id'],
                        $airtableId,
                        $poiData['airtable_updated_at'],
                        date('Y-m-d H:i:s')
                    ]);
                    $results['airtable_to_local']++;
                }
            } catch (Exception $e) {
                $results['errors'][] = "POIs sync error: " . $e->getMessage();
            }
        }
        
    } catch (Exception $e) {
        $results['errors'][] = "Airtable sync error: " . $e->getMessage();
    }
    
    return $results;
}

function syncToAirtable($db, $pat, $baseId, $tables) {
    $results = ['local_to_airtable' => 0, 'updates' => 0, 'errors' => []];
    
    try {
        // Синхронизация регионов в Airtable
        $regions = $db->getConnection()->query("SELECT * FROM regions WHERE airtable_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($regions as $region) {
            try {
                $fields = [
                    'Name (RU)' => $region['name_ru'],
                    'Name (EN)' => $region['name_en']
                ];
                
                $fields = filterAutomaticFields($fields);
                
                $url = "https://api.airtable.com/v0/$baseId/" . $tables['regions'] . "/" . $region['airtable_id'];
                makeAirtableRequest($url, 'PATCH', ['fields' => $fields], $pat);
                $results['updates']++;
            } catch (Exception $e) {
                $results['errors'][] = "Failed to update region " . $region['airtable_id'] . ": " . $e->getMessage();
            }
        }
        
        // Синхронизация городов в Airtable
        $cities = $db->getConnection()->query("SELECT * FROM cities WHERE airtable_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($cities as $city) {
            try {
                // Получаем Airtable ID региона по business_id
                $regionQuery = $db->getConnection()->prepare("SELECT airtable_id FROM regions WHERE business_id = ?");
                $regionQuery->execute([$city['region_id']]);
                $region = $regionQuery->fetch(PDO::FETCH_ASSOC);
                
                if (!$region || !$region['airtable_id']) {
                    $results['errors'][] = "Region Airtable ID not found for city " . $city['name_ru'];
                    continue;
                }
                
                $fields = [
                    'Name (RU)' => $city['name_ru'],
                    'Name (EN)' => $city['name_en'],
                    'Regions' => [$region['airtable_id']]
                ];
                
                $fields = filterAutomaticFields($fields);
                
                $url = "https://api.airtable.com/v0/$baseId/" . $tables['cities'] . "/" . $city['airtable_id'];
                makeAirtableRequest($url, 'PATCH', ['fields' => $fields], $pat);
                $results['updates']++;
            } catch (Exception $e) {
                $results['errors'][] = "Failed to update city " . $city['airtable_id'] . ": " . $e->getMessage();
            }
        }
        
        // Синхронизация POI в Airtable
        $pois = $db->getConnection()->query("SELECT * FROM pois WHERE airtable_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($pois as $poi) {
            try {
                // Получаем Airtable ID города по business_id
                $cityQuery = $db->getConnection()->prepare("SELECT airtable_id FROM cities WHERE business_id = ?");
                $cityQuery->execute([$poi['city_id']]);
                $city = $cityQuery->fetch(PDO::FETCH_ASSOC);
                
                if (!$city || !$city['airtable_id']) {
                    $results['errors'][] = "City Airtable ID not found for POI " . $poi['name_ru'];
                    continue;
                }
                
                $fields = [
                    'Name (RU)' => $poi['name_ru'],
                    'Name (EN)' => $poi['name_en'],
                    'Cities' => [$city['airtable_id']]
                ];
                
                $fields = filterAutomaticFields($fields);
                
                $url = "https://api.airtable.com/v0/$baseId/" . $tables['pois'] . "/" . $poi['airtable_id'];
                makeAirtableRequest($url, 'PATCH', ['fields' => $fields], $pat);
                $results['updates']++;
            } catch (Exception $e) {
                $results['errors'][] = "Failed to update POI " . $poi['airtable_id'] . ": " . $e->getMessage();
            }
        }
        
    } catch (Exception $e) {
        $results['errors'][] = "Local to Airtable sync error: " . $e->getMessage();
    }
    
    return $results;
}

try {
    $db = new Database();
    $config = include 'config.php';
    
    // Читаем токен из конфигурации
    $pat = $config['airtable_registry']['api_key'] ?? 'PLACEHOLDER_FOR_REAL_API_KEY';
    
    $baseId = $config['airtable_registry']['baseId'];
    $tables = $config['airtable_registry']['tables'];
    
    if ($pat === 'PLACEHOLDER_FOR_REAL_API_KEY') {
        respond(false, ['error' => 'Airtable token not configured'], 400);
    }
    
    $action = $_GET['action'] ?? 'sync';
    $results = [
        'airtable_to_local' => 0,
        'local_to_airtable' => 0,
        'updates' => 0,
        'errors' => []
    ];
    
    if ($action === 'sync') {
        // Синхронизация из Airtable в локальную БД
        $airtableResults = syncFromAirtable($db, $pat, $baseId, $tables);
        $results['airtable_to_local'] += $airtableResults['airtable_to_local'];
        $results['updates'] += $airtableResults['updates'];
        $results['errors'] = array_merge($results['errors'], $airtableResults['errors']);
        
        // Синхронизация из локальной БД в Airtable
        $localResults = syncToAirtable($db, $pat, $baseId, $tables);
        $results['local_to_airtable'] += $localResults['local_to_airtable'];
        $results['updates'] += $localResults['updates'];
        $results['errors'] = array_merge($results['errors'], $localResults['errors']);
        
        respond(true, [
            'message' => 'Sync completed successfully',
            'results' => $results
        ]);
    } else {
        respond(false, ['error' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    respond(false, ['error' => $e->getMessage()], 500);
}
?>