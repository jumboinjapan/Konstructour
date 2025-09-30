<?php
// Check Airtable data without API token (using public info)
echo "🔍 Проверка доступности Airtable без API токена...\n\n";

// Airtable configuration
$baseId = 'apppwhjFN82N9zNqm';
$tables = [
    'regions' => 'tblbSajWkzI8X7M4U',
    'cities' => 'tblHaHc9NV0mA8bSa', 
    'pois' => 'tblVCmFcHRpXUT24y'
];

function checkAirtableTable($baseId, $tableId) {
    // Try to access table metadata (this might work without auth for public bases)
    $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}?pageSize=1";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_NOBODY => true, // HEAD request only
        CURLOPT_HEADER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'headers' => $headers
    ];
}

echo "📊 Проверка доступности таблиц Airtable:\n";
echo "Base ID: {$baseId}\n\n";

foreach ($tables as $tableName => $tableId) {
    echo "🔍 Проверка таблицы: {$tableName} (ID: {$tableId})\n";
    
    $result = checkAirtableTable($baseId, $tableId);
    $httpCode = $result['http_code'];
    
    if ($httpCode === 200) {
        echo "✅ Таблица доступна (HTTP 200)\n";
    } elseif ($httpCode === 401) {
        echo "🔐 Требуется авторизация (HTTP 401)\n";
    } elseif ($httpCode === 404) {
        echo "❌ Таблица не найдена (HTTP 404)\n";
    } else {
        echo "⚠️  Неожиданный ответ (HTTP {$httpCode})\n";
    }
    
    echo "\n";
}

echo "💡 Для получения точного количества записей нужен API токен:\n";
echo "1. Получите токен на https://airtable.com/create/tokens\n";
echo "2. Установите: export AIRTABLE_PAT='your_token_here'\n";
echo "3. Запустите: php count-airtable-data.php\n\n";

echo "🔧 Альтернативные способы:\n";
echo "- Используйте веб-интерфейс Airtable для подсчета\n";
echo "- Экспортируйте данные через Airtable UI\n";
echo "- Используйте Airtable API напрямую с токеном\n";
?>
