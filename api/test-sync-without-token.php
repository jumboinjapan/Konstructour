<?php
// Тест синхронизации без токена Airtable
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'diagnostic',
    'message' => 'Проблема найдена: токен Airtable не настроен',
    'symptoms' => [
        'Города Хоккайдо не синхронизируются',
        'Кнопка синхронизации не работает',
        'В консоли ошибки API'
    ],
    'root_cause' => 'Airtable API key not configured',
    'solution' => [
        '1. Получите токен на https://airtable.com/create/tokens',
        '2. Замените PLACEHOLDER_FOR_REAL_API_KEY в api/config.php',
        '3. Запустите php api/check-cities-structure.php для проверки',
        '4. Перезапустите синхронизацию в админ-панели'
    ],
    'files_to_check' => [
        'api/config.php - настройка токена',
        'api/check-cities-structure.php - диагностика структуры',
        'AIRTABLE_TOKEN_SETUP.md - инструкция по настройке'
    ],
    'expected_result' => 'После настройки токена города Хоккайдо должны синхронизироваться из Airtable'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
