<?php
// Data API for admin panel
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once 'database.php';
require_once 'filter-constants.php';
require_once 'airtable-data-source.php';

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$db = new Database();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

try {
    // Проверяем доступность Airtable
    $airtable = new AirtableDataSource();
    
    switch ($action) {
        case 'regions':
            if ($method === 'GET') {
                // Получаем регионы напрямую из Airtable
                $airtableRegions = $airtable->getRegionsFromAirtable();
                $processedRegions = [];
                
                foreach ($airtableRegions as $record) {
                    $fields = $record['fields'] ?? [];
                    $businessId = $fields['REGION ID'] ?? null;
                    
                    if (!$businessId || !preg_match('/^REG-\d+$/', $businessId)) {
                        continue; // Пропускаем записи без корректного business_id
                    }
                    
                    $processedRegions[] = [
                        'id' => $record['id'],
                        'business_id' => $businessId,
                        'name_ru' => $fields['Name (RU)'] ?? 'Неизвестно',
                        'name_en' => $fields['Name (EN)'] ?? 'Unknown'
                    ];
                }
                
                respond(true, ['items' => $processedRegions]);
            }
            break;
            
        case 'cities':
            if ($method === 'GET') {
                $regionId = $_GET['region_id'] ?? '';
                if (!$regionId) {
                    respond(false, ['error' => 'Region ID required'], 400);
                }
                
                // ЖЕСТКАЯ ВАЛИДАЦИЯ: проверяем формат region_id
                if (!validateBusinessId($regionId, 'region')) {
                    respond(false, ['error' => 'Invalid region ID format. Expected: REG-XXXX'], 400);
                }
                
                // Получаем города напрямую из Airtable
                $airtableCities = $airtable->getCitiesFromAirtable();
                $processedCities = [];
                
                foreach ($airtableCities as $record) {
                    $fields = $record['fields'] ?? [];
                    $businessId = $fields['CITY ID'] ?? null;
                    $regionField = $fields['REGION ID'] ?? null;
                    
                    // Проверяем, что это город нужного региона
                    if (!$businessId || !preg_match('/^(CTY|LOC)-\d+$/', $businessId)) {
                        continue;
                    }
                    
                    if ($regionField !== $regionId) {
                        continue; // Пропускаем города других регионов
                    }
                    
                    $processedCities[] = [
                        'id' => $record['id'],
                        'business_id' => $businessId,
                        'name_ru' => $fields['Name (RU)'] ?? 'Неизвестно',
                        'name_en' => $fields['Name (EN)'] ?? 'Unknown',
                        'type' => strpos($businessId, 'LOC-') === 0 ? 'location' : 'city',
                        'region_id' => $regionField
                    ];
                }
                
                respond(true, ['items' => $processedCities]);
            }
            break;
            
        case 'pois':
            if ($method === 'GET') {
                $cityId = $_GET['city_id'] ?? '';
                if (!$cityId) {
                    respond(false, ['error' => 'City ID required'], 400);
                }
                
                // ЖЕСТКАЯ ВАЛИДАЦИЯ: только business_id
                if (!validateBusinessId($cityId, 'city')) {
                    respond(false, ['error' => 'Invalid city ID format. Expected: CTY-XXXX or LOC-XXXX'], 400);
                }
                
                // Получаем POI напрямую из Airtable
                $airtablePois = $airtable->getPoisFromAirtable();
                $processedPois = [];
                
                foreach ($airtablePois as $record) {
                    $fields = $record['fields'] ?? [];
                    $businessId = $fields['POI ID'] ?? null;
                    $cityField = $fields['City Location'] ?? null;
                    
                    // Проверяем, что это POI нужного города
                    if (!$businessId || !preg_match('/^POI-\d+$/', $businessId)) {
                        continue;
                    }
                    
                    // Проверяем связь с городом (может быть массивом или строкой)
                    $isLinkedToCity = false;
                    if (is_array($cityField)) {
                        // Если это массив Airtable ID, нужно найти соответствующий city
                        $airtableCities = $airtable->getCitiesFromAirtable();
                        foreach ($airtableCities as $cityRecord) {
                            if (in_array($cityRecord['id'], $cityField)) {
                                $cityBusinessId = $cityRecord['fields']['CITY ID'] ?? null;
                                if ($cityBusinessId === $cityId) {
                                    $isLinkedToCity = true;
                                    break;
                                }
                            }
                        }
                    } else {
                        // Если это строка, проверяем напрямую
                        $isLinkedToCity = ($cityField === $cityId);
                    }
                    
                    if (!$isLinkedToCity) {
                        continue; // Пропускаем POI других городов
                    }
                    
                    $processedPois[] = [
                        'id' => $record['id'],
                        'business_id' => $businessId,
                        'name_ru' => $fields['POI Name (RU)'] ?? 'Неизвестно',
                        'name_en' => $fields['POI Name (EN)'] ?? 'Unknown',
                        'category' => $fields['Category'] ?? null,
                        'city_id' => $cityField,
                        'region_id' => $fields['Region ID'] ?? null
                    ];
                }
                
                respond(true, ['items' => $processedPois]);
            }
            break;
            
        case 'stats':
            if ($method === 'GET') {
                $stats = $db->getStats();
                $stats['cities_by_region'] = $db->getCityCountsByRegion();
                respond(true, ['stats' => $stats]);
            }
            break;
            
        case 'city-stats':
            if ($method === 'GET') {
                $regionId = $_GET['region_id'] ?? '';
                if (!$regionId) {
                    respond(false, ['error' => 'Region ID required'], 400);
                }
                
                // Валидируем business_id
                if (!validateBusinessId($regionId, 'region')) {
                    respond(false, ['error' => 'Invalid region ID format'], 400);
                }
                
                // Получаем города и POI напрямую из Airtable для подсчета
                $airtableCities = $airtable->getCitiesFromAirtable();
                $airtablePois = $airtable->getPoisFromAirtable();
                
                $stats = [];
                
                // Собираем города нужного региона
                foreach ($airtableCities as $cityRecord) {
                    $cityFields = $cityRecord['fields'] ?? [];
                    $cityBusinessId = $cityFields['CITY ID'] ?? null;
                    $regionField = $cityFields['REGION ID'] ?? null;
                    
                    if ($regionField === $regionId && $cityBusinessId) {
                        $poiCount = 0;
                        
                        // Считаем POI для этого города
                        foreach ($airtablePois as $poiRecord) {
                            $poiFields = $poiRecord['fields'] ?? [];
                            $cityField = $poiFields['City Location'] ?? null;
                            
                            // Проверяем связь POI с городом
                            $isLinkedToCity = false;
                            if (is_array($cityField)) {
                                $isLinkedToCity = in_array($cityRecord['id'], $cityField);
                            } else {
                                $isLinkedToCity = ($cityField === $cityRecord['id']);
                            }
                            
                            if ($isLinkedToCity) {
                                $poiCount++;
                            }
                        }
                        
                        $stats[$cityRecord['id']] = $poiCount;
                    }
                }
                
                respond(true, ['stats' => $stats]);
            }
            break;
            
        case 'sync':
            if ($method === 'POST') {
                // This will be handled by sync script
                respond(true, ['message' => 'Sync initiated']);
            }
            break;
            
        default:
            respond(false, ['error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    respond(false, ['error' => $e->getMessage()], 500);
}
?>
