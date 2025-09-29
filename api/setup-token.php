<?php
header('Content-Type: application/json; charset=utf-8');

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';

    if (empty($token)) {
        respond(false, ['error' => 'Token is required'], 400);
    }

    $envFilePath = __DIR__ . '/airtable.env.local';
    $content = "AIRTABLE_PAT=" . $token . "\n";

    if (file_put_contents($envFilePath, $content) !== false) {
        // Также обновляем environment variables для текущего запроса
        putenv("AIRTABLE_PAT=$token");
        $_ENV['AIRTABLE_PAT'] = $token;
        $_SERVER['AIRTABLE_PAT'] = $token;
        respond(true, ['message' => 'Airtable token saved successfully']);
    } else {
        respond(false, ['error' => 'Failed to save token file. Check permissions.'], 500);
    }
} else {
    respond(false, ['error' => 'Invalid request method'], 405);
}
?>