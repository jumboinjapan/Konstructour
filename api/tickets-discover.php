<?php
// Discovery эндпоинт для исследования структуры таблицы билетов в Airtable

require_once __DIR__ . '/config.features.php';
require_once __DIR__ . '/lib/airtable_client.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $air = new Airtable(getenv('AIRTABLE_BASE_ID') ?: 'apppwhjFN82N9zNqm');
    // ID таблицы билетов — если есть, подставим через ENV, иначе имя 'Tickets'
    $table = getenv('AIRTABLE_TICKETS_TABLE') ?: 'Tickets';
    $res = $air->list($table, ['pageSize' => 10]);
    
    $fields = [];
    foreach (($res['records'] ?? []) as $rec) {
        foreach (array_keys($rec['fields'] ?? []) as $k) {
            $fields[$k] = true;
        }
    }
    
    echo json_encode([
        'ok' => true,
        'sample_count' => count($res['records'] ?? []),
        'fields' => array_keys($fields),
        'samples' => $res['records'] ?? []
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
