<?php
// api/sync-airtable-json.php
// JSON версия синхронизации данных из Airtable

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
    
    $log[] = "🔄 Синхронизация данных из Airtable...";
    
    $token = getAirtableToken();
    $log[] = "✅ Токен Airtable получен";
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Синхронизируем регионы
    $log[] = "📊 Синхронизируем регионы...";
    $regionsData = airtableRequest('tblbSajWkzI8X7M4U', $token);
    $regions = [];
    
    foreach ($regionsData['records'] as $record) {
        $fields = $record['fields'];
        $regionData = [
            'id' => $record['id'],
            'business_id' => $fields['Region ID'] ?? 'REG-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'name_ru' => $fields['Name (RU)'] ?? 'Неизвестно',
            'name_en' => $fields['Name (EN)'] ?? 'Unknown'
        ];
        $db->saveRegion($regionData);
        $regions[$regionData['business_id']] = $regionData['id'];
        $stats['regions']++;
        $log[] = "  ✅ {$regionData['business_id']}";
    }
    
    // Синхронизируем города
    $log[] = "🏙️ Синхронизируем города...";
    $citiesData = airtableRequest('tblHaHc9NV0mA8bSa', $token);
    $cities = [];
    
    foreach ($citiesData['records'] as $record) {
        $fields = $record['fields'];
        $regionBusinessId = $fields['Region ID'][0] ?? null;
        
        if ($regionBusinessId && isset($regions[$regionBusinessId])) {
            $cityData = [
                'id' => $record['id'],
                'business_id' => $fields['City ID'] ?? 'CTY-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'name_ru' => $fields['Name (RU)'] ?? 'Неизвестно',
                'name_en' => $fields['Name (EN)'] ?? 'Unknown',
                'region_id' => $regions[$regionBusinessId]
            ];
            $db->saveCity($cityData);
            $cities[$cityData['business_id']] = $cityData['id'];
            $stats['cities']++;
            $log[] = "  ✅ {$cityData['business_id']}";
        }
    }
    
    // Синхронизируем POI
    $log[] = "📍 Синхронизируем POI...";
    $poisData = airtableRequest('tblVCmFcHRpXUT24y', $token);
    
    foreach ($poisData['records'] as $record) {
        $fields = $record['fields'];
        $cityBusinessId = $fields['City ID'][0] ?? null;
        $regionBusinessId = $fields['Region ID'][0] ?? null;
        
        if ($cityBusinessId && isset($cities[$cityBusinessId])) {
            $poiData = [
                'id' => $record['id'],
                'business_id' => $fields['POI ID'] ?? 'POI-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT),
                'name_ru' => $fields['POI Name (RU)'] ?? 'Неизвестно',
                'name_en' => $fields['POI Name (EN)'] ?? 'Unknown',
                'city_id' => $cities[$cityBusinessId],
                'region_id' => ($regionBusinessId && isset($regions[$regionBusinessId])) ? $regions[$regionBusinessId] : null
            ];
            $db->savePoi($poiData);
            $stats['pois']++;
            $log[] = "  ✅ {$poiData['business_id']}";
        }
    }
    
    $log[] = "✅ Синхронизация завершена!";
    
    echo json_encode([
        'ok' => true,
        'message' => 'Синхронизация завершена успешно',
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
