<?php
/**
 * Простая проверка полей POI
 */

require_once 'database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Получаем структуру локальной БД
    $stmt = $conn->query("PRAGMA table_info(pois)");
    $dbColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dbColumns[] = $row['name'];
    }
    
    echo "=== СТРУКТУРА ЛОКАЛЬНОЙ БД ===\n";
    echo "Колонки в таблице pois:\n";
    foreach ($dbColumns as $col) {
        echo "  - $col\n";
    }
    
    // Проверяем наличие ключевых полей
    $requiredFields = [
        'prefecture_ru', 'prefecture_en', 'categories_ru', 'categories_en',
        'description_ru', 'description_en', 'website', 'working_hours', 'notes'
    ];
    
    echo "\n=== ПРОВЕРКА КЛЮЧЕВЫХ ПОЛЕЙ ===\n";
    foreach ($requiredFields as $field) {
        $exists = in_array($field, $dbColumns);
        echo ($exists ? "✅" : "❌") . " $field\n";
    }
    
    // Проверяем данные в таблице
    $stmt = $conn->query("SELECT COUNT(*) as count FROM pois");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "\n=== ДАННЫЕ В ТАБЛИЦЕ ===\n";
    echo "Всего записей POI: $count\n";
    
    if ($count > 0) {
        $stmt = $conn->query("SELECT id, name_ru, business_id FROM pois LIMIT 3");
        echo "Примеры записей:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - ID: {$row['id']}, Name: {$row['name_ru']}, Business ID: {$row['business_id']}\n";
        }
    }
    
    echo "\n✅ Проверка завершена!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
