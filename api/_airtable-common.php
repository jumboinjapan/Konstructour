<?php
// /api/_airtable-common.php

// Загружаем токен из файла
$envFile = __DIR__ . '/airtable.env.local';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'AIRTABLE_PAT=') === 0) {
            $token = substr($line, 12);
            putenv("AIRTABLE_PAT=$token");
            $_ENV['AIRTABLE_PAT'] = $token;
            $_SERVER['AIRTABLE_PAT'] = $token;
            break;
        }
    }
}

function air_cfg() {
  $cfg = [
    'api_key'  => getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_API_KEY'),
    'base_id'  => getenv('AIRTABLE_BASE_ID') ?: 'apppwhjFN82N9zNqm',
    'table_id' => getenv('AIRTABLE_TABLE_ID') ?: 'tblbSajWkzI8X7M4U',
  ];
  if (!$cfg['api_key'])  throw new Exception('AIRTABLE_API_KEY is missing');
  if (!$cfg['base_id'])  throw new Exception('AIRTABLE_BASE_ID is missing');
  if (!$cfg['table_id']) throw new Exception('AIRTABLE_TABLE_ID is missing');
  return $cfg;
}

function air_call($method, $path, $payload=null, $query=[]) {
  $cfg = air_cfg();
  $url = "https://api.airtable.com/v0/{$cfg['base_id']}/{$cfg['table_id']}";
  if ($path && $path !== '/') $url .= '/'.ltrim($path,'/');
  if ($query) $url .= (str_contains($url,'?')?'&':'?') . http_build_query($query);

  $ch = curl_init($url);
  $headers = ["Authorization: Bearer {$cfg['api_key']}"];
  if (!is_null($payload)) $headers[] = "Content-Type: application/json";

  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => strtoupper($method),
    CURLOPT_HTTPHEADER    => $headers,
    CURLOPT_RETURNTRANSFER=> true,
  ]);
  if (!is_null($payload)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  }
  $out  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  return [$code, $out, $err, $url];
}
?>
