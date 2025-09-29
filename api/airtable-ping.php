<?php
// /api/airtable-ping.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/_airtable-common.php';

try {
  $cfg = air_cfg();
  // пробуем прочитать 1 запись (или схему таблицы через list)
  [$code,$out,$err,$url] = air_call('GET','', null, ['pageSize'=>1]);
  $json = json_decode($out, true);

  if ($code>=400) {
    http_response_code(500);
    echo json_encode([
      'ok'=>false,
      'where'=>'list records',
      'code'=>$code,
      'url'=>$url,
      'base_id'=>$cfg['base_id'],
      'table_id'=>$cfg['table_id'],
      'details'=>$json ?: $out,
      'hint'=>'403 обычно значит: токен не имеет доступа к ЭТОЙ базе/таблице, либо Base/Table ID не совпадает.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo json_encode([
    'ok'=>true,
    'message'=>'Airtable reachable',
    'url'=>$url,
    'base_id'=>$cfg['base_id'],
    'table_id'=>$cfg['table_id'],
    'sample'=> $json['records'] ?? [],
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
