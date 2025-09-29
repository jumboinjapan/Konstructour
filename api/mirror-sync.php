<?php
// Полное зеркалирование Airtable в локальную базу данных
require_once 'database.php';
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

function respond($success, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode([
        'ok' => $success,
        'message' => $data['message'] ?? ($success ? 'Success' : 'Error'),
        'results' => $data['results'] ?? $data,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function fetchAirtableData($baseId, $tableId, $pat, $offset = null) {
    $url = "https://api.airtable.com/v0/$baseId/$tableId";
    if ($offset) {
        $url .= "?offset=$offset";
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $pat,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Airtable API error: HTTP $httpCode - $response");
    }
    
    $data = json_decode($response, true);
    if (!$data) {
        throw new Exception("Invalid JSON response from Airtable");
    }
    
    return $data;
}

function getAllAirtableRecords($baseId, $tableId, $pat) {
    $allRecords = [];
    $offset = null;
    
    do {
        $data = fetchAirtableData($baseId, $tableId, $pat, $offset);
        $allRecords = array_merge($allRecords, $data['records']);
        $offset = $data['offset'] ?? null;
    } while ($offset);
    
    return $allRecords;
}

function extractLinkedRecordId($fields, $possibleFields) {
    foreach ($possibleFields as $field) {
        if (isset($fields[$field]) && is_array($fields[$field]) && !empty($fields[$field])) {
            return $fields[$field][0];
        }
    }
    return null;
}

function syncRegions($db, $baseId, $pat) {
    $results = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'errors' => []];
    
    try {
        // Получаем все записи из Airtable
        $airtableRecords = getAllAirtableRecords($baseId, 'tblbSajWkzI8X7M4U', $pat);
        
        // Получаем все записи из локальной БД
        $localRecords = $db->getConnection()->query("SELECT * FROM regions")->fetchAll(PDO::FETCH_ASSOC);
        $localById = [];
        foreach ($localRecords as $record) {
            $localById[$record['id']] = $record;
        }
        
        // Создаем/обновляем записи из Airtable
        foreach ($airtableRecords as $record) {
            $airtableId = $record['id'];
            $fields = $record['fields'];
            
            $data = [
                'id' => $airtableId,
                'name_ru' => $fields['Name (RU)'] ?? $fields['Название (RU)'] ?? 'Unknown',
                'name_en' => $fields['Name (EN)'] ?? $fields['Название (EN)'] ?? null,
                'business_id' => $fields['ID'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (isset($localById[$airtableId])) {
                // Обновляем существующую запись
                $stmt = $db->getConnection()->prepare("
                    UPDATE regions 
                    SET name_ru = ?, name_en = ?, business_id = ?, updated_at = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $data['name_ru'],
                    $data['name_en'],
                    $data['business_id'],
                    $data['updated_at'],
                    $airtableId
                ]);
                $results['updated']++;
            } else {
                // Создаем новую запись
                $stmt = $db->getConnection()->prepare("
                    INSERT INTO regions (id, name_ru, name_en, business_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $airtableId,
                    $data['name_ru'],
                    $data['name_en'],
                    $data['business_id'],
                    date('Y-m-d H:i:s'),
                    $data['updated_at']
                ]);
                $results['created']++;
            }
            
            // Удаляем из списка локальных записей (останутся только те, что нужно удалить)
            unset($localById[$airtableId]);
        }
        
        // Удаляем записи, которых нет в Airtable
        foreach ($localById as $record) {
            $stmt = $db->getConnection()->prepare("DELETE FROM regions WHERE id = ?");
            $stmt->execute([$record['id']]);
            $results['deleted']++;
        }
        
    } catch (Exception $e) {
        $results['errors'][] = "Regions sync error: " . $e->getMessage();
    }
    
    return $results;
}

function syncCities($db, $baseId, $pat) {
    $results = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'errors' => []];
    
    try {
        // Получаем все записи из Airtable
        $airtableRecords = getAllAirtableRecords($baseId, 'tblHaHc9NV0mA8bSa', $pat);
        
        // Получаем все записи из локальной БД
        $localRecords = $db->getConnection()->query("SELECT * FROM cities")->fetchAll(PDO::FETCH_ASSOC);
        $localById = [];
        foreach ($localRecords as $record) {
            $localById[$record['id']] = $record;
        }
        
        // Создаем/обновляем записи из Airtable
        foreach ($airtableRecords as $record) {
            $airtableId = $record['id'];
            $fields = $record['fields'];
            
            // Находим связанный регион
            $regionId = extractLinkedRecordId($fields, ['Region', 'Регион', 'Regions', 'Регионы']);
            
            $data = [
                'id' => $airtableId,
                'name_ru' => $fields['Name (RU)'] ?? $fields['Название (RU)'] ?? 'Unknown',
                'name_en' => $fields['Name (EN)'] ?? $fields['Название (EN)'] ?? null,
                'business_id' => $fields['ID'] ?? null,
                'type' => $fields['Type'] ?? 'city',
                'region_id' => $regionId,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (isset($localById[$airtableId])) {
                // Обновляем существующую запись
                $stmt = $db->getConnection()->prepare("
                    UPDATE cities 
                    SET name_ru = ?, name_en = ?, business_id = ?, type = ?, region_id = ?, updated_at = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $data['name_ru'],
                    $data['name_en'],
                    $data['business_id'],
                    $data['type'],
                    $data['region_id'],
                    $data['updated_at'],
                    $airtableId
                ]);
                $results['updated']++;
            } else {
                // Создаем новую запись
                $stmt = $db->getConnection()->prepare("
                    INSERT INTO cities (id, name_ru, name_en, business_id, type, region_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $airtableId,
                    $data['name_ru'],
                    $data['name_en'],
                    $data['business_id'],
                    $data['type'],
                    $data['region_id'],
                    date('Y-m-d H:i:s'),
                    $data['updated_at']
                ]);
                $results['created']++;
            }
            
            // Удаляем из списка локальных записей
            unset($localById[$airtableId]);
        }
        
        // Удаляем записи, которых нет в Airtable
        foreach ($localById as $record) {
            $stmt = $db->getConnection()->prepare("DELETE FROM cities WHERE id = ?");
            $stmt->execute([$record['id']]);
            $results['deleted']++;
        }
        
    } catch (Exception $e) {
        $results['errors'][] = "Cities sync error: " . $e->getMessage();
    }
    
    return $results;
}

function syncPOIs($db, $baseId, $pat) {
    $results = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'errors' => []];
    
    try {
        // Получаем все записи из Airtable
        $airtableRecords = getAllAirtableRecords($baseId, 'tbl8X7M4U', $pat);
        
        // Получаем все записи из локальной БД
        $localRecords = $db->getConnection()->query("SELECT * FROM pois")->fetchAll(PDO::FETCH_ASSOC);
        $localById = [];
        foreach ($localRecords as $record) {
            $localById[$record['id']] = $record;
        }
        
        // Создаем/обновляем записи из Airtable
        foreach ($airtableRecords as $record) {
            $airtableId = $record['id'];
            $fields = $record['fields'];
            
            // Находим связанный город
            $cityId = extractLinkedRecordId($fields, ['City', 'Город', 'Cities', 'Города']);
            
            $data = [
                'id' => $airtableId,
                'name_ru' => $fields['Name (RU)'] ?? $fields['Название (RU)'] ?? 'Unknown',
                'name_en' => $fields['Name (EN)'] ?? $fields['Название (EN)'] ?? null,
                'business_id' => $fields['ID'] ?? null,
                'category' => $fields['Category'] ?? $fields['Категория'] ?? null,
                'place_id' => $fields['Place ID'] ?? null,
                'published' => isset($fields['Published']) ? (bool)$fields['Published'] : false,
                'city_id' => $cityId,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (isset($localById[$airtableId])) {
                // Обновляем существующую запись
                $stmt = $db->getConnection()->prepare("
                    UPDATE pois 
                    SET name_ru = ?, name_en = ?, business_id = ?, category = ?, place_id = ?, published = ?, city_id = ?, updated_at = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $data['name_ru'],
                    $data['name_en'],
                    $data['business_id'],
                    $data['category'],
                    $data['place_id'],
                    $data['published'] ? 1 : 0,
                    $data['city_id'],
                    $data['updated_at'],
                    $airtableId
                ]);
                $results['updated']++;
            } else {
                // Создаем новую запись
                $stmt = $db->getConnection()->prepare("
                    INSERT INTO pois (id, name_ru, name_en, business_id, category, place_id, published, city_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $airtableId,
                    $data['name_ru'],
                    $data['name_en'],
                    $data['business_id'],
                    $data['category'],
                    $data['place_id'],
                    $data['published'] ? 1 : 0,
                    $data['city_id'],
                    date('Y-m-d H:i:s'),
                    $data['updated_at']
                ]);
                $results['created']++;
            }
            
            // Удаляем из списка локальных записей
            unset($localById[$airtableId]);
        }
        
        // Удаляем записи, которых нет в Airtable
        foreach ($localById as $record) {
            $stmt = $db->getConnection()->prepare("DELETE FROM pois WHERE id = ?");
            $stmt->execute([$record['id']]);
            $results['deleted']++;
        }
        
    } catch (Exception $e) {
        $results['errors'][] = "POIs sync error: " . $e->getMessage();
    }
    
    return $results;
}

// Основная логика
try {
    $db = new Database();
    $config = include 'config.php';
    
    // Получаем токен Airtable
    $pat = getenv('AIRTABLE_PAT') ?: 'PLACEHOLDER_FOR_REAL_API_KEY';
    
    if ($pat === 'PLACEHOLDER_FOR_REAL_API_KEY') {
        respond(false, ['error' => 'Airtable token not configured'], 400);
    }
    
    $baseId = 'apppwhjFN82N9zNqm';
    
    // Синхронизируем все таблицы
    $regionsResults = syncRegions($db, $baseId, $pat);
    $citiesResults = syncCities($db, $baseId, $pat);
    $poisResults = syncPOIs($db, $baseId, $pat);
    
    // Собираем общие результаты
    $totalResults = [
        'regions' => $regionsResults,
        'cities' => $citiesResults,
        'pois' => $poisResults,
        'summary' => [
            'total_created' => $regionsResults['created'] + $citiesResults['created'] + $poisResults['created'],
            'total_updated' => $regionsResults['updated'] + $citiesResults['updated'] + $poisResults['updated'],
            'total_deleted' => $regionsResults['deleted'] + $citiesResults['deleted'] + $poisResults['deleted'],
            'total_errors' => count($regionsResults['errors']) + count($citiesResults['errors']) + count($poisResults['errors'])
        ]
    ];
    
    // Обновляем время последней синхронизации
    $db->getConnection()->exec("
        CREATE TABLE IF NOT EXISTS sync_status (
            id INTEGER PRIMARY KEY,
            last_sync DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->getConnection()->exec("
        INSERT OR REPLACE INTO sync_status (id, last_sync) VALUES (1, CURRENT_TIMESTAMP)
    ");
    
    respond(true, [
        'message' => 'Mirror sync completed successfully',
        'results' => $totalResults
    ]);
    
} catch (Exception $e) {
    respond(false, ['error' => $e->getMessage()], 500);
}
?>
