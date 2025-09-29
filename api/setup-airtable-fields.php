<?php
// /api/setup-airtable-fields.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/_airtable-common.php';

try {
  $cfg = air_cfg();

  // 1) Пингуем метаданные — не трогает схему, показывает 401/403 честно
  [$code,$out,$err,$url,$h,$t] = air_call('GET', 'meta/bases', null, ['limit'=>1]);
  $json = json_decode($out, true);

  if ($code === 401) {
    http_response_code(500);
    echo json_encode([
      'ok'=>false,
      'error'=>'401 Authentication required (PAT rejected)',
      'hint'=>'Чаще всего: токен скопирован с кавычками/пробелом, либо создан в другом аккаунте без доступа.',
      'debug'=>[
        'request_url'=>$url,
        'token_head'=>$h,       // должно быть "pat"
        'token_tail'=>$t,       // последние 6 символов, для сверки
      ],
      'airtable_response'=>$json ?: $out
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }
  
  // Если meta недоступно (404), продолжаем проверкой таблицы
  if ($code >= 400 && $code !== 404) {
    http_response_code(500);
    echo json_encode([
      'ok'=>false,
      'error'=>"Airtable error $code on meta",
      'airtable_response'=>$json ?: $out
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 2) Проверка доступа к таблице (list records)
  [$c2,$o2,$e2,$u2,$h2,$t2] = air_call('GET', '', null, ['pageSize'=>1]);
  if ($c2 >= 400) {
    http_response_code(500);
    echo json_encode([
      'ok'=>false,
      'error'=>"Airtable error $c2 on list records (table)",
      'request_url'=>$u2,
      'token_head'=>$h2,
      'token_tail'=>$t2,
      'details'=>json_decode($o2,true) ?: $o2
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo json_encode([
    'ok'=>true,
    'message'=>'Доступ к базе и таблице подтверждён. Поля можно настраивать.'
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
