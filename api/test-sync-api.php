<?php
// Тестовая версия API синхронизации для отладки
header('Content-Type: application/json; charset=utf-8');

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = $_GET['action'] ?? 'test';

try {
    switch ($action) {
        case 'test':
            respond(true, [
                'message' => 'Test API working',
                'timestamp' => date('c'),
                'server' => $_SERVER['SERVER_NAME'] ?? 'localhost'
            ]);
            break;
            
        case 'full':
            // Имитируем успешную синхронизацию
            respond(true, [
                'airtable_to_local' => 5,
                'local_to_airtable' => 3,
                'updates' => 2,
                'deletes' => 0,
                'errors' => [],
                'message' => 'Test sync completed successfully'
            ]);
            break;
            
        case 'airtable_to_local':
            respond(true, [
                'airtable_to_local' => 3,
                'message' => 'Test Airtable to Local sync completed'
            ]);
            break;
            
        case 'local_to_airtable':
            respond(true, [
                'local_to_airtable' => 2,
                'message' => 'Test Local to Airtable sync completed'
            ]);
            break;
            
        default:
            respond(false, ['error' => 'Unknown action'], 400);
    }
    
} catch (Exception $e) {
    respond(false, ['error' => $e->getMessage()], 500);
}
?>
