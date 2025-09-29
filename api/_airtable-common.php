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
      $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      foreach ($lines as $line) {
        if (strpos($line, 'AIRTABLE_PAT=') === 0) {
          $val = trim(substr($line, 13)); // 13 символов для 'AIRTABLE_PAT='
          if ($val !== '') return $val;
        }
      }
    }
  }
  return '';
}

function air_cfg() {
  $apiKey = trim(getenv('AIRTABLE_API_KEY') ?: '');
  // если переменная окружения отсутствует ИЛИ выглядит некорректно — пробуем файл
  if ($apiKey === '' || !preg_match('/^pat[^\s]{20,}$/', $apiKey)) {
    $apiKey = _read_pat_from_file();
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
  ]);
  if (!is_null($payload)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  }

  $out  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  return [$code, $out, $err, $url, substr($cfg['api_key'],0,3), substr($cfg['api_key'],-6)];
}
?>
