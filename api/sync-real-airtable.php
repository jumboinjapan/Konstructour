<?php
// Синхронизация реальных данных из Airtable
// Этот скрипт должен запускаться на сервере с настроенными секретами

require_once 'database.php';
require_once 'filter-constants.php';

// Функция для получения токена Airtable
function getAirtableToken() {
    // Попробуем получить токен из переменных окружения
    $token = getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_API_KEY');
    if ($token) {
        return $token;
    }
    
    // Попробуем загрузить из файла секретов
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
    echo "🔄 Начинаем синхронизацию с Airtable...\n";
    
    $token = getAirtableToken();
    echo "✅ Токен Airtable получен\n";
    
    $db = new Database();
    
    // 1. Синхронизируем регионы
    echo "📊 Синхронизируем регионы...\n";
    $regionsData = airtableRequest('tblVCmFcHRpXUT24y', $token);
    
    if (isset($regionsData['records'])) {
        foreach ($regionsData['records'] as $record) {
            $fields = $record['fields'];
            $regionData = [
                'id' => $record['id'],
                'business_id' => $fields['POI ID'] ?? 'REG-' . str_pad(rand(1, 9), 4, '0', STR_PAD_LEFT),
                'name_ru' => $fields['Название (RU)'] ?? 'Неизвестно',
                'name_en' => $fields['Название (EN)'] ?? 'Unknown'
            ];
            
            $db->saveRegion($regionData);
            echo "  ✅ Регион: {$regionData['name_ru']} ({$regionData['business_id']})\n";
        }
    }
    
    // 2. Синхронизируем города
    echo "🏙️ Синхронизируем города...\n";
    $citiesData = airtableRequest('tblVCmFcHRpXUT24y', $token);
    
    if (isset($citiesData['records'])) {
        foreach ($citiesData['records'] as $record) {
            $fields = $record['fields'];
            if (isset($fields['City Location'])) {
                $cityData = [
                    'id' => $record['id'],
                    'business_id' => $fields['POI ID'] ?? 'CTY-' . str_pad(rand(1, 32), 4, '0', STR_PAD_LEFT),
                    'name_ru' => $fields['Название (RU)'] ?? 'Неизвестно',
                    'name_en' => $fields['Название (EN)'] ?? 'Unknown',
                    'region_id' => $fields['Regions'][0] ?? null
                ];
                
                $db->saveCity($cityData);
                echo "  ✅ Город: {$cityData['name_ru']} ({$cityData['business_id']})\n";
            }
        }
    }
    
    // 3. Синхронизируем POI
    echo "📍 Синхронизируем POI...\n";
    $poisData = airtableRequest('tblVCmFcHRpXUT24y', $token);
    
    if (isset($poisData['records'])) {
        foreach ($poisData['records'] as $record) {
            $fields = $record['fields'];
            if (isset($fields['City Location']) && isset($fields['Regions'])) {
                $poiData = [
                    'id' => $record['id'],
                    'business_id' => $fields['POI ID'] ?? 'POI-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT),
                    'name_ru' => $fields['Название (RU)'] ?? 'Неизвестно',
                    'name_en' => $fields['Название (EN)'] ?? 'Unknown',
                    'category' => $fields['POI Category (RU)'][0] ?? 'Unknown',
                    'city_id' => $fields['City Location'][0] ?? null,
                    'region_id' => $fields['Regions'][0] ?? null,
                    'description_ru' => $fields['Описание (RU)'] ?? null,
                    'description_en' => $fields['Description (EN)'] ?? null,
                    'prefecture_ru' => $fields['Префектура (RU)'] ?? null,
                    'prefecture_en' => $fields['Prefecture (EN)'] ?? null,
                    'website' => $fields['Website / Сайт'] ?? null,
                    'working_hours' => $fields['Hours / Часы работы'] ?? null,
                    'notes' => $fields['Notes / Заметки'] ?? null
                ];
                
                $db->savePoi($poiData);
                echo "  ✅ POI: {$poiData['name_ru']} ({$poiData['business_id']})\n";
            }
        }
    }
    
    echo "✅ Синхронизация завершена успешно!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка синхронизации: " . $e->getMessage() . "\n";
    exit(1);
}
?>
