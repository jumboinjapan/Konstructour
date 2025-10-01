<?php
// Диагностика полей Airtable
// Этот скрипт показывает все доступные поля в Airtable

require_once 'database.php';

// Функция для получения токена Airtable
function getAirtableToken() {
    $token = getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_API_KEY');
    if ($token) {
        return $token;
    }
    
    try {
        require_once 'secret-airtable.php';
        $tokens = load_airtable_tokens();
        if ($tokens['current']) {
            return $tokens['current'];
        }
    } catch (Exception $e) {
        echo "Ошибка загрузки секретов: " . $e->getMessage() . "\n";
    }
    
    throw new Exception("Не удалось получить токен Airtable");
}

// Функция для запроса к Airtable API
function airtableRequest($endpoint, $token) {
    $url = "https://api.airtable.com/v0/apppwhjFN82N9zNqm/$endpoint";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Airtable API error: HTTP $httpCode - $response");
    }
    
    return json_decode($response, true);
}

try {
    echo "🔍 Диагностика полей Airtable...\n";
    
    $token = getAirtableToken();
    echo "✅ Токен Airtable получен\n";
    
    // Получаем метаданные таблицы
    echo "📊 Получаем метаданные таблицы...\n";
    $metaData = airtableRequest('tblVCmFcHRpXUT24y', $token);
    
    if (isset($metaData['records'])) {
        echo "Найдено записей: " . count($metaData['records']) . "\n";
        
        // Анализируем поля первой записи
        if (count($metaData['records']) > 0) {
            $firstRecord = $metaData['records'][0];
            $fields = $firstRecord['fields'];
            
            echo "\n--- Анализ полей первой записи ---\n";
            echo "ID записи: " . $firstRecord['id'] . "\n";
            echo "Доступные поля:\n";
            
            foreach ($fields as $fieldName => $fieldValue) {
                $type = gettype($fieldValue);
                $preview = is_string($fieldValue) ? substr($fieldValue, 0, 50) : 
                          (is_array($fieldValue) ? 'Array[' . count($fieldValue) . ']' : 
                          (is_bool($fieldValue) ? ($fieldValue ? 'true' : 'false') : 
                          (is_null($fieldValue) ? 'null' : $fieldValue)));
                
                echo "  - $fieldName ($type): $preview\n";
            }
            
            // Ищем поля с названиями
            echo "\n--- Поиск полей с названиями ---\n";
            $nameFields = [];
            foreach ($fields as $fieldName => $fieldValue) {
                if (stripos($fieldName, 'name') !== false || 
                    stripos($fieldName, 'название') !== false ||
                    stripos($fieldName, 'title') !== false ||
                    stripos($fieldName, 'заголовок') !== false) {
                    $nameFields[] = $fieldName;
                }
            }
            
            if (!empty($nameFields)) {
                echo "Найдены поля с названиями:\n";
                foreach ($nameFields as $field) {
                    echo "  - $field: " . (isset($fields[$field]) ? $fields[$field] : 'Нет значения') . "\n";
                }
            } else {
                echo "❌ Поля с названиями не найдены\n";
            }
            
            // Ищем поля с категориями
            echo "\n--- Поиск полей с категориями ---\n";
            $categoryFields = [];
            foreach ($fields as $fieldName => $fieldValue) {
                if (stripos($fieldName, 'category') !== false || 
                    stripos($fieldName, 'категория') !== false ||
                    stripos($fieldName, 'type') !== false ||
                    stripos($fieldName, 'тип') !== false) {
                    $categoryFields[] = $fieldName;
                }
            }
            
            if (!empty($categoryFields)) {
                echo "Найдены поля с категориями:\n";
                foreach ($categoryFields as $field) {
                    echo "  - $field: " . (isset($fields[$field]) ? (is_array($fields[$field]) ? implode(', ', $fields[$field]) : $fields[$field]) : 'Нет значения') . "\n";
                }
            } else {
                echo "❌ Поля с категориями не найдены\n";
            }
        }
    } else {
        echo "❌ Нет записей в таблице\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
?>
