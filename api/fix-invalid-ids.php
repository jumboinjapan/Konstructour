<?php
/**
 * Скрипт автоисправления некорректных ID
 * Пытается исправить некорректные business_id на основе данных из Airtable
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'database.php';
require_once 'filter-constants.php';

// Функция для получения токена Airtable
function getAirtableToken() {
    $token = getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_API_KEY');
    if ($token) {
        return $token;
    }
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
    return 'pat' . str_repeat('A', 14) . '.' . str_repeat('B', 22); // Тестовый токен
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
    echo "🔍 Начинаем исправление некорректных ID...\n\n";
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Сканируем базу данных
    $invalidIds = findInvalidIds($pdo);
    
    if (empty($invalidIds)) {
        echo "✅ Некорректных ID не найдено!\n";
        exit(0);
    }
    
    echo "❌ Найдено " . count($invalidIds) . " некорректных ID:\n";
    foreach ($invalidIds as $record) {
        echo "  - {$record['table']}: {$record['id']} -> '{$record['bad_id']}'\n";
    }
    echo "\n";
    
    // Получаем токен Airtable
    $token = getAirtableToken();
    echo "🔑 Токен Airtable получен\n";
    
    // Создаем маппинг Airtable ID -> business_id
    $airtableMapping = [];
    
    // Загружаем регионы из Airtable
    echo "📊 Загружаем регионы из Airtable...\n";
    $regionsData = airtableRequest('tblbSajWkzI8X7M4U', $token);
    foreach ($regionsData['records'] as $record) {
        $fields = $record['fields'];
        $businessId = $fields['REGION ID'] ?? null;
        if ($businessId && validateBusinessId($businessId, 'region')) {
            $airtableMapping['regions'][$record['id']] = $businessId;
        }
    }
    echo "  ✅ Загружено " . count($airtableMapping['regions'] ?? []) . " регионов\n";
    
    // Загружаем города из Airtable
    echo "🏙️ Загружаем города из Airtable...\n";
    $citiesData = airtableRequest('tblHaHc9NV0mA8bSa', $token);
    foreach ($citiesData['records'] as $record) {
        $fields = $record['fields'];
        $businessId = $fields['CITY ID'] ?? null;
        if ($businessId && validateBusinessId($businessId, 'city')) {
            $airtableMapping['cities'][$record['id']] = $businessId;
        }
    }
    echo "  ✅ Загружено " . count($airtableMapping['cities'] ?? []) . " городов\n";
    
    // Загружаем POI из Airtable
    echo "📍 Загружаем POI из Airtable...\n";
    $poisData = airtableRequest('tblVCmFcHRpXUT24y', $token);
    foreach ($poisData['records'] as $record) {
        $fields = $record['fields'];
        $businessId = $fields['POI ID'] ?? null;
        if ($businessId && validateBusinessId($businessId, 'poi')) {
            $airtableMapping['pois'][$record['id']] = $businessId;
        }
    }
    echo "  ✅ Загружено " . count($airtableMapping['pois'] ?? []) . " POI\n";
    
    // Исправляем некорректные ID
    echo "\n🔧 Начинаем исправление...\n";
    $fixed = 0;
    $failed = 0;
    
    foreach ($invalidIds as $record) {
        $table = $record['table'];
        $airtableId = $record['id'];
        $badBusinessId = $record['bad_id'];
        
        // Ищем правильный business_id в Airtable
        $correctBusinessId = $airtableMapping[$table][$airtableId] ?? null;
        
        if ($correctBusinessId) {
            try {
                // Обновляем запись в базе данных
                $stmt = $pdo->prepare("UPDATE $table SET business_id = ? WHERE id = ?");
                $stmt->execute([$correctBusinessId, $airtableId]);
                
                echo "  ✅ Исправлено: $table ($airtableId) -> '$correctBusinessId'\n";
                $fixed++;
                
            } catch (Exception $e) {
                echo "  ❌ Ошибка при исправлении $table ($airtableId): " . $e->getMessage() . "\n";
                $failed++;
            }
        } else {
            echo "  ⚠️ Не найден корректный business_id для $table ($airtableId) в Airtable\n";
            $failed++;
        }
    }
    
    echo "\n📊 Результаты исправления:\n";
    echo "  ✅ Исправлено: $fixed\n";
    echo "  ❌ Ошибок: $failed\n";
    
    if ($failed > 0) {
        echo "\n⚠️ Некоторые записи требуют ручного вмешательства.\n";
        echo "Проверьте логи и данные в Airtable.\n";
    }
    
    echo "\n🎉 Исправление завершено!\n";
    
} catch (Exception $e) {
    echo "❌ Критическая ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
?>
