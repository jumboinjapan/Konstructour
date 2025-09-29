<?php
header('Content-Type: application/json; charset=utf-8');

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = isset($input['token']) ? trim($input['token']) : '';

    if (!preg_match('/^pat[0-9A-Za-z_]{20,}$/', $token)) {
        respond(false, ['error' => 'Invalid PAT format'], 400);
    }

    $dir = __DIR__ . '/../.secrets';
    @mkdir($dir, 0770, true);
    $file = $dir . '/airtable_pat.txt';

    if (file_put_contents($file, $token, LOCK_EX) === false) {
        respond(false, ['error' => 'Cannot write token file'], 500);
    }

    respond(true, ['message' => 'Airtable token saved successfully']);
} else {
    respond(false, ['error' => 'Invalid request method'], 405);
}
?>