<?php
return array (
  'airtable_registry' => 
  array (
    'baseId' => 'PLACEHOLDER_BASE_ID',
    'api_key' => 'PLACEHOLDER_FOR_REAL_API_KEY',
    'tables' => 
    array (
      'region' => 
      array (
        'tableId' => 'PLACEHOLDER_REGION_TABLE_ID',
      ),
      'city' => 
      array (
        'tableId' => 'PLACEHOLDER_CITY_TABLE_ID',
        'linkField' => 'Regions',
        'regionCodeField' => 'Region',
      ),
    ),
  ),
);
