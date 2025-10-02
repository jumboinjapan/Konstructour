<?php
// api/check-filtering-violations.php
// Проверка нарушений Filtering.md

require_once 'data-guard.php';
require_once 'database.php';

echo "🔍 ПРОВЕРКА СОБЛЮДЕНИЯ FILTERING.MD\n";
echo "=====================================\n\n";

// 1. Проверяем, есть ли локальные данные без Airtable
echo "1. Проверка локальных данных...\n";
try {
    $db = new Database();
    $regions = $db->getRegions();
    $cities = $db->getConnection()->query('SELECT * FROM cities')->fetchAll(PDO::FETCH_ASSOC);
    $pois = $db->getConnection()->query('SELECT * FROM pois')->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($regions) > 0 || count($cities) > 0 || count($pois) > 0) {
        echo "❌ НАРУШЕНИЕ: В локальной базе есть данные без синхронизации с Airtable\n";
        echo "   Регионы: " . count($regions) . "\n";
        echo "   Города: " . count($cities) . "\n";
        echo "   POI: " . count($pois) . "\n";
        echo "   РЕШЕНИЕ: Очистить локальную базу или настроить синхронизацию с Airtable\n\n";
    } else {
        echo "✅ Локальная база пустая - соответствует Filtering.md\n\n";
    }
} catch (Exception $e) {
    echo "❌ Ошибка проверки локальных данных: " . $e->getMessage() . "\n\n";
}

// 2. Проверяем доступность Airtable
echo "2. Проверка доступности Airtable...\n";
try {
    DataGuard::enforceAirtableOnly();
    echo "✅ Airtable доступен и настроен\n\n";
} catch (Exception $e) {
    echo "❌ Airtable недоступен: " . $e->getMessage() . "\n";
    echo "   РЕШЕНИЕ: Настроить AIRTABLE_TOKEN или secret file\n\n";
}

// 3. Проверяем, есть ли тестовые скрипты
echo "3. Проверка тестовых скриптов...\n";
$testFiles = [
    'create-test-data.php',
    'test-data.php',
    'sample-data.php',
    'mock-data.php'
];

$foundTestFiles = [];
foreach ($testFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $foundTestFiles[] = $file;
    }
}

if (!empty($foundTestFiles)) {
    echo "❌ НАРУШЕНИЕ: Найдены тестовые файлы:\n";
    foreach ($foundTestFiles as $file) {
        echo "   - $file\n";
    }
    echo "   РЕШЕНИЕ: Удалить тестовые файлы\n\n";
} else {
    echo "✅ Тестовые файлы не найдены - соответствует Filtering.md\n\n";
}

// 4. Проверяем методы Database.php
echo "4. Проверка методов Database.php...\n";
$reflection = new ReflectionClass('Database');
$methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

$dangerousMethods = [];
foreach ($methods as $method) {
    $methodName = $method->getName();
    if (strpos($methodName, 'save') === 0 || strpos($methodName, 'create') === 0 || strpos($methodName, 'insert') === 0) {
        $dangerousMethods[] = $methodName;
    }
}

if (!empty($dangerousMethods)) {
    echo "⚠️  ВНИМАНИЕ: Найдены методы для создания данных:\n";
    foreach ($dangerousMethods as $method) {
        echo "   - $method\n";
    }
    echo "   РЕКОМЕНДАЦИЯ: Использовать только для синхронизации с Airtable\n\n";
} else {
    echo "✅ Опасные методы не найдены\n\n";
}

echo "🏁 ПРОВЕРКА ЗАВЕРШЕНА\n";
echo "====================\n";
?>
