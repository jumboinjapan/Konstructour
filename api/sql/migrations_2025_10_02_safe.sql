-- Безопасные миграции для SQLite
-- Дата: 2025-10-02

-- 1) Tickets: добавить колонку для Airtable record id (уникальная)
-- Проверяем и добавляем только если колонки нет
BEGIN TRANSACTION;

-- Проверяем существование колонки airtable_record_id в tickets
SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE tickets ADD COLUMN airtable_record_id TEXT;'
    ELSE 'SELECT "Column airtable_record_id already exists in tickets";'
END
FROM pragma_table_info('tickets') 
WHERE name = 'airtable_record_id';

-- Создаем индекс только если колонка существует
CREATE UNIQUE INDEX IF NOT EXISTS ux_tickets_air ON tickets(airtable_record_id);

-- 2) Лог синхронизации: добавляем новые колонки
-- Проверяем и добавляем batch_id
SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE sync_log ADD COLUMN batch_id TEXT;'
    ELSE 'SELECT "Column batch_id already exists in sync_log";'
END
FROM pragma_table_info('sync_log') 
WHERE name = 'batch_id';

-- Проверяем и добавляем attempt
SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE sync_log ADD COLUMN attempt INTEGER DEFAULT 1;'
    ELSE 'SELECT "Column attempt already exists in sync_log";'
END
FROM pragma_table_info('sync_log') 
WHERE name = 'attempt';

-- Проверяем и добавляем retry_after
SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE sync_log ADD COLUMN retry_after INTEGER;'
    ELSE 'SELECT "Column retry_after already exists in sync_log";'
END
FROM pragma_table_info('sync_log') 
WHERE name = 'retry_after';

-- Проверяем и добавляем scope
SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE sync_log ADD COLUMN scope TEXT;'
    ELSE 'SELECT "Column scope already exists in sync_log";'
END
FROM pragma_table_info('sync_log') 
WHERE name = 'scope';

-- 3) Reference-статус: добавляем is_active
-- Проверяем и добавляем is_active в regions
SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE regions ADD COLUMN is_active INTEGER DEFAULT 1;'
    ELSE 'SELECT "Column is_active already exists in regions";'
END
FROM pragma_table_info('regions') 
WHERE name = 'is_active';

-- Проверяем и добавляем is_active в cities
SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE cities ADD COLUMN is_active INTEGER DEFAULT 1;'
    ELSE 'SELECT "Column is_active already exists in cities";'
END
FROM pragma_table_info('cities') 
WHERE name = 'is_active';

COMMIT;
