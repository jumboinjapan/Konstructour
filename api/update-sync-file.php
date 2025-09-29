<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$newContent = $_POST['content'] ?? '';
if (empty($newContent)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Content is required']);
    exit;
}

$targetFile = __DIR__ . '/sync-correct.php';

try {
    // Создаем резервную копию
    $backupFile = $targetFile . '.backup.' . date('Y-m-d-H-i-s');
    if (file_exists($targetFile)) {
        copy($targetFile, $backupFile);
    }
    
    // Записываем новый контент
    $result = file_put_contents($targetFile, $newContent);
    
    if ($result === false) {
        throw new Exception('Failed to write file');
    }
    
    echo json_encode([
        'ok' => true,
        'message' => 'File updated successfully',
        'backup' => basename($backupFile),
        'size' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>
