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
    $changes[] = 'seed sync_state.last_sync_at';
  }

  // 7) Бэкомпат: если есть строки без updated_at — проставим текущее время
  $stmt = $pdo->query("SELECT COUNT(*) AS c FROM regions WHERE (updated_at IS NULL OR updated_at='')");
  $c = (int)($stmt->fetch()['c'] ?? 0);
  if ($c > 0) {
    $now = gmdate('c'); // ISO8601 UTC
    $pdo->prepare("UPDATE regions SET updated_at=? WHERE (updated_at IS NULL OR updated_at='')")->execute([$now]);
    $changes[] = "backfill regions.updated_at for {$c} rows";
  }

  // 8) Таблица cities для двустороннего синка (идемпотентно)
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS cities (
      id             INTEGER PRIMARY KEY AUTOINCREMENT,
      identifier     TEXT    NOT NULL UNIQUE,
      name_ru        TEXT    NOT NULL,
      name_en        TEXT    NOT NULL,
      region_ident   TEXT,
      lat            REAL,
      lng            REAL,
      place_id       TEXT,
      airtable_id    TEXT UNIQUE,
      updated_at     TEXT    NOT NULL,
      is_deleted     INTEGER NOT NULL DEFAULT 0
    )
  ");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cities_updated_at ON cities(updated_at)");
  $changes[] = 'ensure table cities';

  $pdo->commit();

  echo json_encode([
    'success' => true,
    'db_path' => $dbPath,
    'changes' => $changes,
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error'   => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE);
}
?>