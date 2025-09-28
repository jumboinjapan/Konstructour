<?php
// Test API via HTTP
echo "=== Тест API через HTTP ===\n\n";

// Test stats
echo "1. Тест статистики:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/api/data-api.php?action=stats');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Test regions
echo "2. Тест регионов:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/api/data-api.php?action=regions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: " . substr($response, 0, 200) . "...\n\n";

echo "=== Тест завершен ===\n";
?>
