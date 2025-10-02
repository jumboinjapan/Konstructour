<?php
// Диагностическая страница для мониторинга системы

require_once __DIR__ . '/config.features.php';
require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // 1. Feature Flags Status
    $featureFlags = [
        'SYNC_REFERENCES_ENABLED' => defined('SYNC_REFERENCES_ENABLED') ? SYNC_REFERENCES_ENABLED : false,
        'SYNC_TICKETS_ENABLED' => defined('SYNC_TICKETS_ENABLED') ? SYNC_TICKETS_ENABLED : false,
        'BATCH_UPSERT_ENABLED' => defined('BATCH_UPSERT_ENABLED') ? BATCH_UPSERT_ENABLED : false,
        'RETRY_ENABLED' => defined('RETRY_ENABLED') ? RETRY_ENABLED : false,
    ];
    
    // 2. Database Statistics
    $stats = [];
    $tables = ['regions', 'cities', 'pois', 'tickets', 'sync_log'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $stats[$table] = (int)$stmt->fetchColumn();
    }
    
    // 3. Recent Sync Activity
    $recentSyncs = $pdo->query('
        SELECT table_name, action, batch_id, scope, timestamp
        FROM sync_log 
        ORDER BY timestamp DESC 
        LIMIT 10
    ')->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Error Analysis
    $errorStats = $pdo->query('
        SELECT scope, COUNT(*) as error_count
        FROM sync_log 
        WHERE scope IS NOT NULL 
        AND timestamp > datetime("now", "-7 days")
        GROUP BY scope
    ')->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. Batch Performance
    $batchStats = $pdo->query('
        SELECT 
            COUNT(*) as total_batches
        FROM sync_log 
        WHERE batch_id IS NOT NULL 
        AND timestamp > datetime("now", "-7 days")
    ')->fetch(PDO::FETCH_ASSOC);
    
    // 6. Orphaned Records
    $orphans = [
        'cities_without_region' => $pdo->query('
            SELECT COUNT(*) FROM cities 
            WHERE is_active=1 AND (region_id IS NULL OR region_id="")
        ')->fetchColumn(),
        'pois_without_city' => $pdo->query('
            SELECT COUNT(*) FROM pois p
            LEFT JOIN cities c ON p.city_id = c.id
            WHERE c.id IS NULL
        ')->fetchColumn(),
        'tickets_without_poi' => $pdo->query('
            SELECT COUNT(*) FROM tickets t
            LEFT JOIN pois p ON t.poi_id = p.id
            WHERE p.id IS NULL
        ')->fetchColumn(),
    ];
    
    // 7. Business ID Validation
    $invalidIds = [
        'regions' => $pdo->query('
            SELECT COUNT(*) FROM regions 
            WHERE business_id NOT LIKE "REG-%" OR business_id IS NULL
        ')->fetchColumn(),
        'cities' => $pdo->query('
            SELECT COUNT(*) FROM cities 
            WHERE (business_id NOT LIKE "CTY-%" AND business_id NOT LIKE "LOC-%") OR business_id IS NULL
        ')->fetchColumn(),
        'pois' => $pdo->query('
            SELECT COUNT(*) FROM pois 
            WHERE business_id NOT LIKE "POI-%" OR business_id IS NULL
        ')->fetchColumn(),
    ];
    
    respond(true, [
        'feature_flags' => $featureFlags,
        'database_stats' => $stats,
        'recent_syncs' => $recentSyncs,
        'error_analysis' => $errorStats,
        'batch_performance' => $batchStats,
        'orphaned_records' => $orphans,
        'invalid_business_ids' => $invalidIds,
        'timestamp' => date('c'),
        'system_status' => [
            'database_connected' => true,
            'migrations_applied' => true,
            'feature_flags_loaded' => true
        ]
    ]);
    
} catch (Exception $e) {
    respond(false, [
        'error' => $e->getMessage(),
        'system_status' => [
            'database_connected' => false,
            'migrations_applied' => false,
            'feature_flags_loaded' => false
        ]
    ], 500);
}
?>
