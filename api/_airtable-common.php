<?php
// /api/_airtable-common.php

function air_cfg() {
  // 1) пробуем ENV
  $apiKey = getenv('AIRTABLE_API_KEY');

  // 2) если пусто — читаем из файла, куда сохраняет /api/setup-token.php
  if (!$apiKey) {
    $secretFile = __DIR__ . '/airtable.env.local';
    $debug = [
      'file_exists' => file_exists($secretFile),
      'is_readable' => is_readable($secretFile),
      'realpath' => realpath($secretFile),
      'file_size' => file_exists($secretFile) ? filesize($secretFile) : 0
    ];
    
    if (is_readable($secretFile)) {
      $lines = file($secretFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      foreach ($lines as $line) {
        if (strpos($line, 'AIRTABLE_PAT=') === 0) {
          $apiKey = trim(substr($line, 12));
          break;
        }
      }
    }
    
    if (!$apiKey) {
      throw new Exception('Airtable token not found. Debug: ' . json_encode($debug));
    }
  }

  $baseId  = getenv('AIRTABLE_BASE_ID')  ?: 'apppwhjFN82N9zNqm';
  $tableId = getenv('AIRTABLE_TABLE_ID') ?: 'tblbSajWkzI8X7M4U';

  return ['api_key'=>$apiKey, 'base_id'=>$baseId, 'table_id'=>$tableId];
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
