<?php
// /api/bidirectional-sync.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/_airtable-common.php'; // единый лоадер PAT и вызовы

// Маппинг полей Airtable (по факту из лога UI):
$F_ID = getenv('AIR_F_ID') ?: 'ID';
$F_RU = getenv('AIR_F_RU') ?: 'Name (RU)';
$F_EN = getenv('AIR_F_EN') ?: 'Name (EN)';

// Настройки БД
$dbPath = getenv('SQLITE_PATH');
if (!$dbPath) {
  $dbPath = __DIR__ . '/../data/constructour.db';
  @mkdir(dirname($dbPath), 0775, true);
}

function db() {
  global $dbPath;
  static $pdo;
  if (!$pdo) {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }
  return $pdo;
}

function ok($payload){ echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }
function fail($msg, $extra=[]){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>$msg]+$extra, JSON_UNESCAPED_UNICODE); exit; }

try {
  // 0) Проверим конфиг Airtable/токен и доступ к таблице
  $cfg = air_cfg(); // выбросит понятную ошибку, если токен некорректен/не найден
  // Пингуем таблицу (list 1)
  [$code,$out,$err,$url] = air_call('GET','', null, ['pageSize'=>1]);
  if ($code === 401) fail('Airtable 401 (PAT rejected)', ['where'=>'list','url'=>$url, 'air_resp'=>json_decode($out,true)?:$out]);
  if ($code === 403) fail('Airtable 403 (no access to base/table)', ['where'=>'list','url'=>$url, 'air_resp'=>json_decode($out,true)?:$out]);
  if ($code >= 400)  fail("Airtable $code", ['where'=>'list','url'=>$url, 'air_resp'=>json_decode($out,true)?:$out]);

  // 1) Инициализация схемы SQLite (минимально)
  $pdo = db();
  $pdo->exec("CREATE TABLE IF NOT EXISTS sync_state (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
  $pdo->prepare("INSERT OR IGNORE INTO sync_state(key,value) VALUES('last_sync_at','1970-01-01T00:00:00Z')")->execute();

  // ====== Минимальный «dry sync» ======
  $j = json_decode($out, true);
  $sample = [];
  if (!empty($j['records'][0])) {
    $f = $j['records'][0]['fields'] ?? [];
    $sample = [
      'airtable_id' => $j['records'][0]['id'],
      'identifier'  => $f[$F_ID] ?? null,
      'name_ru'     => $f[$F_RU] ?? null,
      'name_en'     => $f[$F_EN] ?? null,
    ];
  }

  ok([
    'ok' => true,
    'summary' => [
      'sync_time' => gmdate('c'),
      'airtable_changes' => 0,
      'local_changes' => 0
    ],
    'note' => 'Токен сконфигурирован, доступ к таблице есть. Это “smoke test”. Полный апсерт включим после подтверждения.',
    'field_mapping' => ['ID'=>$F_ID, 'RU'=>$F_RU, 'EN'=>$F_EN],
    'airtable_sample' => $sample
  ]);

} catch (Throwable $e) {
  fail($e->getMessage());
}
?>
