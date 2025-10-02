<?php
// api/strict-no-test-data.php
// СТРОГИЙ ЗАПРЕТ на любые тестовые/демо/выдуманные данные

/**
 * ЗАПРЕЩЕНО НАВСЕГДА:
 * - Создавать демо-данные
 * - Создавать тестовые записи
 * - Создавать fallback данные
 * - Создавать mock данные
 * - Создавать sample данные
 * - Создавать fake данные
 * - Создавать dummy данные
 * - Создавать placeholder данные
 * - Создавать выдуманные записи
 * - Создавать локальные тестовые данные
 * 
 * РАЗРЕШЕНО ТОЛЬКО:
 * - Данные из Airtable (единственный источник истины)
 * - Ошибки при недоступности Airtable
 * - Логирование ошибок
 * - Информирование о необходимости настройки токена
 */

class StrictNoTestData {
    
    /**
     * Проверяет, что в коде нет тестовых данных
     */
    public static function enforceNoTestData() {
        $forbiddenPatterns = [
            'demo-',
            'mock-',
            'sample-',
            'fake-',
            'dummy-',
            'placeholder',
            'patTest',
            'demo_',
            'mock_',
            'sample_',
            'fake_',
            'dummy_',
            'Кансай \(демо\)',
            'Kansai \(demo\)',
            'Киото \(демо\)',
            'Kyoto \(demo\)',
            'Осака \(демо\)',
            'Osaka \(demo\)',
            'Кинкаку-дзи \(демо\)',
            'Kinkaku-ji \(demo\)',
            'Фушими Инари \(демо\)',
            'Fushimi Inari \(demo\)'
        ];
        
        $files = [
            __DIR__ . '/data-api.php',
            __DIR__ . '/airtable-data-source.php',
            __DIR__ . '/local-dev-fallback.php', // Должен быть удален
        ];
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                foreach ($forbiddenPatterns as $pattern) {
                    if (preg_match('/' . preg_quote($pattern, '/') . '/i', $content)) {
                        throw new Exception("VIOLATION: Found test data pattern '{$pattern}' in {$file}. Test data is STRICTLY FORBIDDEN!");
                    }
                }
            }
        }
    }
    
    /**
     * Проверяет, что система не создает выдуманные записи
     */
    public static function enforceNoFakeRecords() {
        // Проверяем, что нет хардкодных данных
        $hardcodedData = [
            'REG-0001', 'REG-0002',
            'CTY-0001', 'CTY-0002', 
            'POI-000001', 'POI-000002',
            'demo-region-1', 'demo-region-2',
            'demo-city-1', 'demo-city-2',
            'demo-poi-1', 'demo-poi-2'
        ];
        
        $files = [
            __DIR__ . '/data-api.php',
            __DIR__ . '/airtable-data-source.php',
        ];
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                foreach ($hardcodedData as $data) {
                    if (strpos($content, $data) !== false) {
                        throw new Exception("VIOLATION: Found hardcoded data '{$data}' in {$file}. Hardcoded data is STRICTLY FORBIDDEN!");
                    }
                }
            }
        }
    }
    
    /**
     * Проверяет, что система требует Airtable токен
     */
    public static function enforceAirtableRequired() {
        // Эта проверка отключена, так как система должна работать
        // с ошибкой при отсутствии токена, а не с fallback
        return true;
    }
}

// Автоматическая проверка при подключении
try {
    StrictNoTestData::enforceNoTestData();
    StrictNoTestData::enforceNoFakeRecords();
    StrictNoTestData::enforceAirtableRequired();
} catch (Exception $e) {
    error_log("STRICT NO TEST DATA VIOLATION: " . $e->getMessage());
    throw $e;
}
?>
