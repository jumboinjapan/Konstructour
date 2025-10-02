<?php
// Data API for admin panel
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once 'database.php';
require_once 'filter-constants.php';

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$db = new Database();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'regions':
            if ($method === 'GET') {
                $regions = $db->getValidRegions();
                respond(true, ['items' => $regions]);
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
                
                // Найдем Airtable ID региона по business_id
                $regions = $db->getRegions();
                $regionAirtableId = null;
                foreach ($regions as $region) {
                    if ($region['business_id'] === $regionId) {
                        $regionAirtableId = $region['id'];
                        break;
                    }
                }
                
                if (!$regionAirtableId) {
                    respond(false, ['error' => 'Region not found'], 404);
                }
                
                $cities = $db->getValidCitiesByRegion($regionAirtableId);
                respond(true, ['items' => $cities]);
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
                
                // Найдем Airtable ID города по business_id
                $cities = $db->getAllCities();
                $cityAirtableId = null;
                foreach ($cities as $city) {
                    if ($city['business_id'] === $cityId) {
                        $cityAirtableId = $city['id'];
                        break;
                    }
                }
                
                if (!$cityAirtableId) {
                    respond(false, ['error' => 'City not found'], 404);
                }
                
                $pois = $db->getValidPoisByCity($cityAirtableId);
                respond(true, ['items' => $pois]);
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
                
                // Найдем Airtable ID региона по business_id
                $regions = $db->getRegions();
                $regionAirtableId = null;
                foreach ($regions as $region) {
                    if ($region['business_id'] === $regionId) {
                        $regionAirtableId = $region['id'];
                        break;
                    }
                }
                
                if (!$regionAirtableId) {
                    respond(false, ['error' => 'Region not found'], 404);
                }
                
                $stats = $db->getPoiCountsByCity($regionAirtableId);
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
