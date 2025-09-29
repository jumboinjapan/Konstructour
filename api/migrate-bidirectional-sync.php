<?php
// /api/migrate-bidirectional-sync.php
header('Content-Type: application/json; charset=utf-8');

try {
  // 1) Путь к вашей локальной БД (правьте при необходимости)
  $dbPath = getenv('SQLITE_PATH');
  if (!$dbPath) {
    // запасной вариант: рядом с проектом
    $dbPath = __DIR__ . '/../data/constructour.db';
    @mkdir(dirname($dbPath), 0775, true);
  }

  $pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // хелперы
  $changes = [];

  $colExists = function(PDO $pdo, string $table, string $col): bool {
    $stmt = $pdo->query("PRAGMA table_info(" . str_replace('"','""',$table) . ")");
    foreach ($stmt->fetchAll() as $r) {
      if (strcasecmp($r['name'], $col) === 0) return true;
    }
    return false;
  };

  $idxExists = function(PDO $pdo, string $idx): bool {
    $stmt = $pdo->query("PRAGMA index_list(regions)");
    foreach ($stmt->fetchAll() as $r) {
      if (strcasecmp($r['name'], $idx) === 0) return true;
    }
    return false;
  };

  $pdo->beginTransaction();

  // 2) Базовая таблица regions (создастся, если отсутствует)
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS regions (
      id           INTEGER PRIMARY KEY AUTOINCREMENT,
      identifier   TEXT    NOT NULL UNIQUE,
      name_ru      TEXT    NOT NULL,
      name_en      TEXT    NOT NULL,
      airtable_id  TEXT    UNIQUE,
      updated_at   TEXT    NOT NULL,
      is_deleted   INTEGER NOT NULL DEFAULT 0
    )
  ");
  $changes[] = 'ensure table regions';

  // 3) Досоздаём недостающие колонки (важно: проверяем перед ADD COLUMN)
  $wantCols = [
    ['identifier',  "TEXT NOT NULL UNIQUE"],
    ['name_ru',     "TEXT NOT NULL"],
    ['name_en',     "TEXT NOT NULL"],
    ['airtable_id', "TEXT UNIQUE"],
    ['updated_at',  "TEXT NOT NULL"],
    ['is_deleted',  "INTEGER NOT NULL DEFAULT 0"],
  ];
  foreach ($wantCols as [$name, $ddl]) {
    if (!$colExists($pdo, 'regions', $name)) {
      $pdo->exec("ALTER TABLE regions ADD COLUMN {$name} {$ddl}");
      $changes[] = "add column regions.{$name}";
    }
  }

  // 4) Индекс по updated_at
  if (!$idxExists($pdo, 'idx_regions_updated_at')) {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_regions_updated_at ON regions(updated_at)");
    $changes[] = 'ensure index idx_regions_updated_at';
  }

  // 5) Служебная таблица состояния синка
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS sync_state (
      key   TEXT PRIMARY KEY,
      value TEXT NOT NULL
    )
  ");
  $changes[] = 'ensure table sync_state';

  // 6) Стартовое значение last_sync_at
  $stmt = $pdo->prepare("SELECT value FROM sync_state WHERE key='last_sync_at'");
  $val = $stmt->execute() && ($row = $stmt->fetch()) ? $row['value'] : null;
  if ($val === null) {
    $pdo->prepare("INSERT INTO sync_state(key,value) VALUES('last_sync_at','1970-01-01T00:00:00Z')")->execute();
    $changes[] = 'init last_sync_at';
  }

  // 7) Заполняем identifier для существующих записей без него
  $stmt = $pdo->query("SELECT id, business_id FROM regions WHERE identifier IS NULL OR identifier = ''");
  $regions = $stmt->fetchAll();
  foreach ($regions as $region) {
    $identifier = $region['business_id'] ?: 'REG-' . str_pad($region['id'], 4, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE regions SET identifier = ? WHERE id = ?")
        ->execute([$identifier, $region['id']]);
  }
  if (!empty($regions)) {
    $changes[] = 'populate ' . count($regions) . ' identifiers';
  }

  // 8) Обновляем updated_at для записей без него
  $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM regions WHERE updated_at IS NULL OR updated_at = ''");
  $count = $stmt->fetch()['cnt'];
  if ($count > 0) {
    $pdo->exec("UPDATE regions SET updated_at = datetime('now', 'utc') WHERE updated_at IS NULL OR updated_at = ''");
    $changes[] = "set updated_at for $count records";
  }

  $pdo->commit();

  echo json_encode([
    'success' => true,
    'message' => 'Migration completed successfully',
    'changes' => $changes
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
  if (isset($pdo)) {
    $pdo->rollBack();
  }
  
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine()
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>