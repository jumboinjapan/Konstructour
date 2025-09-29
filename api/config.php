<?php
// Загружаем переменные окружения
require_once __DIR__ . '/load-env.php';

// Получаем токен Airtable из переменных окружения
$airtablePat = getenv('AIRTABLE_PAT') ?: 'PLACEHOLDER_FOR_REAL_API_KEY';

return array (
  'airtable_registry' => 
  array (
    'baseId' => 'apppwhjFN82N9zNqm',
    'api_key' => $airtablePat,
  ),
  'airtable' => 
  array (
    'baseId' => 'apppwhjFN82N9zNqm',
    'api_key' => $airtablePat,
  ),
);