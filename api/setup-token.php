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

    if (!preg_match('/^pat[0-9A-Za-z._-]{20,}$/', $token)) {
        respond(false, ['error' => 'Invalid PAT format'], 400);
    }

    // Write to multiple persistent locations to survive deployments
    $written = false;
    $errors = [];
    $targets = [];
    // HOME-based storage
    $home = getenv('HOME');
    if (!$home) { $home = dirname(__DIR__, 3); }
    if ($home) {
        $dir1 = rtrim($home, '/').'/.konstructour';
        @mkdir($dir1, 0770, true);
        $targets[] = $dir1 . '/airtable_pat.txt';
    }
    // Project-local secrets
    $dir2 = __DIR__ . '/../.secrets';
    @mkdir($dir2, 0770, true);
    $targets[] = $dir2 . '/airtable_pat.txt';
    // Legacy env file for compatibility
    $targets[] = __DIR__ . '/airtable.env.local';

    foreach ($targets as $path) {
        if (@file_put_contents($path, $token, LOCK_EX) !== false) {
            $written = true;
        } else {
            $errors[] = $path;
        }
    }

    if (!$written) {
        respond(false, ['error' => 'Cannot write token file', 'targets' => $errors], 500);
    }

    respond(true, ['message' => 'Airtable token saved successfully']);
} else {
    respond(false, ['error' => 'Invalid request method'], 405);
}
?>