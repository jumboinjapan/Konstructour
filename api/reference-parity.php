<?php
// Отчет о расхождениях в справочниках

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
    
    // Подсчитываем активные записи
    $r = $pdo->query('SELECT COUNT(*) AS n FROM regions WHERE is_active=1')->fetch(PDO::FETCH_ASSOC);
    $c = $pdo->query('SELECT COUNT(*) AS n FROM cities  WHERE is_active=1')->fetch(PDO::FETCH_ASSOC);
    $p = $pdo->query('SELECT COUNT(*) AS n FROM pois')->fetch(PDO::FETCH_ASSOC);
    $t = $pdo->query('SELECT COUNT(*) AS n FROM tickets')->fetch(PDO::FETCH_ASSOC);
    
    // Сироты: города без region_id
    $orphanCities = $pdo->query('
        SELECT business_id, name_ru, name_en 
        FROM cities 
        WHERE is_active=1 AND (region_id IS NULL OR region_id="") 
        LIMIT 50
    ')->fetchAll(PDO::FETCH_ASSOC);
    
    // Сироты: POI без city_id
    $orphanPois = $pdo->query('
        SELECT p.business_id, p.name_ru, p.name_en, p.city_id
        FROM pois p
        LEFT JOIN cities c ON p.city_id = c.id
        WHERE c.id IS NULL
        LIMIT 50
    ')->fetchAll(PDO::FETCH_ASSOC);
    
    // Сироты: билеты без POI
    $orphanTickets = $pdo->query('
        SELECT t.id, t.category, t.price, t.poi_id
        FROM tickets t
        LEFT JOIN pois p ON t.poi_id = p.id
        WHERE p.id IS NULL
        LIMIT 50
    ')->fetchAll(PDO::FETCH_ASSOC);
    
    // Статистика по типам городов
    $cityTypes = $pdo->query('
        SELECT type, COUNT(*) as count
        FROM cities 
        WHERE is_active=1
        GROUP BY type
    ')->fetchAll(PDO::FETCH_ASSOC);
    
    // Статистика по валидности business_id
    $invalidRegions = $pdo->query('
        SELECT COUNT(*) as count
        FROM regions 
        WHERE business_id NOT LIKE "REG-%" OR business_id IS NULL
    ')->fetch(PDO::FETCH_ASSOC);
    
    $invalidCities = $pdo->query('
        SELECT COUNT(*) as count
        FROM cities 
        WHERE (business_id NOT LIKE "CTY-%" AND business_id NOT LIKE "LOC-%") OR business_id IS NULL
    ')->fetch(PDO::FETCH_ASSOC);
    
    $invalidPois = $pdo->query('
        SELECT COUNT(*) as count
        FROM pois 
        WHERE business_id NOT LIKE "POI-%" OR business_id IS NULL
    ')->fetch(PDO::FETCH_ASSOC);
    
    respond(true, [
        'counts' => [
            'regions' => (int)($r['n'] ?? 0),
            'cities' => (int)($c['n'] ?? 0),
            'pois' => (int)($p['n'] ?? 0),
            'tickets' => (int)($t['n'] ?? 0)
        ],
        'orphans' => [
            'city_without_region' => $orphanCities,
            'poi_without_city' => $orphanPois,
            'ticket_without_poi' => $orphanTickets
        ],
        'city_types' => $cityTypes,
        'invalid_business_ids' => [
            'regions' => (int)($invalidRegions['count'] ?? 0),
            'cities' => (int)($invalidCities['count'] ?? 0),
            'pois' => (int)($invalidPois['count'] ?? 0)
        ],
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    respond(false, ['error' => $e->getMessage()], 500);
}
?>
