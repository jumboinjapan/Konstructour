<?php
// api/valid-data.php
// API endpoint для получения всех валидных данных (только с корректными business_id)

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once 'database.php';

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $db = new Database();
    $data = $db->getAllValidData();
    
    respond(true, [
        'message' => 'Все валидные данные получены успешно',
        'data' => $data,
        'timestamp' => date('c')
    ]);

} catch (Exception $e) {
    respond(false, [
        'error' => $e->getMessage(),
        'message' => 'Ошибка при получении валидных данных'
    ], 500);
}
?>
