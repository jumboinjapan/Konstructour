<?php
// Lightweight event/error log API (JSONL store)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$LOG_FILE = __DIR__.'/.events_log.jsonl';

function respond($ok, $data = [], $code = 200){ http_response_code($code); echo json_encode(['ok'=>$ok] + $data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

function ensure_dir($path){ $dir = dirname($path); if (!is_dir($dir)) @mkdir($dir, 0700, true); }

function append_log($event){
  global $LOG_FILE; ensure_dir($LOG_FILE);
  $line = json_encode($event, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
  @file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
  // Trim file if > 5MB (simple heuristic)
  if (file_exists($LOG_FILE) && filesize($LOG_FILE) > 5*1024*1024){
    $lines = @file($LOG_FILE, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [];
    $keep = array_slice($lines, max(0, count($lines) - 1000));
    @file_put_contents($LOG_FILE, implode("\n", $keep)."\n");
  }
}

function read_events(){
  global $LOG_FILE; if (!file_exists($LOG_FILE)) return [];
  $lines = @file($LOG_FILE, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [];
  $out = [];
  foreach ($lines as $ln){ $j = json_decode($ln, true); if (is_array($j)) $out[] = $j; }
  return $out;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (!$action) respond(false, ['error'=>'Missing action'], 400);

if ($action === 'add'){
  $raw = file_get_contents('php://input'); $j = json_decode($raw, true) ?: [];
  $type = $j['type'] ?? 'error';
  $msg = $j['msg'] ?? ($j['message'] ?? '');
  $ctx = $j['ctx'] ?? ($j['context'] ?? []);
  if (!$msg) respond(false, ['error'=>'Missing message'], 400);
  append_log([
    'ts' => time(),
    'type' => $type,
    'msg' => $msg,
    'ctx' => $ctx,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
  ]);
  respond(true, ['saved'=>1]);
}

if ($action === 'count'){
  $window = intval($_GET['window'] ?? 3600);
  $since = time() - max(0, $window);
  $events = read_events();
  $cnt = 0; foreach ($events as $e){ if (($e['ts'] ?? 0) >= $since && ($e['type'] ?? '') === 'error') $cnt++; }
  respond(true, ['count'=>$cnt]);
}

if ($action === 'list'){
  $limit = intval($_GET['limit'] ?? 50); if ($limit<=0) $limit=50; if ($limit>500) $limit=500;
  $events = read_events();
  $slice = array_slice($events, -$limit);
  respond(true, ['items'=>$slice, 'total'=>count($events)]);
}

if ($action === 'clear'){
  global $LOG_FILE; @unlink($LOG_FILE); respond(true, ['cleared'=>1]);
}

respond(false, ['error'=>'Unknown action'], 400);


