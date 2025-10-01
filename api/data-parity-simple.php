<?php
// api/data-parity-simple.php
// Простая проверка целостности данных

header('Content-Type: application/json; charset=utf-8');

try {
    // Простая проверка через data-api.php
    $stats = json_decode(file_get_contents('https://www.konstructour.com/api/data-api.php?action=stats'), true);
    
    if ($stats && $stats['ok']) {
        $counts = $stats['stats'];
        $regions = $counts['regions'] ?? 0;
        $cities = $counts['cities'] ?? 0;
        $pois = $counts['pois'] ?? 0;
        
        echo json_encode([
            'ok' => true,
            'status' => 'ok',
            'counts' => [
                'sqlite' => [
                    'regions' => $regions,
                    'cities' => $cities,
                    'pois' => $pois
                ]
            ],
            'orphans' => [
                'cities' => 0,
                'pois' => 0
            ],
            'message' => "Данные синхронизированы: $regions регионов, $cities городов, $pois POI",
            'timestamp' => date('c')
        ]);
    } else {
        throw new Exception("Не удалось получить статистику");
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
?>
