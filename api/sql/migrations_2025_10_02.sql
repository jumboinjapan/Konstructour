-- Миграции для улучшений системы синхронизации
-- Дата: 2025-10-02

-- 1) Tickets: добавить колонку для Airtable record id (уникальная)
-- Проверяем существование колонки перед добавлением
PRAGMA table_info(tickets);
-- Если колонки нет, добавляем
ALTER TABLE tickets ADD COLUMN airtable_record_id TEXT;
CREATE UNIQUE INDEX IF NOT EXISTS ux_tickets_air ON tickets(airtable_record_id);

-- 2) Лог синхронизации: batch_id, attempt, retry_after, scope
-- Проверяем существование колонок перед добавлением
PRAGMA table_info(sync_log);
-- Если колонок нет, добавляем
ALTER TABLE sync_log ADD COLUMN batch_id TEXT;
ALTER TABLE sync_log ADD COLUMN attempt INTEGER DEFAULT 1;
ALTER TABLE sync_log ADD COLUMN retry_after INTEGER;
ALTER TABLE sync_log ADD COLUMN scope TEXT;

-- 3) Reference-статус (опционально): пометить «осиротевших»
-- Проверяем существование колонок перед добавлением
PRAGMA table_info(regions);
PRAGMA table_info(cities);
-- Если колонок нет, добавляем
ALTER TABLE regions ADD COLUMN is_active INTEGER DEFAULT 1;
ALTER TABLE cities  ADD COLUMN is_active INTEGER DEFAULT 1;
