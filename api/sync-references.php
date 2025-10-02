<?php
// Синхронизация справочников (регионы/города) с Airtable

require_once __DIR__ . '/config.features.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/lib/airtable_client.php';
require_once __DIR__ . '/filter-constants.php';

header('Content-Type: application/json; charset=utf-8');

if (!SYNC_REFERENCES_ENABLED) {
    echo json_encode(['ok' => false, 'error' => 'References sync disabled by feature flag']);
    exit;
}

function upsertRegion(PDO $db, array $r) {
    $st = $db->prepare('
        INSERT INTO regions (id, name_ru, name_en, business_id, created_at, updated_at, is_active)
        VALUES(:id, :ru, :en, :bid, strftime("%Y-%m-%dT%H:%M:%fZ","now"), strftime("%Y-%m-%dT%H:%M:%fZ","now"), 1)
        ON CONFLICT(id) DO UPDATE SET 
            name_ru=excluded.name_ru, 
            name_en=excluded.name_en, 
            business_id=excluded.business_id, 
            updated_at=strftime("%Y-%m-%dT%H:%M:%fZ","now"), 
            is_active=1
    ');
    $st->execute($r);
}

function upsertCity(PDO $db, array $c) {
    $st = $db->prepare('
        INSERT INTO cities (id, name_ru, name_en, business_id, type, region_id, created_at, updated_at, is_active)
        VALUES(:id, :ru, :en, :bid, :type, :region_id, strftime("%Y-%m-%dT%H:%M:%fZ","now"), strftime("%Y-%m-%dT%H:%M:%fZ","now"), 1)
        ON CONFLICT(id) DO UPDATE SET 
            name_ru=excluded.name_ru, 
            name_en=excluded.name_en, 
            business_id=excluded.business_id, 
            type=excluded.type, 
            region_id=excluded.region_id, 
            updated_at=strftime("%Y-%m-%dT%H:%M:%fZ","now"), 
            is_active=1
    ');
    $st->execute($c);
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $air = new Airtable(getenv('AIRTABLE_BASE_ID') ?: 'apppwhjFN82N9zNqm');

    $rlist = $air->list('Regions', ['pageSize' => 100]);
    $clist = $air->list('Cities',  ['pageSize' => 200]);

    $r_cnt = 0;
    $c_cnt = 0;
    $warn = [];
    
    // Синхронизируем регионы
    foreach (($rlist['records'] ?? []) as $rec) {
        $bid = $rec['fields']['Business ID'] ?? null;
        if (!is_string($bid) || !preg_match('/^REG-\d+$/', $bid)) {
            $warn[] = ['table' => 'regions', 'id' => $rec['id'], 'bad' => $bid];
            continue;
        }
        
        upsertRegion($pdo, [
            ':id'  => $rec['id'], // локальное primary id можно держать как airtable id либо свой UUID
            ':ru'  => $rec['fields']['Name RU'] ?? null,
            ':en'  => $rec['fields']['Name EN'] ?? null,
            ':bid' => $bid
        ]);
        $r_cnt++;
    }

    // Синхронизируем города
    foreach (($clist['records'] ?? []) as $rec) {
        $bid = $rec['fields']['Business ID'] ?? null;
        if (!is_string($bid) || !preg_match('/^(CTY|LOC)-\d+$/', $bid)) {
            $warn[] = ['table' => 'cities', 'id' => $rec['id'], 'bad' => $bid];
            continue;
        }
        
        $type = str_starts_with($bid, 'CTY-') ? 'city' : 'location';
        $regBid = $rec['fields']['Region Business ID'] ?? null; // ожидаем бизнес id региона
        $reg = null;
        
        if (is_string($regBid) && preg_match('/^REG-\d+$/', $regBid)) {
            $st = $pdo->prepare('SELECT id FROM regions WHERE business_id=:b LIMIT 1');
            $st->execute([':b' => $regBid]);
            $reg = $st->fetchColumn();
        }
        
        upsertCity($pdo, [
            ':id'        => $rec['id'],
            ':ru'        => $rec['fields']['Name RU'] ?? null,
            ':en'        => $rec['fields']['Name EN'] ?? null,
            ':bid'       => $bid,
            ':type'      => $type,
            ':region_id' => $reg
        ]);
        $c_cnt++;
    }

    echo json_encode([
        'ok' => true,
        'regions' => $r_cnt,
        'cities' => $c_cnt,
        'warnings' => $warn
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
