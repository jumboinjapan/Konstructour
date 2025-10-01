<?php
// Синхронизация только регионов из Airtable
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
    echo "🔄 Синхронизируем регионы из Airtable...\n";
    
    $token = getAirtableToken();
    echo "✅ Токен Airtable получен\n";
    
    $db = new Database();
    
    // Очищаем старые регионы
    echo "🗑️ Очищаем старые регионы...\n";
    $db->getConnection()->exec("DELETE FROM regions");
    
    // Синхронизируем регионы из таблицы tblbSajWkzI8X7M4U
    echo "📊 Загружаем регионы из таблицы tblbSajWkzI8X7M4U...\n";
    $regionsData = airtableRequest('tblbSajWkzI8X7M4U', $token);
    
    if (isset($regionsData['records'])) {
        echo "Найдено записей регионов: " . count($regionsData['records']) . "\n";
        
        foreach ($regionsData['records'] as $record) {
            $fields = $record['fields'];
            
            // Анализируем поля
            echo "\n--- Анализ записи региона ---\n";
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
            
            // Ищем поля с названиями
            $nameRu = null;
            $nameEn = null;
            $businessId = null;
            
            foreach ($fields as $fieldName => $fieldValue) {
                if (stripos($fieldName, 'name') !== false && stripos($fieldName, 'ru') !== false) {
                    $nameRu = $fieldValue;
                } elseif (stripos($fieldName, 'name') !== false && stripos($fieldName, 'en') !== false) {
                    $nameEn = $fieldValue;
                } elseif (stripos($fieldName, 'id') !== false || stripos($fieldName, 'ID') !== false) {
                    $businessId = $fieldValue;
                }
            }
            
            // Если не нашли business_id, генерируем
            if (!$businessId) {
                $businessId = 'REG-' . str_pad(rand(1, 9), 4, '0', STR_PAD_LEFT);
            }
            
            $regionData = [
                'id' => $record['id'],
                'business_id' => $businessId,
                'name_ru' => $nameRu ?? 'Неизвестно',
                'name_en' => $nameEn ?? 'Unknown'
            ];
            
            $db->saveRegion($regionData);
            echo "  ✅ Регион: {$regionData['name_ru']} ({$regionData['business_id']})\n";
        }
    } else {
        echo "❌ Нет записей в таблице регионов\n";
    }
    
    echo "\n✅ Синхронизация регионов завершена!\n";
    
    // Показываем итоговую статистику
    $regions = $db->getRegions();
    echo "📊 Итого регионов в базе: " . count($regions) . "\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка синхронизации: " . $e->getMessage() . "\n";
    exit(1);
}
?>
