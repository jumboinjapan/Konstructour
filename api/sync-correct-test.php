<?php
// Тестовая версия правильной синхронизации
require_once 'database.php';

header('Content-Type: application/json; charset=utf-8');

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $db = new Database();
    $action = $_GET['action'] ?? 'sync';
    
    $results = [
        'airtable_to_local' => 0,
        'local_to_airtable' => 0,
        'updates' => 0,
        'errors' => []
    ];
    
    if ($action === 'sync' || $action === 'full') {
        // Имитируем синхронизацию регионов
        $regions = $db->getRegions();
        $regionsCount = count($regions);
        
        // Имитируем результаты синхронизации
        $results['airtable_to_local'] = rand(0, min(3, $regionsCount));
        $results['local_to_airtable'] = rand(0, min(2, $regionsCount));
        $results['updates'] = rand(0, min(2, $regionsCount));
        
        // Имитируем некоторые ошибки (10% вероятность)
        if (rand(1, 10) === 1) {
            $results['errors'][] = "Test error: Connection timeout";
        }
    }
    
    respond(true, [
        'message' => 'Test sync completed successfully',
        'results' => $results,
        'timestamp' => date('c'),
        'note' => 'This is a test version. Configure Airtable token for real sync.'
    ]);
    
} catch (Exception $e) {
    respond(false, ['error' => $e->getMessage()], 500);
}
?>
