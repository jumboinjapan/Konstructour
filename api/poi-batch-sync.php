<?php
// Батч-синхронизация POI с Airtable

require_once __DIR__ . '/config.features.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/lib/airtable_client.php';
require_once __DIR__ . '/filter-constants.php';

header('Content-Type: application/json; charset=utf-8');

function poi_batch_upsert_to_airtable(array $pois): array {
    require_once __DIR__ . '/lib/airtable_client.php';
    $air = new Airtable(getenv('AIRTABLE_BASE_ID') ?: 'apppwhjFN82N9zNqm');

    // Подготовка рекордов Airtable
    $records = [];
    foreach ($pois as $p) {
        // Валидация business_id
        if (!preg_match('/^POI-\d+$/', $p['business_id'] ?? '')) {
            continue;
        }
        
        $records[] = [
            'idempotency_key' => hash('sha1', json_encode([$p['business_id'], $p['updated_at'] ?? ''])),
            'fields' => [
                'Business ID' => $p['business_id'],
                'Name RU'     => $p['name_ru'] ?? null,
                'Name EN'     => $p['name_en'] ?? null,
                'City'        => $p['city_business_id'] ?? null,
                'Region'      => $p['region_business_id'] ?? null,
                'Published'   => (bool)($p['published'] ?? 0),
                'Latitude'    => isset($p['latitude']) ? (float)$p['latitude'] : null,
                'Longitude'   => isset($p['longitude']) ? (float)$p['longitude'] : null,
            ]
        ];
    }
    
    return $air->batchUpsert('POI', $records);
}

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Получаем POI для синхронизации (например, измененные за последние 24 часа)
    $stmt = $pdo->prepare('
        SELECT p.*, c.business_id as city_business_id, r.business_id as region_business_id
        FROM pois p
        LEFT JOIN cities c ON p.city_id = c.id
        LEFT JOIN regions r ON p.region_id = r.id
        WHERE p.updated_at > datetime("now", "-1 day")
        ORDER BY p.updated_at DESC
        LIMIT 100
    ');
    $stmt->execute();
    $pois = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pois)) {
        respond(true, [
            'message' => 'No POIs to sync',
            'synced' => 0,
            'failed' => 0
        ]);
    }
    
    // Выполняем батч-синхронизацию
    $result = poi_batch_upsert_to_airtable($pois);
    
    // Логируем результат
    $logStmt = $pdo->prepare('
        INSERT INTO sync_log (table_name, action, records_count, success_count, error_count, batch_id, scope, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, datetime("now"))
    ');
    $logStmt->execute([
        'pois',
        'batch_sync',
        count($pois),
        $result['ok'],
        count($result['fail']),
        uniqid('batch_', true),
        'poi_batch_sync'
    ]);
    
    respond(true, [
        'message' => 'POI batch sync completed',
        'total' => count($pois),
        'synced' => $result['ok'],
        'failed' => count($result['fail']),
        'failed_ids' => $result['fail']
    ]);
    
} catch (Exception $e) {
    respond(false, ['error' => $e->getMessage()], 500);
}
?>
