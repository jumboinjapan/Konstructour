<?php
// Data API for admin panel
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once 'database.php';

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$db = new Database();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'regions':
            if ($method === 'GET') {
                $regions = $db->getRegions();
                respond(true, ['items' => $regions]);
            }
            break;
            
        case 'cities':
            if ($method === 'GET') {
                $regionId = $_GET['region_id'] ?? '';
                if (!$regionId) {
                    respond(false, ['error' => 'Region ID required'], 400);
                }
                $cities = $db->getCitiesByRegion($regionId);
                respond(true, ['items' => $cities]);
            }
            break;
            
        case 'pois':
            if ($method === 'GET') {
                $cityId = $_GET['city_id'] ?? '';
                if (!$cityId) {
                    respond(false, ['error' => 'City ID required'], 400);
                }
                $pois = $db->getPoisByCity($cityId);
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
                $stats = $db->getPoiCountsByCity($regionId);
                respond(true, ['stats' => $stats]);
            }
            break;
            
        case 'sync':
            if ($method === 'POST') {
                // This will be handled by sync script
                respond(true, ['message' => 'Sync initiated']);
            }
            break;
            
        case 'update-region':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'] ?? '';
                $updateData = $data['data'] ?? [];
                
                if (!$id) {
                    respond(false, ['error' => 'Region ID required'], 400);
                }
                
                $result = $db->updateRegion($id, $updateData);
                if ($result) {
                    respond(true, ['message' => 'Region updated']);
                } else {
                    respond(false, ['error' => 'Failed to update region'], 500);
                }
            }
            break;
            
        case 'delete-region':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'] ?? '';
                
                if (!$id) {
                    respond(false, ['error' => 'Region ID required'], 400);
                }
                
                $result = $db->deleteRegion($id);
                if ($result) {
                    respond(true, ['message' => 'Region deleted']);
                } else {
                    respond(false, ['error' => 'Failed to delete region'], 500);
                }
            }
            break;
            
        case 'update-city':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'] ?? '';
                $updateData = $data['data'] ?? [];
                
                if (!$id) {
                    respond(false, ['error' => 'City ID required'], 400);
                }
                
                $result = $db->updateCity($id, $updateData);
                if ($result) {
                    respond(true, ['message' => 'City updated']);
                } else {
                    respond(false, ['error' => 'Failed to update city'], 500);
                }
            }
            break;
            
        case 'delete-city':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'] ?? '';
                
                if (!$id) {
                    respond(false, ['error' => 'City ID required'], 400);
                }
                
                $result = $db->deleteCity($id);
                if ($result) {
                    respond(true, ['message' => 'City deleted']);
                } else {
                    respond(false, ['error' => 'Failed to delete city'], 500);
                }
            }
            break;
            
        case 'update-poi':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'] ?? '';
                $updateData = $data['data'] ?? [];
                
                if (!$id) {
                    respond(false, ['error' => 'POI ID required'], 400);
                }
                
                $result = $db->updatePoi($id, $updateData);
                if ($result) {
                    respond(true, ['message' => 'POI updated']);
                } else {
                    respond(false, ['error' => 'Failed to update POI'], 500);
                }
            }
            break;
            
        case 'delete-poi':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'] ?? '';
                
                if (!$id) {
                    respond(false, ['error' => 'POI ID required'], 400);
                }
                
                $result = $db->deletePoi($id);
                if ($result) {
                    respond(true, ['message' => 'POI deleted']);
                } else {
                    respond(false, ['error' => 'Failed to delete POI'], 500);
                }
            }
            break;
            
        default:
            respond(false, ['error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    respond(false, ['error' => $e->getMessage()], 500);
}
?>
