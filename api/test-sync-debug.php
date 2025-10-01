<?php
// api/test-sync-debug.php
// Простой тест синхронизации для отладки

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 Тест синхронизации...\n";

try {
    echo "1. Проверяем database.php...\n";
    require_once 'database.php';
    echo "✅ database.php загружен\n";
    
    echo "2. Проверяем secret-airtable.php...\n";
    require_once 'secret-airtable.php';
    echo "✅ secret-airtable.php загружен\n";
    
    echo "3. Проверяем токен...\n";
    $tokens = load_airtable_tokens();
    if ($tokens['current']) {
        echo "✅ Токен найден: " . substr($tokens['current'], 0, 10) . "...\n";
    } else {
        echo "❌ Токен не найден\n";
    }
    
    echo "4. Проверяем базу данных...\n";
    $db = new Database();
    echo "✅ База данных подключена\n";
    
    echo "5. Тест завершен успешно!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n";
}
?>
