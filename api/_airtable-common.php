<?php
// /api/_airtable-common.php
function _read_pat_from_file() {
  $file = __DIR__ . '/../.secrets/airtable_pat.txt';
  if (is_readable($file)) {
    $raw = file_get_contents($file);
    return $raw === false ? '' : trim($raw);
  }
  return '';
}

function air_cfg() {
  $apiKey = trim(getenv('AIRTABLE_API_KEY') ?: '');
  if ($apiKey === '') $apiKey = _read_pat_from_file();

  // строгая валидация — чтобы поймать "кавычки" и \n
  if ($apiKey === '' || !preg_match('/^pat[0-9A-Za-z._-]{20,}$/', $apiKey)) {
    throw new Exception('Airtable token missing or malformed (expect starts with "pat" and no quotes/newlines).');
  }

  $baseId  = getenv('AIRTABLE_BASE_ID')  ?: 'apppwhjFN82N9zNqm';
  $tableId = getenv('AIRTABLE_TABLE_ID') ?: 'tblbSajWkzI8X7M4U';
  return ['api_key'=>$apiKey, 'base_id'=>$baseId, 'table_id'=>$tableId];
}

function air_call($method, $path = '', $payload = null, $query = []) {
  $cfg = air_cfg();
  $base = "https://api.airtable.com/v0/{$cfg['base_id']}";
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
