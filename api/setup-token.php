<?php
header('Content-Type: application/json; charset=utf-8');

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = trim($input['token'] ?? '');

    if (!$token || strncmp($token, 'pat', 3) !== 0) {
        respond(false, ['error' => 'Invalid token format'], 400);
    }

    // тот же путь, что в _airtable-common.php
    $file = __DIR__ . '/airtable.env.local';
    $content = "AIRTABLE_PAT=" . $token . "\n";

    if (file_put_contents($file, $content, LOCK_EX) === false) {
        respond(false, ['error' => 'Cannot write token file'], 500);
    }

    respond(true, ['message' => 'Airtable token saved successfully']);
} else {
    respond(false, ['error' => 'Invalid request method'], 405);
}
?>