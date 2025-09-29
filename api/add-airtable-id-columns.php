<?php
require_once 'database.php';

function addAirtableIdColumns() {
    $db = new Database();
    $connection = $db->getConnection();

    try {
        // Добавляем колонку airtable_id в таблицы
        $migrations = [
            "ALTER TABLE regions ADD COLUMN airtable_id TEXT",
            "ALTER TABLE cities ADD COLUMN airtable_id TEXT",
            "ALTER TABLE pois ADD COLUMN airtable_id TEXT"
        ];

        foreach ($migrations as $sql) {
            try {
                $connection->exec($sql);
                echo "✓ Executed: $sql\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'duplicate column name') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                    echo "⚠ Column already exists: $sql\n";
                } else {
                    throw $e;
                }
            }
        }

        echo "\n🎉 Airtable ID columns added successfully!\n";

    } catch (Exception $e) {
        echo "❌ Migration failed: " . $e->getMessage() . "\n";
    }
}

addAirtableIdColumns();
?>
