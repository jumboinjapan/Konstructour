<?php
/**
 * Миграция схемы POI - добавление новых полей
 * Расширяет таблицу pois для полного соответствия Airtable
 */

header('Content-Type: application/json; charset=utf-8');

require_once 'database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "🔄 Начало миграции схемы POI...\n\n";
    
    // Получаем текущую структуру таблицы
    $stmt = $conn->query("PRAGMA table_info(pois)");
    $existingColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['name'];
    }
    
    echo "📋 Существующие колонки:\n";
    echo json_encode($existingColumns, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // Определяем новые колонки для добавления
    $newColumns = [
        'prefecture_ru' => 'TEXT',
        'prefecture_en' => 'TEXT',
        'categories_ru' => 'TEXT',  // JSON массив
        'categories_en' => 'TEXT',  // JSON массив
        'description_ru' => 'TEXT',
        'description_en' => 'TEXT',
        'website' => 'TEXT',
        'working_hours' => 'TEXT',
        'notes' => 'TEXT'
    ];
    
    echo "➕ Добавление новых колонок:\n";
    
    foreach ($newColumns as $columnName => $columnType) {
        if (in_array($columnName, $existingColumns)) {
            echo "   ⚠️  $columnName - уже существует, пропускаем\n";
            continue;
        }
        
        try {
            $sql = "ALTER TABLE pois ADD COLUMN $columnName $columnType";
            $conn->exec($sql);
            echo "   ✅ $columnName - добавлена ($columnType)\n";
        } catch (Exception $e) {
            echo "   ❌ $columnName - ошибка: {$e->getMessage()}\n";
        }
    }
    
    echo "\n📊 Миграция данных из старых полей:\n";
    
    // Миграция: category → categories_ru (JSON)
    if (in_array('category', $existingColumns) && in_array('categories_ru', $existingColumns)) {
        $stmt = $conn->query("SELECT id, category FROM pois WHERE category IS NOT NULL AND category != ''");
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categoryArray = json_encode([$row['category']], JSON_UNESCAPED_UNICODE);
            $update = $conn->prepare("UPDATE pois SET categories_ru = ? WHERE id = ?");
            $update->execute([$categoryArray, $row['id']]);
            $count++;
        }
        echo "   ✅ category → categories_ru: мигрировано $count записей\n";
    }
    
    // Миграция: description → description_ru
    if (in_array('description', $existingColumns) && in_array('description_ru', $existingColumns)) {
        $stmt = $conn->query("SELECT id, description FROM pois WHERE description IS NOT NULL AND description != ''");
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $update = $conn->prepare("UPDATE pois SET description_ru = ? WHERE id = ?");
            $update->execute([$row['description'], $row['id']]);
            $count++;
        }
        echo "   ✅ description → description_ru: мигрировано $count записей\n";
    }
    
    echo "\n✅ Миграция завершена успешно!\n";
    
    // Показываем финальную структуру
    $stmt = $conn->query("PRAGMA table_info(pois)");
    $finalColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $finalColumns[] = [
            'name' => $row['name'],
            'type' => $row['type'],
            'notnull' => $row['notnull'],
            'dflt_value' => $row['dflt_value']
        ];
    }
    
    echo "\n📋 Финальная структура таблицы pois:\n";
    echo json_encode($finalColumns, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    echo "\n🎉 Готово! Таблица pois обновлена и готова к синхронизации с Airtable.\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка миграции: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>

