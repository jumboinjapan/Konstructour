<?php
// Тест получения Airtable токена
require_once 'config.php';

$config = include 'config.php';

echo "=== Тест получения Airtable токена ===\n\n";

// Проверяем все возможные источники токена
$sources = [
    'config[airtable][api_key]' => $config['airtable']['api_key'] ?? '',
    'config[airtable][token]' => $config['airtable']['token'] ?? '',
    'config[airtable_pat]' => $config['airtable_pat'] ?? '',
    'config[airtable_registry][api_key]' => $config['airtable_registry']['api_key'] ?? '',
    'config[airtable_registry][token]' => $config['airtable_registry']['token'] ?? '',
    'getenv(AIRTABLE_PAT)' => getenv('AIRTABLE_PAT') ?: '',
    'getenv(AIRTABLE_API_KEY)' => getenv('AIRTABLE_API_KEY') ?: ''
];

foreach ($sources as $source => $value) {
    $status = $value ? '✅ Есть' : '❌ Нет';
    $preview = $value ? substr($value, 0, 10) . '...' : 'пусто';
    echo "{$source}: {$status} ({$preview})\n";
}

// Получаем токен тем же способом, что и sync-airtable.php
$pat = ($config['airtable']['api_key'] ?? '')
    ?: (($config['airtable']['token'] ?? '')
    ?: (($config['airtable_pat'] ?? '')
    ?: (($config['airtable_registry']['api_key'] ?? '')
    ?: (($config['airtable_registry']['token'] ?? '')
    ?: (getenv('AIRTABLE_PAT') ?: (getenv('AIRTABLE_API_KEY') ?: ''))))));

echo "\n=== Итоговый токен ===\n";
if ($pat && $pat !== 'PLACEHOLDER_FOR_REAL_API_KEY') {
    echo "✅ Токен найден: " . substr($pat, 0, 10) . "...\n";
    echo "Длина: " . strlen($pat) . " символов\n";
    
    // Тестируем токен
    echo "\n=== Тест токена ===\n";
    $url = "https://api.airtable.com/v0/meta/whoami";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $pat],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP код: {$httpCode}\n";
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        echo "✅ Токен валидный! User ID: " . ($data['id'] ?? 'N/A') . "\n";
    } else {
        echo "❌ Токен невалидный. Ответ: " . substr($response, 0, 200) . "\n";
    }
} else {
    echo "❌ Токен не найден или является placeholder\n";
}

echo "\n=== Конфигурация ===\n";
echo "Base ID: " . ($config['airtable_registry']['baseId'] ?? 'N/A') . "\n";
echo "Region Table: " . ($config['airtable_registry']['tables']['region']['tableId'] ?? 'N/A') . "\n";
echo "City Table: " . ($config['airtable_registry']['tables']['city']['tableId'] ?? 'N/A') . "\n";
echo "POI Table: " . ($config['airtable_registry']['tables']['poi']['tableId'] ?? 'N/A') . "\n";
?>
