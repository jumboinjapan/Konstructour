<?php
// /api/_airtable-common.php
function _possible_pat_paths(): array {
  $paths = [];
  // Persistent path in user HOME
  $home = getenv('HOME');
  if (!$home) {
    // try to derive home from project path (api/ → public_html/konstructour)
    $home = dirname(__DIR__, 3); // usually /home/<user>
  }
  if ($home) {
    $paths[] = rtrim($home, '/').'/.konstructour/airtable_pat.txt';
  }
  // Project-local secrets (can be preserved by rsync exclude)
  $paths[] = __DIR__ . '/../.secrets/airtable_pat.txt';
  // Legacy env file fallback
  $paths[] = __DIR__ . '/airtable.env.local';
  return $paths;
}

function _read_pat_from_file() {
  foreach (_possible_pat_paths() as $file) {
    if (is_readable($file)) {
      $content = file_get_contents($file);
      if ($content !== false) {
        // Используем regex для более надежного извлечения токена
        if (preg_match('/AIRTABLE_PAT=([^\r\n]+)/', $content, $matches)) {
          $val = trim($matches[1]);
          if ($val !== '') return $val;
        }
      }
    }
  }
  return '';
}

function air_cfg() {
  $apiKey = trim(getenv('AIRTABLE_PAT') ?: '');
  // если переменная окружения отсутствует ИЛИ выглядит некорректно — пробуем файл
  if ($apiKey === '' || !preg_match('/^pat[^\s]{20,}$/', $apiKey)) {
    $apiKey = _read_pat_from_file();
  }
  
  // Если все еще нет токена, пробуем загрузить из config.php
  if ($apiKey === '' || !preg_match('/^pat[^\s]{20,}$/', $apiKey)) {
    $configFile = __DIR__ . '/config.php';
    if (file_exists($configFile)) {
      $config = require $configFile;
      if (is_array($config) && isset($config['airtable_registry']['api_key'])) {
        $apiKey = $config['airtable_registry']['api_key'];
      }
    }
  }


  // строгая валидация: начинаем с pat и минимум 20 непробельных символов далее
  if ($apiKey === '' || !preg_match('/^pat[^\s]{20,}$/', $apiKey)) {
    throw new Exception('Airtable token missing or malformed (expect starts with "pat" and no quotes/newlines).');
  }

  $baseId  = getenv('AIRTABLE_BASE_ID')  ?: 'apppwhjFN82N9zNqm';
  $tableId = getenv('AIRTABLE_TABLE_ID') ?: 'tblbSajWkzI8X7M4U';
  return ['api_key'=>$apiKey, 'base_id'=>$baseId, 'table_id'=>$tableId];
}

function air_call($method, $path = '', $payload = null, $query = []) {
  $cfg = air_cfg();
  // meta/* эндпоинты живут на корне /v0 без baseId
  $isMeta = is_string($path) && str_starts_with($path, 'meta/');
  $base = $isMeta ? 'https://api.airtable.com/v0' : "https://api.airtable.com/v0/{$cfg['base_id']}";
  $url  = rtrim($base, '/');
  if ($path === '' || $path === null) {
    $url .= '/' . $cfg['table_id'];
  } else {
    $url .= '/' . ltrim($path, '/');
  }
  if ($query) $url .= (str_contains($url,'?') ? '&' : '?') . http_build_query($query);

  $headers = [
    'Authorization: Bearer ' . $cfg['api_key'], // ВАЖНО: пробел после Bearer
    'Content-Type: application/json'
  ];

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => strtoupper($method),
    CURLOPT_HTTPHEADER    => $headers,
    CURLOPT_RETURNTRANSFER=> true,
    CURLOPT_TIMEOUT       => 30,
    CURLOPT_SSL_VERIFYPEER => true, // Включить проверку SSL
    CURLOPT_FOLLOWLOCATION => true, // Следовать редиректам
    CURLOPT_USERAGENT     => 'Konstructour/1.0', // Добавить User-Agent
  ]);
  if (!is_null($payload)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  }

  $out  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  $info = curl_getinfo($ch);
  curl_close($ch);

  // Логирование для отладки (только в случае ошибки)
  if ($out === false || $err || $code >= 400) {
    error_log("Airtable API Error - URL: $url, Code: $code, Error: $err, Output: " . substr($out, 0, 500));
  }

  return [$code, $out, $err, $url, substr($cfg['api_key'],0,3), substr($cfg['api_key'],-6)];
}
?>
