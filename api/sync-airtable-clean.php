<?php
// Чистая синхронизация данных из Airtable
// Использует только business_id для логики, названия только для отображения

require_once 'database.php';
require_once 'filter-constants.php';

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
    echo "🔄 Синхронизация данных из Airtable...\n";
    
    $token = getAirtableToken();
    echo "✅ Токен Airtable получен\n";
    
    $db = new Database();
    
    // 1. Синхронизируем регионы
    echo "📊 Синхронизируем регионы...\n";
    $db->getConnection()->exec("DELETE FROM regions");
    
    $regionsData = airtableRequest('tblbSajWkzI8X7M4U', $token);
    if (isset($regionsData['records'])) {
        foreach ($regionsData['records'] as $record) {
            $fields = $record['fields'];
            $regionData = [
                'id' => $record['id'],
                'business_id' => $fields['REGION ID'] ?? 'REG-' . str_pad(rand(1, 9), 4, '0', STR_PAD_LEFT),
                'name_ru' => $fields['Name (RU)'] ?? 'Неизвестно',
                'name_en' => $fields['Name (EN)'] ?? 'Unknown'
            ];
            $db->saveRegion($regionData);
            echo "  ✅ {$regionData['name_ru']} ({$regionData['business_id']})\n";
        }
    }
    
    // 2. Синхронизируем города
    echo "🏙️ Синхронизируем города...\n";
    $db->getConnection()->exec("DELETE FROM cities");
    
    $citiesData = airtableRequest('tblHaHc9NV0mA8bSa', $token);
    if (isset($citiesData['records'])) {
        foreach ($citiesData['records'] as $record) {
            $fields = $record['fields'];
            if (isset($fields['Region ID']) && is_array($fields['Region ID']) && !empty($fields['Region ID'])) {
                // Найдем Airtable ID региона по business_id
                $regions = $db->getRegions();
                $regionAirtableId = null;
                foreach ($regions as $region) {
                    if ($region['business_id'] === $fields['Region ID'][0]) {
                        $regionAirtableId = $region['id'];
                        break;
                    }
                }
                
                if ($regionAirtableId) {
                    $cityData = [
                        'id' => $record['id'],
                        'business_id' => $fields['CITY ID'] ?? 'CTY-' . str_pad(rand(1, 32), 4, '0', STR_PAD_LEFT),
                        'name_ru' => $fields['Name (RU)'] ?? 'Неизвестно',
                        'name_en' => $fields['Name (EN)'] ?? 'Unknown',
                        'region_id' => $regionAirtableId
                    ];
                    $db->saveCity($cityData);
                    echo "  ✅ {$cityData['name_ru']} ({$cityData['business_id']})\n";
                }
            }
        }
    }
    
    // 3. Синхронизируем POI
    echo "📍 Синхронизируем POI...\n";
    $db->getConnection()->exec("DELETE FROM pois");
    
    $poisData = airtableRequest('tblVCmFcHRpXUT24y', $token);
    if (isset($poisData['records'])) {
        foreach ($poisData['records'] as $record) {
            $fields = $record['fields'];
            if (isset($fields['City Location']) && is_array($fields['City Location']) && !empty($fields['City Location'])) {
                // Найдем Airtable ID города по business_id
                $cities = $db->getAllCities();
                $cityAirtableId = null;
                foreach ($cities as $city) {
                    if ($city['business_id'] === $fields['City Location'][0]) {
                        $cityAirtableId = $city['id'];
                        break;
                    }
                }
                
                if ($cityAirtableId) {
                    // Найдем Airtable ID региона
                    $regions = $db->getRegions();
                    $regionAirtableId = null;
                    if (isset($fields['Regions']) && is_array($fields['Regions']) && !empty($fields['Regions'])) {
                        foreach ($regions as $region) {
                            if ($region['business_id'] === $fields['Regions'][0]) {
                                $regionAirtableId = $region['id'];
                                break;
                            }
                        }
                    }
                    
                    if ($regionAirtableId) {
                        $poiData = [
                            'id' => $record['id'],
                            'business_id' => $fields['POI ID'] ?? 'POI-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT),
                            'name_ru' => $fields['POI Name (RU)'] ?? 'Неизвестно',
                            'name_en' => $fields['POI Name (EN)'] ?? 'Unknown',
                            'category' => $fields['POI Category (RU)'][0] ?? 'Unknown',
                            'city_id' => $cityAirtableId,
                            'region_id' => $regionAirtableId,
                            'description_ru' => $fields['Description (RU)'] ?? null,
                            'description_en' => $fields['Description (EN)'] ?? null,
                            'prefecture_ru' => $fields['Prefecture (RU)'] ?? null,
                            'prefecture_en' => $fields['Prefecture (EN)'] ?? null,
                            'website' => $fields['Website / Сайт'] ?? null,
                            'working_hours' => $fields['Hours / Часы работы'] ?? null,
                            'notes' => $fields['Notes / Заметки'] ?? null
                        ];
                        $db->savePoi($poiData);
                        echo "  ✅ {$poiData['name_ru']} ({$poiData['business_id']})\n";
                    }
                }
            }
        }
    }
    
    echo "\n✅ Синхронизация завершена!\n";
    
    // Статистика
    $regions = $db->getRegions();
    $cities = $db->getAllCities();
    $pois = $db->getAllPois();
    
    echo "📊 Регионов: " . count($regions) . "\n";
    echo "📊 Городов: " . count($cities) . "\n";
    echo "📊 POI: " . count($pois) . "\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
?>
