<?php
// Debug token loading
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'env_pat' => getenv('AIRTABLE_PAT'),
    'env_api_key' => getenv('AIRTABLE_API_KEY'),
    'config_exists' => file_exists(__DIR__ . '/config.php'),
    'config_content' => file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : null,
    'airtable_common_exists' => file_exists(__DIR__ . '/_airtable-common.php'),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
