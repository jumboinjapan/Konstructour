<?php
// Простая настройка токена Airtable
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Token is required']);
    exit;
}

try {
    // Создаем файл с токеном
    $tokenFile = __DIR__ . '/airtable.env.local';
    $content = "AIRTABLE_PAT=$token\n";
    
    if (file_put_contents($tokenFile, $content) === false) {
        throw new Exception('Failed to write token file');
    }
    
    // Устанавливаем права доступа
    chmod($tokenFile, 0600);
    
    echo json_encode([
        'ok' => true,
        'message' => 'Token saved successfully',
        'file' => 'airtable.env.local'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>
