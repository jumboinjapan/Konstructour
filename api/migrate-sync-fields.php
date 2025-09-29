<?php
// Миграция для добавления полей отслеживания синхронизации
require_once 'database.php';

function migrateSyncFields() {
    $db = new Database();
    $connection = $db->getConnection();
    
    try {
        // Добавляем поля для отслеживания времени синхронизации
        $migrations = [
            "ALTER TABLE regions ADD COLUMN airtable_updated_at DATETIME",
            "ALTER TABLE regions ADD COLUMN local_updated_at DATETIME DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE cities ADD COLUMN airtable_updated_at DATETIME",
            "ALTER TABLE cities ADD COLUMN local_updated_at DATETIME DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE pois ADD COLUMN airtable_updated_at DATETIME",
            "ALTER TABLE pois ADD COLUMN local_updated_at DATETIME DEFAULT CURRENT_TIMESTAMP"
        ];
        
        foreach ($migrations as $sql) {
            try {
                $connection->exec($sql);
                echo "✓ Executed: $sql\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'duplicate column name') !== false) {
                    echo "⚠ Column already exists: $sql\n";
                } else {
                    echo "✗ Error: $sql - " . $e->getMessage() . "\n";
                }
            }
        }
        
        // Обновляем существующие записи
        $updateSql = "
            UPDATE regions 
            SET local_updated_at = updated_at 
            WHERE local_updated_at IS NULL
        ";
        $connection->exec($updateSql);
        echo "✓ Updated existing regions with local_updated_at\n";
        
        $updateSql = "
            UPDATE cities 
            SET local_updated_at = updated_at 
            WHERE local_updated_at IS NULL
        ";
        $connection->exec($updateSql);
        echo "✓ Updated existing cities with local_updated_at\n";
        
        $updateSql = "
            UPDATE pois 
            SET local_updated_at = updated_at 
            WHERE local_updated_at IS NULL
        ";
        $connection->exec($updateSql);
        echo "✓ Updated existing pois with local_updated_at\n";
        
        echo "\n🎉 Migration completed successfully!\n";
        return true;
        
    } catch (Exception $e) {
        echo "❌ Migration failed: " . $e->getMessage() . "\n";
        return false;
    }
}

// Запуск миграции
if (php_sapi_name() === 'cli') {
    migrateSyncFields();
} else {
    header('Content-Type: application/json; charset=utf-8');
    $result = migrateSyncFields();
    echo json_encode(['ok' => $result]);
}
?>
