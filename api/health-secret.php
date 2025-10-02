<?php
// api/health-secret.php
require_once __DIR__.'/secret-manager.php';

header('Content-Type: application/json; charset=utf-8');
try {
    $path = SecretManager::path();
    $j    = SecretManager::load();
    $tok  = $j['current']['token'] ?? null;
    $ok   = is_string($tok) && (str_starts_with($tok, 'pat.') || str_starts_with($tok, 'patTest'));
    echo json_encode([
        'ok' => $ok,
        'state' => [
            'path' => $path,
            'file_exists' => file_exists($path),
            'file_readable' => is_readable($path),
            'has_current' => $ok,
            'has_next' => is_string($j['next']['token'] ?? null),
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (SecretError $e) {
    http_response_code(200);
    echo json_encode(['ok'=>false,'reason'=>$e->reason,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
