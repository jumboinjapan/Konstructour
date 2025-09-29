<?php
// Диагностика полей Airtable
header('Content-Type: application/json; charset=utf-8');

// Загружаем токен из файла
$tokenFile = __DIR__ . '/airtable.env.local';
if (file_exists($tokenFile)) {
    $lines = file($tokenFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'AIRTABLE_PAT=') === 0) {
            $token = trim(substr($line, 13));
            putenv("AIRTABLE_API_KEY=$token");
            $_ENV['AIRTABLE_API_KEY'] = $token;
            $_SERVER['AIRTABLE_API_KEY'] = $token;
            break;
        }
    }
}

require_once __DIR__.'/_airtable-common.php';

try {
  $cfg = air_cfg();
  
  // Получаем одну запись из таблицы городов
  $params = ['pageSize'=>1];
  [$code,$out,$err,$url] = air_call('GET','tblHaHc9NV0mA8bSa', null, $params);
  
  if ($code>=400) {
    echo json_encode(['error' => "Airtable error $code", 'url' => $url, 'response' => $out]);
    exit;
  }
  
  $data = json_decode($out, true);
  $record = $data['records'][0] ?? null;
  
  if (!$record) {
    echo json_encode(['error' => 'No records found']);
    exit;
  }
  
  echo json_encode([
    'record_id' => $record['id'],
    'fields' => $record['fields'],
    'field_names' => array_keys($record['fields'] ?? [])
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  
} catch (Exception $e) {
  echo json_encode(['error' => $e->getMessage()]);
}
