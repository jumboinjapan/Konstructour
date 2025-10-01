<?php
// Проверка данных в Airtable
// Этот скрипт проверяет, какие данные есть в Airtable

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
        echo "Ошибка загрузки секретов: " . $e->getMessage() . "\n";
    }
    
    throw new Exception("Не удалось получить токен Airtable");
}

// Функция для запроса к Airtable API
function airtableRequest($endpoint, $token) {
    $url = "https://api.airtable.com/v0/apppwhjFN82N9zNqm/$endpoint";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
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
    echo "🔍 Проверяем данные в Airtable...\n";
    
    $token = getAirtableToken();
    echo "✅ Токен Airtable получен\n";
    
    // Проверяем таблицу POI
    echo "📊 Проверяем таблицу POI...\n";
    $poisData = airtableRequest('tblVCmFcHRpXUT24y', $token);
    
    if (isset($poisData['records'])) {
        echo "Найдено записей: " . count($poisData['records']) . "\n";
        
        // Показываем первые 5 записей
        for ($i = 0; $i < min(5, count($poisData['records'])); $i++) {
            $record = $poisData['records'][$i];
            $fields = $record['fields'];
            
            echo "\n--- Запись " . ($i + 1) . " ---\n";
            echo "ID: " . $record['id'] . "\n";
            echo "Название (RU): " . ($fields['Название (RU)'] ?? 'Нет') . "\n";
            echo "Название (EN): " . ($fields['Название (EN)'] ?? 'Нет') . "\n";
            echo "POI ID: " . ($fields['POI ID'] ?? 'Нет') . "\n";
            echo "City Location: " . (isset($fields['City Location']) ? implode(', ', $fields['City Location']) : 'Нет') . "\n";
            echo "Regions: " . (isset($fields['Regions']) ? implode(', ', $fields['Regions']) : 'Нет') . "\n";
            echo "POI Category (RU): " . (isset($fields['POI Category (RU)']) ? implode(', ', $fields['POI Category (RU)']) : 'Нет') . "\n";
        }
    } else {
        echo "❌ Нет записей в таблице POI\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
?>
