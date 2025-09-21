<?php
// Copy to config.php and fill in real keys. This file is NOT loaded if config.php exists.
return [
  'openai' => [
    'api_key' => 'sk-REPLACE',
    'model'   => 'gpt-4o-mini'
  ],
  'airtable' => [
    'api_key' => 'pat-REPLACE',
    'base_id' => 'appREPLACE',
    'table'   => 'Tours'
  ],
  'gsheets' => [
    'api_key'        => 'AIzaREPLACE',
    'spreadsheet_id' => '1REPLACE'
  ],
  'gmaps' => [
    'api_key' => 'AIzaREPLACE'
  ],
  'recaptcha' => [
    'site_key' => 'REPLACE',
    'secret'   => 'REPLACE'
  ],
  'brilliantdb' => [
    'api_key'   => 'REPLACE',
    'base_url'  => 'https://api.brilliantdb.com/v1/',
    'collection'=> 'tours'
  ],
];


