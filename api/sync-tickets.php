<?php
// Синхронизация билетов с Airtable

require_once __DIR__ . '/config.features.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/lib/airtable_client.php';
require_once __DIR__ . '/filter-constants.php';

header('Content-Type: application/json; charset=utf-8');

if (!SYNC_TICKETS_ENABLED) {
    echo json_encode(['ok' => false, 'error' => 'Tickets sync disabled by feature flag']);
    exit;
}

function findPoiByBusinessId(PDO $db, string $bid) {
    $st = $db->prepare('SELECT id FROM pois WHERE business_id = :b LIMIT 1');
    $st->execute([':b' => $bid]);
    return $st->fetch(PDO::FETCH_ASSOC);
}

function upsertTicket(PDO $db, array $t) {
    // Приоритетно уникальность по airtable_record_id
    $st = $db->prepare('
        INSERT INTO tickets(airtable_record_id, poi_id, category, price, currency, note, created_at)
        VALUES(:air, :poi, :cat, :price, :cur, :note, strftime("%Y-%m-%dT%H:%M:%fZ","now"))
        ON CONFLICT(airtable_record_id) DO UPDATE SET
            poi_id=excluded.poi_id, 
            category=excluded.category, 
            price=excluded.price, 
            currency=excluded.currency, 
            note=excluded.note
    ');
    $st->execute([
        ':air'   => $t['airtable_record_id'],
        ':poi'   => $t['poi_id'],
        ':cat'   => $t['category'],
        ':price' => $t['price'],
        ':cur'   => $t['currency'],
        ':note'  => $t['note']
    ]);
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $air = new Airtable(getenv('AIRTABLE_BASE_ID') ?: 'apppwhjFN82N9zNqm');
    $table = getenv('AIRTABLE_TICKETS_TABLE') ?: 'Tickets';
    $res = $air->list($table, ['pageSize' => 100]);
    
    $ok = 0;
    $skip = 0;
    $orphans = [];
    
    foreach (($res['records'] ?? []) as $rec) {
        $fields = $rec['fields'] ?? [];
        $poiBid = $fields['POI Business ID'] ?? null;  // ожидаем бизнес-ID
        
        if (!is_string($poiBid) || !preg_match('/^POI-\d+$/', $poiBid)) {
            $skip++;
            continue;
        }
        
        $poi = findPoiByBusinessId($pdo, $poiBid);
        if (!$poi) {
            $orphans[] = $poiBid;
            continue;
        }
        
        $ticket = [
            'airtable_record_id' => $rec['id'],
            'poi_id'             => (int)$poi['id'],
            'category'           => $fields['Category'] ?? 'general',
            'price'              => (int)($fields['Price'] ?? 0),
            'currency'           => $fields['Currency'] ?? 'JPY',
            'note'               => $fields['Note'] ?? null,
        ];
        
        upsertTicket($pdo, $ticket);
        $ok++;
    }
    
    echo json_encode([
        'ok' => true,
        'synced' => $ok,
        'skipped' => $skip,
        'orphans' => array_values(array_unique($orphans))
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
