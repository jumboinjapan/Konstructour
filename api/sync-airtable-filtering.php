<?php
// api/sync-airtable-filtering.php
// Синхронизация данных из Airtable строго по принципам Filtering.md
// Использует ТОЛЬКО business_id для логики, Airtable ID только для сохранения

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Устанавливаем JSON заголовок
header('Content-Type: application/json; charset=utf-8');

require_once 'database.php';
require_once 'filter-constants.php';

// Функция для получения токена Airtable
function getAirtableToken() {
    // Сначала пробуем переменные окружения
    $token = getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_API_KEY');
    if ($token) {
        return $token;
    }
    
    // Пробуем загрузить из файла секретов
    try {
        if (file_exists('secret-airtable.php')) {
            require_once 'secret-airtable.php';
            $tokens = load_airtable_tokens();
            if ($tokens['current']) {
                return $tokens['current'];
            }
        }
    } catch (Exception $e) {
        // Игнорируем ошибки загрузки секретов
    }
    
    // Используем токен по умолчанию для тестирования
    return 'pat' . str_repeat('A', 14) . '.' . str_repeat('B', 22);
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

try {
    $log = [];
    $stats = ['regions' => 0, 'cities' => 0, 'pois' => 0];
    
    $log[] = "🔄 Синхронизация данных из Airtable (Filtering.md принципы)...";
    
    $token = getAirtableToken();
    $log[] = "✅ Токен Airtable получен";
    
    $db = new Database();
    
    // Синхронизируем регионы
    $log[] = "📊 Синхронизируем регионы...";
    $regionsData = airtableRequest('tblbSajWkzI8X7M4U', $token);
    
    foreach ($regionsData['records'] as $record) {
        $fields = $record['fields'];
        $businessId = $fields['REGION ID'] ?? null;
        
        // Валидируем business_id согласно Filtering.md
        if (!$businessId || !validateBusinessId($businessId, 'region')) {
            $log[] = "  ⚠️ Пропущен регион с невалидным business_id: " . ($businessId ?? 'null');
            continue;
        }
        
        $regionData = [
            'id' => $record['id'], // Airtable ID только для сохранения
            'business_id' => $businessId, // business_id для логики
            'name_ru' => $fields['Name (RU)'] ?? 'Неизвестно',
            'name_en' => $fields['Name (EN)'] ?? 'Unknown'
        ];
        
        $db->saveRegion($regionData);
        $stats['regions']++;
        $log[] = "  ✅ {$regionData['business_id']}";
    }
    
    // Синхронизируем города
    $log[] = "🏙️ Синхронизируем города...";
    $citiesData = airtableRequest('tblHaHc9NV0mA8bSa', $token);
    
    foreach ($citiesData['records'] as $record) {
        $fields = $record['fields'];
        $businessId = $fields['CITY ID'] ?? null;
        $regionBusinessId = $fields['Region ID'][0] ?? null; // Это business_id региона!
        
        // Валидируем business_id города
        if (!$businessId || !validateBusinessId($businessId, 'city')) {
            $log[] = "  ⚠️ Пропущен город с невалидным business_id: " . ($businessId ?? 'null');
            continue;
        }
        
        // Валидируем business_id региона
        if (!$regionBusinessId || !validateBusinessId($regionBusinessId, 'region')) {
            $log[] = "  ⚠️ Пропущен город {$businessId} - невалидный region business_id: " . ($regionBusinessId ?? 'null');
            continue;
        }
        
        // Находим Airtable ID региона по business_id
        $regionAirtableId = $db->getRegionAirtableIdByBusinessId($regionBusinessId);
        if (!$regionAirtableId) {
            $log[] = "  ⚠️ Пропущен город {$businessId} - регион {$regionBusinessId} не найден";
            continue;
        }
        
        $cityData = [
            'id' => $record['id'], // Airtable ID только для сохранения
            'business_id' => $businessId, // business_id для логики
            'name_ru' => $fields['Name (RU)'] ?? 'Неизвестно',
            'name_en' => $fields['Name (EN)'] ?? 'Unknown',
            'region_id' => $regionAirtableId // Связь через Airtable ID
        ];
        
        $db->saveCity($cityData);
        $stats['cities']++;
        $log[] = "  ✅ {$cityData['business_id']}";
    }
    
    // Синхронизируем POI
    $log[] = "📍 Синхронизируем POI...";
    $poisData = airtableRequest('tblVCmFcHRpXUT24y', $token);
    
    foreach ($poisData['records'] as $record) {
        $fields = $record['fields'];
        $businessId = $fields['POI ID'] ?? null;
        $cityBusinessId = $fields['City Location'][0] ?? null; // Это business_id города!
        
        // Валидируем business_id POI
        if (!$businessId || !validateBusinessId($businessId, 'poi')) {
            $log[] = "  ⚠️ Пропущен POI с невалидным business_id: " . ($businessId ?? 'null');
            continue;
        }
        
        // Валидируем business_id города
        if (!$cityBusinessId || !validateBusinessId($cityBusinessId, 'city')) {
            $log[] = "  ⚠️ Пропущен POI {$businessId} - невалидный city business_id: " . ($cityBusinessId ?? 'null');
            continue;
        }
        
        // Находим Airtable ID города по business_id
        $cityAirtableId = $db->getCityAirtableIdByBusinessId($cityBusinessId);
        if (!$cityAirtableId) {
            $log[] = "  ⚠️ Пропущен POI {$businessId} - город {$cityBusinessId} не найден";
            continue;
        }
        
        $poiData = [
            'id' => $record['id'], // Airtable ID только для сохранения
            'business_id' => $businessId, // business_id для логики
            'name_ru' => $fields['POI Name (RU)'] ?? 'Неизвестно',
            'name_en' => $fields['POI Name (EN)'] ?? 'Unknown',
            'city_id' => $cityAirtableId, // Связь через Airtable ID
            'region_id' => null // Пока не используем
        ];
        
        $db->savePoi($poiData);
        $stats['pois']++;
        $log[] = "  ✅ {$poiData['business_id']}";
    }
    
    $log[] = "✅ Синхронизация завершена!";
    
    echo json_encode([
        'ok' => true,
        'message' => 'Синхронизация завершена успешно (Filtering.md принципы)',
        'stats' => $stats,
        'log' => $log,
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'message' => 'Ошибка синхронизации',
        'timestamp' => date('c')
    ]);
}
?>
