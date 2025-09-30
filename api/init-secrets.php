<?php
// api/init-secrets.php
// Идемпотентная инициализация секрета Airtable.
// Требует заголовок X-Admin-Token. Токены не возвращает.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function json_out($arr, $code = 200) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// 1) Авторизация (тот же механизм, что в config-store-secure.php)
$admin_header = isset($_SERVER['HTTP_X_ADMIN_TOKEN']) ? trim($_SERVER['HTTP_X_ADMIN_TOKEN']) : '';
$admin_hash_env = getenv('KT_ADMIN_TOKEN_HASH') ?: ''; // рекомендуем хранить bcrypt/argon2 хеш
if ($admin_hash_env) {
  if (!$admin_header || password_verify($admin_header, $admin_hash_env) !== true) {
    json_out(['ok'=>false,'reason'=>'forbidden','message'=>'Invalid admin token'], 403);
  }
} else {
  // Фолбэк: если хеш не настроен, запрещаем запись
  json_out(['ok'=>false,'reason'=>'forbidden','message'=>'Admin hash not configured'], 403);
}

// 2) Конфиг путей
$dir = '/var/konstructour/secrets';
$file = $dir . '/airtable.json';

$state = [
  'php_user' => function_exists('get_current_user') ? get_current_user() : 'unknown',
  'uid' => function_exists('posix_geteuid') ? posix_geteuid() : null,
  'file_exists' => file_exists($file),
  'file_readable' => is_readable($file),
  'file_writable' => is_writable($file),
];

// 3) Пытаемся создать каталог
if (!is_dir($dir)) {
  if (!@mkdir($dir, 0700, true)) {
    json_out([
      'ok'=>false,
      'reason'=>'permission_denied',
      'message'=>"Cannot create dir: $dir (need one-time bootstrap via SSH)",
      'state'=>$state
    ], 500);
  }
  @chmod($dir, 0700);
}

// 4) Пытаемся создать файл, если его нет
if (!file_exists($file)) {
  $tpl = [
    'current' => ['token' => null, 'since' => null],
    'next'    => ['token' => null, 'since' => null]
  ];
  $json = json_encode($tpl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if (@file_put_contents($file, $json, LOCK_EX) === false) {
    json_out([
      'ok'=>false,
      'reason'=>'permission_denied',
      'message'=>"Cannot write secret file: $file (need chmod/chown via SSH)",
      'state'=>$state
    ], 500);
  }
  @chmod($file, 0600);
}

// 5) Валидируем содержимое
$raw = @file_get_contents($file);
$decoded = json_decode($raw, true);
if (!is_array($decoded) || !array_key_exists('current',$decoded) || !array_key_exists('next',$decoded)) {
  json_out([
    'ok'=>false,
    'reason'=>'bad_format',
    'message'=>'Secret file exists but has invalid JSON structure',
    'state'=>$state
  ], 500);
}

// 6) Готово
$state['file_exists'] = true;
$state['file_readable'] = is_readable($file);
$state['file_writable'] = is_writable($file);

json_out([
  'ok'=>true,
  'message'=>'Secret initialized (or already present)',
  'path'=>$file,
  'state'=>$state
]);
?>