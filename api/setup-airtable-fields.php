<?php
require_once 'config.php';

// Загружаем переменные окружения
$envFile = __DIR__ . '/airtable.env.local';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'AIRTABLE_PAT=') === 0) {
            $token = substr($line, 12);
            putenv("AIRTABLE_PAT=$token");
            $_ENV['AIRTABLE_PAT'] = $token;
            $_SERVER['AIRTABLE_PAT'] = $token;
            break;
        }
    }
}

// Также загружаем из config.php как fallback
$config = include 'config.php';
if (!$pat = getenv('AIRTABLE_PAT')) {
    $pat = $config['airtable_registry']['api_key'] ?? null;
    if ($pat && $pat !== 'PLACEHOLDER_FOR_REAL_API_KEY') {
        putenv("AIRTABLE_PAT=$pat");
        $_ENV['AIRTABLE_PAT'] = $pat;
        $_SERVER['AIRTABLE_PAT'] = $pat;
    }
}

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function makeAirtableRequest($url, $data = [], $method = 'GET') {
    $pat = getenv('AIRTABLE_PAT');
    
    if (!$pat || $pat === 'PLACEHOLDER_FOR_REAL_API_KEY') {
        throw new Exception('Airtable token not configured. Please set up token first.');
    }
    
    $ch = curl_init();
    
    $headers = [
        "Authorization: Bearer $pat",
        "Content-Type: application/json"
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method
    ]);
    
    if ($method === 'POST' || $method === 'PATCH') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'GET' && !empty($data)) {
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Airtable API Error: HTTP $httpCode - $response");
    }
    
    return $response;
}

function setupAirtableFields() {
    $config = include 'config.php';
    $baseId = $config['airtable_registry']['baseId'];
    $tableId = $config['airtable_registry']['tables']['region']['tableId'];
    
    // Получаем схему таблицы
    $url = "https://api.airtable.com/v0/meta/bases/$baseId/tables";
    $response = makeAirtableRequest($url);
    $data = json_decode($response, true);
    
    $regionsTable = null;
    foreach ($data['tables'] as $table) {
        if ($table['id'] === $tableId) {
            $regionsTable = $table;
            break;
        }
    }
    
    if (!$regionsTable) {
        throw new Exception("Table $tableId not found");
    }
    
    $existingFields = [];
    foreach ($regionsTable['fields'] as $field) {
        $existingFields[$field['name']] = $field['id'];
    }
    
    $requiredFields = [
        'Идентификатор' => 'Single line text',
        'Название (RU)' => 'Single line text', 
        'Название (EN)' => 'Single line text',
        'updated_at' => 'Single line text',
        'is_deleted' => 'Checkbox'
    ];
    
    $missingFields = [];
    $fieldMappings = [];
    
    foreach ($requiredFields as $fieldName => $fieldType) {
        if (!isset($existingFields[$fieldName])) {
            $missingFields[] = [
                'name' => $fieldName,
                'type' => $fieldType
            ];
        } else {
            $fieldMappings[$fieldName] = $existingFields[$fieldName];
        }
    }
    
    // Создаем недостающие поля
    if (!empty($missingFields)) {
        $url = "https://api.airtable.com/v0/meta/bases/$baseId/tables/$tableId/fields";
        
        foreach ($missingFields as $field) {
            try {
                $response = makeAirtableRequest($url, $field, 'POST');
                $fieldData = json_decode($response, true);
                $fieldMappings[$field['name']] = $fieldData['id'];
                
                // Задержка между запросами
                usleep(500000); // 0.5 секунды
                
            } catch (Exception $e) {
                error_log("Failed to create field {$field['name']}: " . $e->getMessage());
            }
        }
    }
    
    return [
        'existing_fields' => $existingFields,
        'missing_fields' => $missingFields,
        'field_mappings' => $fieldMappings,
        'table_info' => [
            'id' => $regionsTable['id'],
            'name' => $regionsTable['name'],
            'description' => $regionsTable['description']
        ]
    ];
}

// Обработка запроса
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $result = setupAirtableFields();
        respond(true, $result);
    } catch (Exception $e) {
        respond(false, ['error' => $e->getMessage()], 500);
    }
} else {
    respond(false, ['error' => 'Method not allowed'], 405);
}
?>
