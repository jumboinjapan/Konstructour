<?php
// Скрипт для обновления databases.html на сервере

$targetUrl = 'https://www.konstructour.com/api/update-databases-html.php';
$filePath = __DIR__ . '/site-admin/databases.html';

if (!file_exists($filePath)) {
    die("File not found: $filePath\n");
}

$content = file_get_contents($filePath);
if ($content === false) {
    die("Failed to read file: $filePath\n");
}

$postData = http_build_query(['content' => $content]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $postData
    ]
]);

echo "Sending updated databases.html to server...\n";
echo "File size: " . strlen($content) . " bytes\n";

$result = file_get_contents($targetUrl, false, $context);

if ($result === false) {
    die("Failed to send request to server\n");
}

$response = json_decode($result, true);

if ($response && $response['ok']) {
    echo "✅ Success: " . $response['message'] . "\n";
    echo "Backup created: " . $response['backup'] . "\n";
    echo "File size: " . $response['size'] . " bytes\n";
} else {
    echo "❌ Error: " . ($response['error'] ?? 'Unknown error') . "\n";
    echo "Response: $result\n";
}
?>
