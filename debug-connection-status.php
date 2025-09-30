<?php
// Debug connection status logic
echo "🔍 Отладка логики статуса подключения...\n\n";

// Load config
$cfg = [];
if (file_exists(__DIR__.'/api/config.php')) {
    $cfg = require __DIR__.'/api/config.php';
}

echo "📋 Конфигурация:\n";
echo "Config file exists: " . (file_exists(__DIR__.'/api/config.php') ? 'YES' : 'NO') . "\n";
echo "Config loaded: " . (is_array($cfg) ? 'YES' : 'NO') . "\n\n";

// Check Airtable key presence (as in health.php)
$airtableKeyPresent = !empty($cfg['airtable']['api_key'] ?? '')
                   || !empty($cfg['airtable']['token'] ?? '')
                   || !empty($cfg['airtable_pat'] ?? '');

echo "🔑 Проверка наличия ключа Airtable:\n";
echo "airtable.api_key: " . ($cfg['airtable']['api_key'] ?? 'NOT_SET') . "\n";
echo "airtable.token: " . ($cfg['airtable']['token'] ?? 'NOT_SET') . "\n";
echo "airtable_pat: " . ($cfg['airtable_pat'] ?? 'NOT_SET') . "\n";
echo "Key present: " . ($airtableKeyPresent ? 'YES' : 'NO') . "\n\n";

// Check airtable_registry
$airReg = $cfg['airtable_registry'] ?? [];
echo "📊 Airtable Registry:\n";
echo "baseId: " . ($airReg['baseId'] ?? 'NOT_SET') . "\n";
echo "api_key: " . ($airReg['api_key'] ?? 'NOT_SET') . "\n";
echo "tables: " . (isset($airReg['tables']) ? 'SET' : 'NOT_SET') . "\n\n";

// Simulate health.php logic
echo "🧪 Симуляция логики health.php:\n";

if (!$airtableKeyPresent) {
    echo "❌ Нет ключа Airtable - статус: Ожидание\n";
} else {
    echo "✅ Ключ найден - проверяем подключение...\n";
    
    // Get PAT from various sources
    $pat = $cfg['airtable']['api_key'] ?? ($cfg['airtable']['token'] ?? ($cfg['airtable_pat'] ?? ''));
    echo "PAT found: " . ($pat ? 'YES (' . substr($pat, 0, 10) . '...)' : 'NO') . "\n";
    
    if ($pat) {
        // Test whoami
        echo "🔍 Тестируем whoami...\n";
        $ch = curl_init('https://api.airtable.com/v0/meta/whoami');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $pat],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Whoami response code: $code\n";
        
        if ($code >= 200 && $code < 300) {
            echo "✅ Whoami успешен - статус: Подключено\n";
        } else if ($code === 401) {
            echo "❌ 401 Unauthorized - статус: Ошибка\n";
        } else {
            echo "⚠️  Неожиданный код: $code - статус: Ошибка\n";
        }
    } else {
        echo "❌ PAT не найден - статус: Ошибка\n";
    }
}

echo "\n💡 Вывод:\n";
echo "Система показывает 'Подключено' если:\n";
echo "1. Есть хотя бы один ключ Airtable в конфигурации\n";
echo "2. И whoami запрос возвращает 200-299\n";
echo "3. И все таблицы доступны (если настроены)\n\n";

echo "В вашем случае:\n";
if ($airtableKeyPresent) {
    echo "- ✅ Ключ найден в конфигурации\n";
    echo "- ❌ Но whoami возвращает 401 (неверный токен)\n";
    echo "- 🤔 Возможно, система кэширует старый статус\n";
} else {
    echo "- ❌ Ключ не найден в конфигурации\n";
    echo "- 🤔 Возможно, система использует другой источник ключа\n";
}
?>
