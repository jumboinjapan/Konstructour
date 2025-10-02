-- Простые миграции для SQLite
-- Дата: 2025-10-02

-- 1) Tickets: добавить колонку для Airtable record id
ALTER TABLE tickets ADD COLUMN airtable_record_id TEXT;
CREATE UNIQUE INDEX IF NOT EXISTS ux_tickets_air ON tickets(airtable_record_id);

-- 2) Лог синхронизации: добавляем новые колонки
ALTER TABLE sync_log ADD COLUMN batch_id TEXT;
ALTER TABLE sync_log ADD COLUMN attempt INTEGER DEFAULT 1;
ALTER TABLE sync_log ADD COLUMN retry_after INTEGER;
ALTER TABLE sync_log ADD COLUMN scope TEXT;

-- 3) Reference-статус: добавляем is_active
ALTER TABLE regions ADD COLUMN is_active INTEGER DEFAULT 1;
ALTER TABLE cities ADD COLUMN is_active INTEGER DEFAULT 1;
