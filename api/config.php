<?php
// Загружаем токен Airtable из файла
$tokenFile = __DIR__ . '/airtable.env.local';
if (file_exists($tokenFile)) {
    $lines = file($tokenFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

return array (
  'airtable_registry' => 
  array (
    'baseId' => 'apppwhjFN82N9zNqm',
    'api_key' => 'PLACEHOLDER_FOR_REAL_API_KEY',
    'tables' => 
    array (
      'region' => 
      array (
        'tableId' => 'tblbSajWkzI8X7M4U',
      ),
      'city' => 
      array (
        'tableId' => 'tblHaHc9NV0mA8bSa',
        'linkField' => 'Regions',
        'regionCodeField' => 'Region',
      ),
    ),
  ),
);
