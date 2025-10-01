<?php
/**
 * API для сохранения POI с синхронизацией в Airtable
 * POST /api/save-poi.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once 'database.php';

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, ['error' => 'Invalid method'], 405);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !is_array($data)) {
    respond(false, ['error' => 'Invalid JSON'], 400);
}

// Валидация обязательных полей
$required = ['name_ru', 'name_en', 'city_id'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        respond(false, ['error' => "Missing required field: $field"], 400);
    }
}

try {
    $db = new Database();
    
    // Генерируем ID если это новый POI
    if (empty($data['id'])) {
        // Получаем максимальный номер POI для города
        $stmt = $db->getConnection()->prepare("
            SELECT business_id FROM pois WHERE city_id = ? ORDER BY business_id DESC LIMIT 1
        ");
        $stmt->execute([$data['city_id']]);
        $lastPoi = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $nextNumber = 1;
        if ($lastPoi && preg_match('/POI-(\d+)$/', $lastPoi['business_id'], $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }
        
        $data['id'] = 'rec' . bin2hex(random_bytes(8));  // Airtable-style ID
        $data['business_id'] = 'POI-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
    
    // Сохраняем в локальную БД
    $dbResult = $db->savePoi($data);
    
    if (!$dbResult) {
        respond(false, ['error' => 'Failed to save to local database'], 500);
    }
    
    // Синхронизация с Airtable
    $cfg = [];
    $cfgFile = __DIR__.'/config.php';
    if (file_exists($cfgFile)) {
        $cfg = require $cfgFile;
        if (!is_array($cfg)) $cfg = [];
    }
    
    $airReg = $cfg['airtable_registry'] ?? null;
    $airtableResult = null;
    
    if ($airReg) {
        $baseId = $airReg['baseId'] ?? ($airReg['base_id'] ?? '');
        $tables = $airReg['tables'] ?? [];
        $poiTable = $tables['poi'] ?? [];
        $tableId = $poiTable['tableId'] ?? ($poiTable['table_id'] ?? '');
        $pat = $airReg['api_key'] ?? ($cfg['airtable']['api_key'] ?? '');
        
        if ($baseId && $tableId && $pat) {
            // Подготовка данных для Airtable
            $airtableFields = [
                'POI ID' => $data['business_id'] ?? $data['id'],
                'POI Name (RU)' => $data['name_ru'],
                'POI Name (EN)' => $data['name_en'] ?? '',
                'Prefecture (RU)' => $data['prefecture_ru'] ?? '',
                'Prefecture (EN)' => $data['prefecture_en'] ?? '',
                'Place ID' => $data['place_id'] ?? '',
                'Description (RU)' => $data['description_ru'] ?? '',
                'Description (EN)' => $data['description_en'] ?? '',
                'Website' => $data['website'] ?? '',
                'Working Hours' => $data['working_hours'] ?? '',
                'Notes' => $data['notes'] ?? ''
            ];
            
            // Категории (multi-select)
            if (!empty($data['categories_ru']) && is_array($data['categories_ru'])) {
                $airtableFields['POI Category (RU)'] = $data['categories_ru'];
            }
            if (!empty($data['categories_en']) && is_array($data['categories_en'])) {
                $airtableFields['POI Category (EN)'] = $data['categories_en'];
            }
            
            // City Location (linked record)
            if (!empty($data['city_id'])) {
                $airtableFields['City Location'] = [$data['city_id']];
            }
            
            // Regions (linked record)
            if (!empty($data['region_id'])) {
                $regionId = is_array($data['region_id']) ? $data['region_id'][0] : $data['region_id'];
                $airtableFields['Regions'] = [$regionId];
            }
            
            // Отправка в Airtable
            $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}";
            $method = 'POST';
            $payload = ['records' => [['fields' => $airtableFields]]];
            
            // Если редактирование существующей записи
            if (!empty($data['airtable_id'])) {
                $url .= '/' . $data['airtable_id'];
                $method = 'PATCH';
                $payload = ['fields' => $airtableFields];
            }
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $pat,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15
            ]);
            
            $resp = curl_exec($ch);
            $err = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($code >= 200 && $code < 300) {
                $airtableResult = json_decode($resp, true);
            } else {
                error_log("Airtable sync failed: $code - $resp");
            }
        }
    }
    
    respond(true, [
        'message' => 'POI saved successfully',
        'id' => $data['id'],
        'business_id' => $data['business_id'] ?? null,
        'airtable_synced' => $airtableResult !== null,
        'airtable_response' => $airtableResult
    ]);
    
} catch (Exception $e) {
    respond(false, ['error' => $e->getMessage()], 500);
}
?>

