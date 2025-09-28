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
                respond(true, $stats);
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
