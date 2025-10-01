<?php
// Чистая синхронизация данных из Airtable
// Использует только business_id для логики, названия только для отображения

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Простая функция для получения токена Airtable
function getAirtableToken() {
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

// Простая функция для работы с базой данных
function getDatabase() {
    $pdo = new PDO('sqlite:konstructour.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function saveRegion($pdo, $data) {
    $stmt = $pdo->prepare("
        INSERT OR REPLACE INTO regions (id, name_ru, name_en, business_id, created_at, updated_at)
        VALUES (:id, :name_ru, :name_en, :business_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    $stmt->execute($data);
}

function saveCity($pdo, $data) {
    $stmt = $pdo->prepare("
        INSERT OR REPLACE INTO cities (id, name_ru, name_en, business_id, region_id, created_at, updated_at)
        VALUES (:id, :name_ru, :name_en, :business_id, :region_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    $stmt->execute($data);
}

function savePOI($pdo, $data) {
    $stmt = $pdo->prepare("
        INSERT OR REPLACE INTO pois (id, name_ru, name_en, business_id, city_id, region_id, created_at, updated_at)
        VALUES (:id, :name_ru, :name_en, :business_id, :city_id, :region_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    $stmt->execute($data);
}

try {
    echo "🔄 Синхронизация данных из Airtable...\n";
    
    $token = getAirtableToken();
    echo "✅ Токен Airtable получен\n";
    
    $pdo = getDatabase();
    
    // 1. Синхронизируем регионы
    echo "📊 Синхронизируем регионы...\n";
    $pdo->exec("DELETE FROM regions");
    
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
            saveRegion($pdo, $regionData);
            echo "  ✅ {$regionData['business_id']}\n";
        }
    }
    
    // 2. Синхронизируем города
    echo "🏙️ Синхронизируем города...\n";
    $pdo->exec("DELETE FROM cities");
    
    $citiesData = airtableRequest('tblHaHc9NV0mA8bSa', $token);
    echo "  📊 Получено записей городов: " . (isset($citiesData['records']) ? count($citiesData['records']) : 0) . "\n";
    
    if (isset($citiesData['records'])) {
        // Получаем регионы для сопоставления (Airtable ID -> business_id)
        $regions = [];
        $stmt = $pdo->query("SELECT id, business_id FROM regions");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $regions[$row['id']] = $row['business_id']; // Airtable ID -> business_id
        }
        echo "  📊 Загружено регионов для сопоставления: " . count($regions) . "\n";
        
        foreach ($citiesData['records'] as $record) {
            $fields = $record['fields'];
            
            // Получаем Airtable ID региона из поля Region ID
            $regionAirtableId = null;
            if (isset($fields['Region ID'])) {
                $regionId = $fields['Region ID'];
                if (is_array($regionId) && !empty($regionId)) {
                    $regionAirtableId = $regionId[0];
                } elseif (is_string($regionId)) {
                    $regionAirtableId = $regionId;
                }
            }
            
            // Получаем business_id региона по Airtable ID
            $regionBusinessId = null;
            if ($regionAirtableId && isset($regions[$regionAirtableId])) {
                $regionBusinessId = $regions[$regionAirtableId];
            }
            
            echo "  🔍 Город: " . ($fields['Name (RU)'] ?? 'Без названия') . " | Region ID: " . json_encode($fields['Region ID'] ?? 'НЕТ') . " | Airtable ID: " . ($regionAirtableId ?? 'НЕТ') . " | Business ID: " . ($regionBusinessId ?? 'НЕТ') . "\n";
            
            if ($regionAirtableId && isset($regions[$regionAirtableId])) {
                $cityData = [
                    'id' => $record['id'],
                    'business_id' => $fields['CITY ID'] ?? 'CTY-' . str_pad(rand(1, 32), 4, '0', STR_PAD_LEFT),
                    'name_ru' => $fields['Name (RU)'] ?? 'Неизвестно',
                    'name_en' => $fields['Name (EN)'] ?? 'Unknown',
                    'region_id' => $regionAirtableId // Используем Airtable ID для связи
                ];
                saveCity($pdo, $cityData);
                echo "  ✅ {$cityData['business_id']}\n";
            }
        }
    }
    
    // 3. Синхронизируем POI
    echo "📍 Синхронизируем POI...\n";
    $pdo->exec("DELETE FROM pois");
    
    $poisData = airtableRequest('tblVCmFcHRpXUT24y', $token);
    if (isset($poisData['records'])) {
        // Получаем города и регионы для сопоставления
        $cities = [];
        $stmt = $pdo->query("SELECT id, business_id FROM cities");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cities[$row['business_id']] = $row['id'];
        }
        
        $regions = [];
        $stmt = $pdo->query("SELECT id, business_id FROM regions");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $regions[$row['business_id']] = $row['id'];
        }
        
        foreach ($poisData['records'] as $record) {
            $fields = $record['fields'];
            
            // Получаем business_id города из поля City Location
            $cityBusinessId = null;
            if (isset($fields['City Location'])) {
                $cityLocation = $fields['City Location'];
                if (is_array($cityLocation) && !empty($cityLocation)) {
                    $cityBusinessId = $cityLocation[0];
                } elseif (is_string($cityLocation)) {
                    $cityBusinessId = $cityLocation;
                }
            }
            
            // Получаем business_id региона из поля Regions
            $regionBusinessId = null;
            if (isset($fields['Regions'])) {
                $regionsField = $fields['Regions'];
                if (is_array($regionsField) && !empty($regionsField)) {
                    $regionBusinessId = $regionsField[0];
                } elseif (is_string($regionsField)) {
                    $regionBusinessId = $regionsField;
                }
            }
            
            if ($cityBusinessId && isset($cities[$cityBusinessId])) {
                $poiData = [
                    'id' => $record['id'],
                    'business_id' => $fields['POI ID'] ?? 'POI-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT),
                    'name_ru' => $fields['POI Name (RU)'] ?? 'Неизвестно',
                    'name_en' => $fields['POI Name (EN)'] ?? 'Unknown',
                    'city_id' => $cities[$cityBusinessId],
                    'region_id' => ($regionBusinessId && isset($regions[$regionBusinessId])) ? $regions[$regionBusinessId] : null
                ];
                savePOI($pdo, $poiData);
                echo "  ✅ {$poiData['business_id']}\n";
            }
        }
    }
    
    echo "✅ Синхронизация завершена!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
?>
?>