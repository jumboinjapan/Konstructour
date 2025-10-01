<?php
// api/test-proxy-secure.php
// Proxy для безопасного тестирования Airtable API

require_once 'secret-airtable.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$provider = $_GET['provider'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    if ($provider !== 'airtable') {
        throw new Exception("Unsupported provider: $provider");
    }
    
    // Получаем токен Airtable
    $tokens = load_airtable_tokens();
    $token = $tokens['current'] ?? null;
    
    if (!$token) {
        throw new Exception("No Airtable token available");
    }
    
    $baseId = $input['base_id'] ?? '';
    $tableId = $input['table'] ?? '';
    $viewId = $input['view'] ?? '';
    
    // Если есть параметры таблицы, тестируем конкретную таблицу
    if ($baseId && $tableId) {
        $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}";
        if ($viewId) {
            $url .= "?view={$viewId}";
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Table access error: HTTP $httpCode");
        }
        
        $data = json_decode($response, true);
        
        echo json_encode([
            'ok' => true,
            'auth' => true,
            'status' => $httpCode,
            'data' => $data,
            'message' => 'Table access successful',
            'timestamp' => date('c')
        ]);
    } else {
        // Выполняем whoami запрос
        $ch = curl_init('https://api.airtable.com/v0/meta/whoami');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Airtable API error: HTTP $httpCode");
        }
        
        $data = json_decode($response, true);
        
        echo json_encode([
            'ok' => true,
            'auth' => true,
            'status' => $httpCode,
            'data' => $data,
            'message' => 'WhoAmI successful',
            'timestamp' => date('c')
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'auth' => false,
        'status' => 500,
        'error' => $e->getMessage(),
        'message' => 'WhoAmI failed',
        'timestamp' => date('c')
    ]);
}
?>
