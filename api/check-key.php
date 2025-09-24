<?php
// Простая проверка наличия API ключа
header('Content-Type: application/json; charset=utf-8');

$sources = [
    'env AIRTABLE_API_KEY' => getenv('AIRTABLE_API_KEY'),
    'env AIRTABLE_PAT' => getenv('AIRTABLE_PAT'), 
    'env AIRTABLE_TOKEN' => getenv('AIRTABLE_TOKEN')
];

$cfg = [];
if (file_exists(__DIR__.'/config.php')) {
    $cfg = require __DIR__.'/config.php';
    if (is_array($cfg)) {
        $sources['config airtable.api_key'] = $cfg['airtable']['api_key'] ?? null;
        $sources['config airtable.token'] = $cfg['airtable']['token'] ?? null;
        $sources['config airtable_pat'] = $cfg['airtable_pat'] ?? null;
        $sources['config airtable_registry.api_key'] = $cfg['airtable_registry']['api_key'] ?? null;
    }
}

$result = [];
foreach ($sources as $source => $value) {
    $result[$source] = $value ? '✅ НАЙДЕН ('.strlen($value).' символов)' : '❌ НЕ НАЙДЕН';
}

// Определяем финальный ключ
$finalKey = '';
foreach ($sources as $source => $value) {
    if ($value && $value !== 'PLACEHOLDER_FOR_REAL_API_KEY') {
        $finalKey = $value;
        break;
    }
}

echo json_encode([
    'ok' => !empty($finalKey),
    'final_key_found' => !empty($finalKey),
    'final_key_length' => strlen($finalKey),
    'sources' => $result,
    'config_exists' => file_exists(__DIR__.'/config.php')
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
