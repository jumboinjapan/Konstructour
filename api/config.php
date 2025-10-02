<?php
return array (
  'airtable' => 
  array (
    'api_key' => null,
  ),
  'airtable_registry' => 
  array (
    'baseId' => 'apppwhjFN82N9zNqm',
    'api_key' => null,
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
      'poi' => 
      array (
        'tableId' => 'tblVCmFcHRpXUT24y',
        'linkField' => 'Cities',
        'cityCodeField' => 'City',
      ),
    ),
  ),
);
