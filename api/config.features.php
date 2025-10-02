<?php
// Feature flags — раскатываем по одному
define('SYNC_REFERENCES_ENABLED', true);   // еженедельная сверка регионов/городов
define('SYNC_TICKETS_ENABLED',    false);  // включим после Discover/Validate
define('BATCH_UPSERT_ENABLED',    true);   // батчи по 10 записей
define('RETRY_ENABLED',           true);   // ретраи только для 429/5xx
?>
