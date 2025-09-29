<?php
// Функция для загрузки переменных окружения из файлов
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Пропускаем комментарии
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Парсим KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Устанавливаем переменную окружения только если она не установлена
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
    
    return true;
}

// Загружаем переменные окружения из файлов
loadEnv(__DIR__ . '/airtable.env.local');
loadEnv(__DIR__ . '/airtable.env');
loadEnv(__DIR__ . '/.env');
loadEnv(__DIR__ . '/.env.local');
?>
