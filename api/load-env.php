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
            
            // Устанавливаем переменную окружения (перезаписываем если уже установлена)
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            
            // Отладочная информация
            if ($key === 'AIRTABLE_PAT') {
                error_log("Loaded AIRTABLE_PAT: $value");
            }
        }
    }
    
    return true;
}

// Загружаем переменные окружения из файлов (в порядке приоритета)
loadEnv(__DIR__ . '/airtable.env.local');
loadEnv(__DIR__ . '/airtable.env');
loadEnv(__DIR__ . '/.env');
loadEnv(__DIR__ . '/.env.local');

// Отладочная информация
if (getenv('AIRTABLE_PAT') && getenv('AIRTABLE_PAT') !== 'your_airtable_token_here') {
    // Токен загружен успешно
} else {
    // Токен не загружен, показываем отладочную информацию
    error_log('AIRTABLE_PAT not loaded from env files');
}
?>
