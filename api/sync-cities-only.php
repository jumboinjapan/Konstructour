<?php
// Синхронизация только городов из Airtable
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
    echo "🔄 Синхронизируем города из Airtable...\n";
    
    $token = getAirtableToken();
    echo "✅ Токен Airtable получен\n";
    
    $db = new Database();
    
    // Очищаем старые города
    echo "🗑️ Очищаем старые города...\n";
    $db->getConnection()->exec("DELETE FROM cities");
    
    // Синхронизируем города из таблицы tblHaHc9NV0mA8bSa
    echo "🏙️ Загружаем города из таблицы tblHaHc9NV0mA8bSa...\n";
    $citiesData = airtableRequest('tblHaHc9NV0mA8bSa', $token);
    
    if (isset($citiesData['records'])) {
        echo "Найдено записей городов: " . count($citiesData['records']) . "\n";
        
        foreach ($citiesData['records'] as $record) {
            $fields = $record['fields'];
            
            // Анализируем поля
            echo "\n--- Анализ записи города ---\n";
            echo "ID: " . $record['id'] . "\n";
            echo "Доступные поля:\n";
            foreach ($fields as $fieldName => $fieldValue) {
                $type = gettype($fieldValue);
                $preview = is_string($fieldValue) ? substr($fieldValue, 0, 50) : 
                          (is_array($fieldValue) ? 'Array[' . count($fieldValue) . ']' : 
                          (is_bool($fieldValue) ? ($fieldValue ? 'true' : 'false') : 
                          (is_null($fieldValue) ? 'null' : $fieldValue)));
                
                echo "  - $fieldName ($type): $preview\n";
            }
            
            // Ищем поля с названиями и ID
            $nameRu = null;
            $nameEn = null;
            $businessId = null;
            $regionId = null;
            
            foreach ($fields as $fieldName => $fieldValue) {
                if (stripos($fieldName, 'name') !== false && stripos($fieldName, 'ru') !== false && is_string($fieldValue)) {
                    $nameRu = $fieldValue;
                } elseif (stripos($fieldName, 'name') !== false && stripos($fieldName, 'en') !== false && is_string($fieldValue)) {
                    $nameEn = $fieldValue;
                } elseif (stripos($fieldName, 'CITY ID') !== false && is_string($fieldValue)) {
                    $businessId = $fieldValue;
                } elseif (stripos($fieldName, 'region') !== false && is_array($fieldValue) && !empty($fieldValue)) {
                    $regionId = $fieldValue[0]; // Берем первый элемент массива
                }
            }
            
            // Если не нашли business_id, генерируем
            if (!$businessId) {
                $businessId = 'CTY-' . str_pad(rand(1, 32), 4, '0', STR_PAD_LEFT);
            }
            
            // Если не нашли region_id, пропускаем запись
            if (!$regionId) {
                echo "  ⚠️ Пропускаем город без региона: {$nameRu}\n";
                continue;
            }
            
            $cityData = [
                'id' => $record['id'],
                'business_id' => $businessId,
                'name_ru' => $nameRu ?? 'Неизвестно',
                'name_en' => $nameEn ?? 'Unknown',
                'region_id' => $regionId
            ];
            
            $db->saveCity($cityData);
            echo "  ✅ Город: {$cityData['name_ru']} ({$cityData['business_id']}) -> Регион: {$regionId}\n";
        }
    } else {
        echo "❌ Нет записей в таблице городов\n";
    }
    
    echo "\n✅ Синхронизация городов завершена!\n";
    
    // Показываем итоговую статистику
    $cities = $db->getAllCities();
    echo "📊 Итого городов в базе: " . count($cities) . "\n";
    
    // Показываем города по регионам
    $regions = $db->getRegions();
    foreach ($regions as $region) {
        $regionCities = array_filter($cities, function($city) use ($region) {
            return $city['region_id'] === $region['id'];
        });
        echo "  - {$region['name_ru']}: " . count($regionCities) . " городов\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка синхронизации: " . $e->getMessage() . "\n";
    exit(1);
}
?>
