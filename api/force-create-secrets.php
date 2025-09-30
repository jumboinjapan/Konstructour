<?php
// api/force-create-secrets.php
// Принудительное создание секретов через веб-запрос

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function json_out($arr, $code = 200) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// Простая проверка - любой токен принимается
$admin_header = isset($_SERVER['HTTP_X_ADMIN_TOKEN']) ? trim($_SERVER['HTTP_X_ADMIN_TOKEN']) : '';
if (!$admin_header) {
  json_out(['ok'=>false,'reason'=>'forbidden','message'=>'X-Admin-Token header required'], 403);
}

// Конфиг путей
$dir = '/var/konstructour/secrets';
$file = $dir . '/airtable.json';

$state = [
  'php_user' => function_exists('get_current_user') ? get_current_user() : 'unknown',
  'uid' => function_exists('posix_geteuid') ? posix_geteuid() : null,
  'file_exists' => file_exists($file),
  'file_readable' => is_readable($file),
  'file_writable' => is_writable($file),
];

// Попытка 1: Создать каталог через mkdir
if (!is_dir($dir)) {
  if (!@mkdir($dir, 0700, true)) {
    // Попытка 2: Создать в /tmp как fallback
    $tmp_dir = '/tmp/konstructour_secrets';
    $tmp_file = $tmp_dir . '/airtable.json';
    
    if (!is_dir($tmp_dir)) {
      if (!@mkdir($tmp_dir, 0700, true)) {
        json_out([
          'ok'=>false,
          'reason'=>'permission_denied',
          'message'=>'Cannot create secrets directory anywhere. Need server admin access.',
          'state'=>$state,
          'suggestion'=>'Contact server administrator to create /var/konstructour/secrets'
        ], 500);
      }
    }
    
    // Используем /tmp как fallback
    $dir = $tmp_dir;
    $file = $tmp_file;
  }
}

// Создать файл секрета
$tpl = [
  'current' => ['token' => null, 'since' => null],
  'next'    => ['token' => null, 'since' => null]
];
$json = json_encode($tpl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (@file_put_contents($file, $json, LOCK_EX) === false) {
  json_out([
    'ok'=>false,
    'reason'=>'permission_denied',
    'message'=>"Cannot write secret file: $file",
    'state'=>$state
  ], 500);
}

// Установить права (если возможно)
@chmod($file, 0600);
@chmod($dir, 0700);

// Обновить состояние
$state['file_exists'] = true;
$state['file_readable'] = is_readable($file);
$state['file_writable'] = is_writable($file);

json_out([
  'ok'=>true,
  'message'=>'Secrets created successfully',
  'path'=>$file,
  'directory'=>$dir,
  'state'=>$state,
  'note'=>$dir === '/tmp/konstructour_secrets' ? 'Created in /tmp as fallback. May need to update secret-airtable.php path.' : 'Created in standard location.'
]);
?>
