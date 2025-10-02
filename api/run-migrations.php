<?php
// Запуск миграций базы данных
require_once __DIR__.'/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $migrationFile = __DIR__.'/sql/migrations_2025_10_02_simple.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    $pdo->exec($sql);
    
    echo "✅ Migrations completed successfully\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
