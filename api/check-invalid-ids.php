<?php
/**
 * API endpoint для проверки некорректных ID в базе данных
 * Возвращает список записей с некорректными business_id
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once 'database.php';
require_once 'filter-constants.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Сканируем базу данных на предмет некорректных ID
    $invalidIds = findInvalidIds($pdo);
    
    // Логируем найденные некорректные ID
    if (!empty($invalidIds)) {
        logInvalidIds($invalidIds);
    }
    
    // Группируем по типам для удобства
    $grouped = [
        'regions' => [],
        'cities' => [],
        'pois' => []
    ];
    
    foreach ($invalidIds as $record) {
        $grouped[$record['table']][] = $record;
    }
    
    // Статистика
    $stats = [
        'total_invalid' => count($invalidIds),
        'regions_invalid' => count($grouped['regions']),
        'cities_invalid' => count($grouped['cities']),
        'pois_invalid' => count($grouped['pois'])
    ];
    
    echo json_encode([
        'ok' => true,
        'message' => 'Проверка некорректных ID завершена',
        'stats' => $stats,
        'invalid_records' => $grouped,
        'raw_invalid' => $invalidIds,
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'message' => 'Ошибка при проверке некорректных ID',
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
