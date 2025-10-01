<?php
// api/debug-airtable-data.php
// Анализ данных Airtable для отладки синхронизации

require_once 'database.php';

// Функция для получения токена Airtable
function getAirtableToken() {
    $token = getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_API_KEY');
    if ($token) {
        return $token;
    }
    
    try {
        require_once 'secret-airtable.php';
        $tokens = load_airtable_tokens();
        if ($tokens['current']) {
            return $tokens['current'];
        }
    } catch (Exception $e) {
        // Игнорируем ошибки секретов
    }
    
    return null;
}

// Функция для запроса к Airtable API
function airtableRequest($tableId, $token, $params = []) {
    $url = "https://api.airtable.com/v0/apppwhjFN82N9zNqm/$tableId";
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Airtable API error: HTTP $httpCode - $response");
    }
    
    return json_decode($response, true);
}

try {
    $token = getAirtableToken();
    if (!$token) {
        throw new Exception("Не удалось получить токен Airtable");
    }
    
    echo "🔍 Анализ данных Airtable...\n\n";
    
    // 1. Анализируем регионы
    echo "📊 РЕГИОНЫ:\n";
    $regionsData = airtableRequest('tblbSajWkzI8X7M4U', $token, ['maxRecords' => 10]);
    echo "  Всего записей: " . count($regionsData['records']) . "\n";
    
    foreach ($regionsData['records'] as $i => $record) {
        $fields = $record['fields'];
        echo "  " . ($i + 1) . ". ID: " . $record['id'] . "\n";
        echo "     Name (RU): " . ($fields['Name (RU)'] ?? 'НЕТ') . "\n";
        echo "     Name (EN): " . ($fields['Name (EN)'] ?? 'НЕТ') . "\n";
        echo "     REGION ID: " . ($fields['REGION ID'] ?? 'НЕТ') . "\n";
        echo "\n";
    }
    
    // 2. Анализируем города
    echo "🏙️ ГОРОДА:\n";
    $citiesData = airtableRequest('tblHaHc9NV0mA8bSa', $token, ['maxRecords' => 10]);
    echo "  Всего записей: " . count($citiesData['records']) . "\n";
    
    foreach ($citiesData['records'] as $i => $record) {
        $fields = $record['fields'];
        echo "  " . ($i + 1) . ". ID: " . $record['id'] . "\n";
        echo "     Name (RU): " . ($fields['Name (RU)'] ?? 'НЕТ') . "\n";
        echo "     Name (EN): " . ($fields['Name (EN)'] ?? 'НЕТ') . "\n";
        echo "     CITY ID: " . ($fields['CITY ID'] ?? 'НЕТ') . "\n";
        echo "     Region ID: " . json_encode($fields['Region ID'] ?? 'НЕТ') . "\n";
        echo "     Regions: " . json_encode($fields['Regions'] ?? 'НЕТ') . "\n";
        echo "\n";
    }
    
    // 3. Анализируем POI
    echo "📍 POI:\n";
    $poisData = airtableRequest('tblVCmFcHRpXUT24y', $token, ['maxRecords' => 5]);
    echo "  Всего записей: " . count($poisData['records']) . "\n";
    
    foreach ($poisData['records'] as $i => $record) {
        $fields = $record['fields'];
        echo "  " . ($i + 1) . ". ID: " . $record['id'] . "\n";
        echo "     POI Name (RU): " . ($fields['POI Name (RU)'] ?? 'НЕТ') . "\n";
        echo "     POI ID: " . ($fields['POI ID'] ?? 'НЕТ') . "\n";
        echo "     City Location: " . json_encode($fields['City Location'] ?? 'НЕТ') . "\n";
        echo "     Regions: " . json_encode($fields['Regions'] ?? 'НЕТ') . "\n";
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>
