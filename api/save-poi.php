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
$required = ['name_ru', 'name_en'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        respond(false, ['error' => "Missing required field: $field"], 400);
    }
}

try {
    $db = new Database();
    
    // Генерируем ID если это новый POI
    if (empty($data['id'])) {
        // Получаем максимальный номер POI
        $stmt = $db->getConnection()->prepare("
            SELECT business_id FROM pois ORDER BY business_id DESC LIMIT 1
        ");
        $stmt->execute();
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
    
    // Сохраняем билеты если есть
    $ticketsSaved = 0;
    if (!empty($data['tickets']) && is_array($data['tickets'])) {
        $conn = $db->getConnection();
        
        // Удаляем старые билеты для этого POI
        $stmt = $conn->prepare("DELETE FROM tickets WHERE poi_id = ?");
        $stmt->execute([$data['id']]);
        
        // Сохраняем новые билеты
        $stmt = $conn->prepare("
            INSERT INTO tickets (poi_id, category, price, currency, note, created_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        foreach ($data['tickets'] as $ticket) {
            $stmt->execute([
                $data['id'],
                $ticket['category'] ?? '',
                $ticket['price'] ?? 0,
                $ticket['currency'] ?? 'JPY',
                $ticket['note'] ?? null
            ]);
            $ticketsSaved++;
        }
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
            // Подготовка данных для Airtable (только непустые поля)
            $airtableFields = [
                'POI ID' => $data['business_id'] ?? $data['id'],
                'POI Name (RU)' => $data['name_ru'],
                'POI Name (EN)' => $data['name_en'] ?? ''
            ];
            
            // Добавляем только непустые поля
            if (!empty($data['prefecture_ru'])) {
                $airtableFields['Prefecture (RU)'] = $data['prefecture_ru'];
            }
            if (!empty($data['prefecture_en'])) {
                $airtableFields['Prefecture (EN)'] = $data['prefecture_en'];
            }
            if (!empty($data['place_id'])) {
                $airtableFields['Place ID'] = $data['place_id'];
            }
            if (!empty($data['description_ru'])) {
                $airtableFields['Description (RU)'] = $data['description_ru'];
            }
            if (!empty($data['description_en'])) {
                $airtableFields['Description (EN)'] = $data['description_en'];
            }
            if (!empty($data['website'])) {
                $airtableFields['Website'] = $data['website'];
            }
            if (!empty($data['working_hours'])) {
                $airtableFields['Working Hours'] = $data['working_hours'];
            }
            if (!empty($data['notes'])) {
                $airtableFields['Notes'] = $data['notes'];
            }
            
            // Категории (multi-select)
            if (!empty($data['categories_ru']) && is_array($data['categories_ru'])) {
                $airtableFields['POI Category (RU)'] = $data['categories_ru'];
            }
            if (!empty($data['categories_en']) && is_array($data['categories_en'])) {
                $airtableFields['POI Category (EN)'] = $data['categories_en'];
            }
            
            // City Location (linked record) - только если это валидный Airtable record ID
            if (!empty($data['city_id']) && preg_match('/^rec[A-Za-z0-9]{14}$/', $data['city_id'])) {
                $airtableFields['City Location'] = [$data['city_id']];
            }
            
            // Regions (linked record) - только если это валидный Airtable record ID
            if (!empty($data['region_id'])) {
                $regionId = is_array($data['region_id']) ? $data['region_id'][0] : $data['region_id'];
                if (preg_match('/^rec[A-Za-z0-9]{14}$/', $regionId)) {
                    $airtableFields['Regions'] = [$regionId];
                }
            }
            
            // Если city_id или region_id не являются валидными Airtable ID, 
            // но являются локальными ID, попробуем найти соответствующие Airtable ID
            if (empty($airtableFields['City Location']) && !empty($data['city_id'])) {
                // Ищем город по локальному ID и получаем его Airtable ID
                $stmt = $db->getConnection()->prepare("SELECT id FROM cities WHERE id = ?");
                $stmt->execute([$data['city_id']]);
                $city = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($city && preg_match('/^rec[A-Za-z0-9]{14}$/', $city['id'])) {
                    $airtableFields['City Location'] = [$city['id']];
                }
            }
            
            if (empty($airtableFields['Regions']) && !empty($data['region_id'])) {
                // Ищем регион по локальному ID и получаем его Airtable ID
                $stmt = $db->getConnection()->prepare("SELECT id FROM regions WHERE id = ?");
                $stmt->execute([$data['region_id']]);
                $region = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($region && preg_match('/^rec[A-Za-z0-9]{14}$/', $region['id'])) {
                    $airtableFields['Regions'] = [$region['id']];
                }
            }
            
            // Tickets 1 - пустой массив для совместимости с Airtable
            $airtableFields['Tickets 1'] = [];
            
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
            
            // Логирование для отладки
            error_log("Airtable request: $method $url");
            error_log("Airtable payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE));
            
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
                $errorDetails = json_decode($resp, true);
                error_log("Airtable sync failed: $code - $resp");
                
                // Возвращаем подробную ошибку для отладки
                respond(false, [
                    'error' => "Airtable sync failed: $code",
                    'airtable_error' => $errorDetails,
                    'payload' => $payload,
                    'url' => $url
                ], 500);
            }
        }
    }
    
    // TODO: Синхронизация билетов с Airtable (таблица tblKOLhiHMihpWsVl)
    // Требуется уточнение структуры таблицы билетов и названий полей
    
    respond(true, [
        'message' => 'POI saved successfully',
        'id' => $data['id'],
        'business_id' => $data['business_id'] ?? null,
        'tickets_saved' => $ticketsSaved,
        'airtable_synced' => $airtableResult !== null,
        'airtable_response' => $airtableResult
    ]);
    
} catch (Exception $e) {
    respond(false, ['error' => $e->getMessage()], 500);
}
?>

