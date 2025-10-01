<?php
// Синхронизация только POI из Airtable
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
    echo "🔄 Синхронизируем POI из Airtable...\n";
    
    $token = getAirtableToken();
    echo "✅ Токен Airtable получен\n";
    
    $db = new Database();
    
    // Очищаем старые POI
    echo "🗑️ Очищаем старые POI...\n";
    $db->getConnection()->exec("DELETE FROM pois");
    
    // Синхронизируем POI из таблицы tblVCmFcHRpXUT24y
    echo "📍 Загружаем POI из таблицы tblVCmFcHRpXUT24y...\n";
    $poisData = airtableRequest('tblVCmFcHRpXUT24y', $token);
    
    if (isset($poisData['records'])) {
        echo "Найдено записей POI: " . count($poisData['records']) . "\n";
        
        foreach ($poisData['records'] as $record) {
            $fields = $record['fields'];
            
            // Анализируем поля
            echo "\n--- Анализ записи POI ---\n";
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
            
            // Ищем поля с данными POI
            $nameRu = null;
            $nameEn = null;
            $businessId = null;
            $cityId = null;
            $regionId = null;
            $category = null;
            $descriptionRu = null;
            $descriptionEn = null;
            $prefectureRu = null;
            $prefectureEn = null;
            $website = null;
            $workingHours = null;
            $notes = null;
            
            foreach ($fields as $fieldName => $fieldValue) {
                if (stripos($fieldName, 'POI Name') !== false && stripos($fieldName, 'RU') !== false && is_string($fieldValue)) {
                    $nameRu = $fieldValue;
                } elseif (stripos($fieldName, 'POI Name') !== false && stripos($fieldName, 'EN') !== false && is_string($fieldValue)) {
                    $nameEn = $fieldValue;
                } elseif (stripos($fieldName, 'POI ID') !== false && is_string($fieldValue)) {
                    $businessId = $fieldValue;
                } elseif (stripos($fieldName, 'City Location') !== false && is_array($fieldValue) && !empty($fieldValue)) {
                    $cityId = $fieldValue[0];
                } elseif (stripos($fieldName, 'Regions') !== false && is_array($fieldValue) && !empty($fieldValue)) {
                    $regionId = $fieldValue[0];
                } elseif (stripos($fieldName, 'POI Category') !== false && stripos($fieldName, 'RU') !== false && is_array($fieldValue) && !empty($fieldValue)) {
                    $category = $fieldValue[0]; // Берем первую категорию
                } elseif (stripos($fieldName, 'Description') !== false && stripos($fieldName, 'RU') !== false && is_string($fieldValue)) {
                    $descriptionRu = $fieldValue;
                } elseif (stripos($fieldName, 'Description') !== false && stripos($fieldName, 'EN') !== false && is_string($fieldValue)) {
                    $descriptionEn = $fieldValue;
                } elseif (stripos($fieldName, 'Prefecture') !== false && stripos($fieldName, 'RU') !== false && is_string($fieldValue)) {
                    $prefectureRu = $fieldValue;
                } elseif (stripos($fieldName, 'Prefecture') !== false && stripos($fieldName, 'EN') !== false && is_string($fieldValue)) {
                    $prefectureEn = $fieldValue;
                } elseif (stripos($fieldName, 'Website') !== false && is_string($fieldValue)) {
                    $website = $fieldValue;
                } elseif (stripos($fieldName, 'Hours') !== false && is_string($fieldValue)) {
                    $workingHours = $fieldValue;
                } elseif (stripos($fieldName, 'Notes') !== false && is_string($fieldValue)) {
                    $notes = $fieldValue;
                }
            }
            
            // Если не нашли business_id, генерируем
            if (!$businessId) {
                $businessId = 'POI-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
            }
            
            // Если не нашли city_id или region_id, пропускаем запись
            if (!$cityId || !$regionId) {
                echo "  ⚠️ Пропускаем POI без города или региона: {$nameRu}\n";
                continue;
            }
            
            // Найдем Airtable ID города
            $cities = $db->getAllCities();
            $cityAirtableId = null;
            foreach ($cities as $city) {
                if ($city['id'] === $cityId) {
                    $cityAirtableId = $city['id'];
                    break;
                }
            }
            
            if (!$cityAirtableId) {
                echo "  ⚠️ Не найден город для POI {$nameRu}: {$cityId}\n";
                // Попробуем найти город по business_id
                foreach ($cities as $city) {
                    if ($city['business_id'] === $cityId) {
                        $cityAirtableId = $city['id'];
                        echo "  ✅ Найден город по business_id: {$city['name_ru']}\n";
                        break;
                    }
                }
                if (!$cityAirtableId) {
                    continue;
                }
            }
            
            // Найдем Airtable ID региона
            $regions = $db->getRegions();
            $regionAirtableId = null;
            foreach ($regions as $region) {
                if ($region['id'] === $regionId) {
                    $regionAirtableId = $region['id'];
                    break;
                }
            }
            
            if (!$regionAirtableId) {
                echo "  ⚠️ Не найден регион для POI {$nameRu}: {$regionId}\n";
                continue;
            }
            
            $poiData = [
                'id' => $record['id'],
                'business_id' => $businessId,
                'name_ru' => $nameRu ?? 'Неизвестно',
                'name_en' => $nameEn ?? 'Unknown',
                'category' => $category ?? 'Unknown',
                'city_id' => $cityAirtableId,
                'region_id' => $regionAirtableId,
                'description_ru' => $descriptionRu,
                'description_en' => $descriptionEn,
                'prefecture_ru' => $prefectureRu,
                'prefecture_en' => $prefectureEn,
                'website' => $website,
                'working_hours' => $workingHours,
                'notes' => $notes
            ];
            
            $db->savePoi($poiData);
            echo "  ✅ POI: {$poiData['name_ru']} ({$poiData['business_id']}) -> Город: {$cityId}, Регион: {$regionId}\n";
        }
    } else {
        echo "❌ Нет записей в таблице POI\n";
    }
    
    echo "\n✅ Синхронизация POI завершена!\n";
    
    // Показываем итоговую статистику
    $pois = $db->getAllPois();
    echo "📊 Итого POI в базе: " . count($pois) . "\n";
    
    // Показываем POI по городам
    $cities = $db->getAllCities();
    foreach ($cities as $city) {
        $cityPois = array_filter($pois, function($poi) use ($city) {
            return $poi['city_id'] === $city['id'];
        });
        if (count($cityPois) > 0) {
            echo "  - {$city['name_ru']}: " . count($cityPois) . " POI\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка синхронизации: " . $e->getMessage() . "\n";
    exit(1);
}
?>
