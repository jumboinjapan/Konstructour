<?php
// /api/regions-create.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/_airtable-common.php';

// ПОЛЯ — можно оставить имена, позже легко перейти на Field ID
$F_ID = getenv('AIR_F_ID') ?: 'Идентификатор';
$F_RU = getenv('AIR_F_RU') ?: 'Название (RU)';
$F_EN = getenv('AIR_F_EN') ?: 'Название (EN)';

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
$name_ru = trim((string)($body['name_ru'] ?? ''));
$name_en = trim((string)($body['name_en'] ?? ''));

if ($name_ru === '' || $name_en === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'name_ru and name_en are required'], JSON_UNESCAPED_UNICODE);
  exit;
}

function fmt_id($n){ return sprintf('REG-%04d', $n); }

try {
  // 1) найти максимальный REG-XXXX (с пагинацией)
  $max=0; $offset=null;
  do {
    [$code,$out,$err,$url] = air_call('GET','', null, ['pageSize'=>100,'offset'=>$offset]);
    if ($code>=400) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>"Airtable $code (list)",'url'=>$url,'details'=>json_decode($out,true)?:$out], JSON_UNESCAPED_UNICODE); exit; }
    $j = json_decode($out,true);
    foreach (($j['records']??[]) as $r){
      $val = strval($r['fields'][$F_ID] ?? '');
      if (preg_match('/^REG-(\d{4})$/', $val, $m)) $max = max($max, (int)$m[1]);
    }
    $offset = $j['offset'] ?? null;
  } while ($offset);

  $next = fmt_id($max+1);

  // 2) создать запись
  $payload = ['fields'=>[$F_ID=>$next, $F_RU=>$name_ru, $F_EN=>$name_en]];
  [$code,$out,$err,$url] = air_call('POST','', $payload);
  if ($code>=300) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>"Airtable $code (create)",'url'=>$url,'request'=>$payload,'details'=>json_decode($out,true)?:$out], JSON_UNESCAPED_UNICODE); exit;
  }
  $created = json_decode($out,true);
  echo json_encode(['ok'=>true,'record_id'=>$created['id']??null,'region_id'=>$next], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>