<?php
require_once 'database.php';

function migrateToBidirectionalSync() {
    $db = new Database();
    $pdo = $db->getConnection();
    
    try {
        $pdo->beginTransaction();
        
        // 1. Обновляем таблицу regions для двусторонней синхронизации
        // Добавляем колонки без UNIQUE (SQLite не поддерживает UNIQUE в ALTER TABLE)
        $pdo->exec("
            ALTER TABLE regions ADD COLUMN identifier TEXT;
        ");
        
        $pdo->exec("
            ALTER TABLE regions ADD COLUMN updated_at TEXT NOT NULL DEFAULT '1970-01-01T00:00:00Z';
        ");
        
        $pdo->exec("
            ALTER TABLE regions ADD COLUMN is_deleted INTEGER NOT NULL DEFAULT 0;
        ");
        
        // Создаем индекс для быстрого поиска по updated_at
        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_regions_updated_at ON regions(updated_at);
        ");
        
        // 2. Создаем таблицу состояния синхронизации
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sync_state (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );
        ");
        
        $pdo->exec("
            INSERT OR IGNORE INTO sync_state(key, value) VALUES ('last_sync_at', '1970-01-01T00:00:00Z');
        ");
        
        // 3. Заполняем identifier для существующих регионов
        $regions = $pdo->query("SELECT id, business_id FROM regions WHERE identifier IS NULL")->fetchAll();
        foreach ($regions as $region) {
            $identifier = $region['business_id'] ?: 'REG-' . str_pad($region['id'], 4, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE regions SET identifier = ? WHERE id = ?")
                ->execute([$identifier, $region['id']]);
        }
        
        // 4. Обновляем updated_at для всех записей
        $pdo->exec("
            UPDATE regions SET updated_at = datetime('now', 'utc') 
            WHERE updated_at = '1970-01-01T00:00:00Z'
        ");
        
        // 5. Создаем уникальный индекс для identifier после заполнения данных
        $pdo->exec("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_regions_identifier ON regions(identifier);
        ");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Migration completed successfully',
            'changes' => [
                'Added identifier column to regions',
                'Added updated_at column to regions', 
                'Added is_deleted column to regions',
                'Created sync_state table',
                'Updated existing records with identifiers and timestamps',
                'Created unique index on identifier column'
            ]
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Запускаем миграцию
$result = migrateToBidirectionalSync();

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
