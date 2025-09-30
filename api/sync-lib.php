<?php
// api/sync-lib.php
require_once __DIR__ . '/db.php';

function saveRegion(PDO $db, array $r): void {
    // Гарантия типов
    $id  = (string)$r['id'];
    $nr  = (string)($r['name_ru'] ?? '');
    $ne  = (string)($r['name_en'] ?? '');
    $bid = (string)($r['business_id'] ?? '');

    $stmt = $db->prepare("
        INSERT OR REPLACE INTO regions (id, name_ru, name_en, business_id, created_at, updated_at)
        VALUES (:id, :nr, :ne, :bid, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([':id'=>$id, ':nr'=>$nr, ':ne'=>$ne, ':bid'=>$bid]);
}

function saveCity(PDO $db, array $c): void {
    $id   = (string)$c['id'];
    $nr   = (string)($c['name_ru'] ?? '');
    $ne   = (string)($c['name_en'] ?? '');
    $bid  = (string)($c['business_id'] ?? '');
    $type = (string)($c['type'] ?? 'city');
    $rid  = (string)$c['region_id'];

    // Доп.проверка родителя (даст внятную ошибку в лог)
    $chk = $db->prepare("SELECT 1 FROM regions WHERE id=?");
    $chk->execute([$rid]);
    if (!$chk->fetchColumn()) {
        throw new RuntimeException("City parent region not found: $rid (for city $id)");
    }

    $stmt = $db->prepare("
        INSERT OR REPLACE INTO cities (id, name_ru, name_en, business_id, type, region_id, created_at, updated_at)
        VALUES (:id, :nr, :ne, :bid, :type, :rid, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([':id'=>$id, ':nr'=>$nr, ':ne'=>$ne, ':bid'=>$bid, ':type'=>$type, ':rid'=>$rid]);
}

function savePoi(PDO $db, array $p): void {
    $id   = (string)$p['id'];
    $nr   = (string)($p['name_ru'] ?? '');
    $ne   = (string)($p['name_en'] ?? '');
    $cat  = (string)($p['category'] ?? '');
    $pl   = (string)($p['place_id'] ?? '');
    $pub  = (int)!!($p['published'] ?? 0);
    $bid  = (string)($p['business_id'] ?? '');
    $cid  = (string)($p['city_id'] ?? '');
    $rid  = (string)($p['region_id'] ?? '');
    $desc = (string)($p['description'] ?? '');
    $lat  = isset($p['latitude'])  ? (float)$p['latitude']  : null;
    $lng  = isset($p['longitude']) ? (float)$p['longitude'] : null;

    // Проверим родителей (даст читаемый лог, не "тихий" откат)
    if ($cid) {
        $chkC = $db->prepare("SELECT 1 FROM cities WHERE id=?");
        $chkC->execute([$cid]);
        if (!$chkC->fetchColumn()) {
            throw new RuntimeException("POI city not found: $cid (for poi $id)");
        }
    }
    if ($rid) {
        $chkR = $db->prepare("SELECT 1 FROM regions WHERE id=?");
        $chkR->execute([$rid]);
        if (!$chkR->fetchColumn()) {
            throw new RuntimeException("POI region not found: $rid (for poi $id)");
        }
    }

    $stmt = $db->prepare("
        INSERT OR REPLACE INTO pois (id, name_ru, name_en, category, place_id, published, business_id, city_id, region_id, description, latitude, longitude, created_at, updated_at)
        VALUES (:id, :nr, :ne, :cat, :pl, :pub, :bid, :cid, :rid, :desc, :lat, :lng, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    $stmt->bindValue(':id',$id);
    $stmt->bindValue(':nr',$nr);
    $stmt->bindValue(':ne',$ne);
    $stmt->bindValue(':cat',$cat);
    $stmt->bindValue(':pl',$pl);
    $stmt->bindValue(':pub',$pub, PDO::PARAM_INT);
    $stmt->bindValue(':bid',$bid);
    $stmt->bindValue(':cid',$cid ?: null, $cid ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':rid',$rid ?: null, $rid ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':desc',$desc);
    $stmt->bindValue(':lat',$lat, $lat===null?PDO::PARAM_NULL:PDO::PARAM_STR);
    $stmt->bindValue(':lng',$lng, $lng===null?PDO::PARAM_NULL:PDO::PARAM_STR);
    $stmt->execute();
}
