<?php
// Отладка конфигурации Airtable
header('Content-Type: application/json');

$config = include 'config.php';

$result = [
    'config_exists' => file_exists('config.php'),
    'config_loaded' => is_array($config),
    'airtable_config' => $config['airtable'] ?? null,
    'airtable_registry' => $config['airtable_registry'] ?? null,
    'airtable_pat' => $config['airtable_pat'] ?? null,
    'env_airtable_pat' => getenv('AIRTABLE_PAT') ? 'SET' : 'NOT_SET',
    'env_airtable_api_key' => getenv('AIRTABLE_API_KEY') ? 'SET' : 'NOT_SET'
];

// Проверяем, какой токен будет использован
$pat = ($config['airtable']['api_key'] ?? '')
    ?: (($config['airtable']['token'] ?? '')
    ?: (($config['airtable_pat'] ?? '')
    ?: (($config['airtable_registry']['api_key'] ?? '')
    ?: (($config['airtable_registry']['token'] ?? '')
    ?: (getenv('AIRTABLE_PAT') ?: (getenv('AIRTABLE_API_KEY') ?: ''))))));

$result['final_token'] = $pat ? substr($pat, 0, 10) . '...' : 'NOT_FOUND';
$result['token_length'] = strlen($pat);
$result['is_placeholder'] = $pat === 'PLACEHOLDER_FOR_REAL_API_KEY';

echo json_encode($result, JSON_PRETTY_PRINT);
?>
