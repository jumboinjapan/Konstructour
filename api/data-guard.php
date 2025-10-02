<?php
// api/data-guard.php
// СТРОГИЕ ПРАВИЛА для предотвращения нарушений Filtering.md

class DataGuard {
    
    /**
     * ПРОВЕРКА: Можно ли создавать данные локально?
     * Согласно Filtering.md - НЕТ! Только Airtable.
     */
    public static function canCreateLocalData() {
        return false; // НИКОГДА!
    }
    
    /**
     * ПРОВЕРКА: Можно ли читать данные из локальной базы?
     * Только если они синхронизированы с Airtable.
     */
    public static function canReadLocalData() {
        // Проверяем, есть ли данные в Airtable
        try {
            require_once 'airtable-data-source.php';
            $airtable = new AirtableDataSource();
            return $airtable->isAirtableAvailable() && $airtable->testConnection();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * ПРОВЕРКА: Можно ли использовать локальную базу для отображения?
     * Только если она является кэшем данных из Airtable.
     */
    public static function canUseLocalCache() {
        // Локальная база может использоваться только как кэш
        // если есть активная синхронизация с Airtable
        return self::canReadLocalData();
    }
    
    /**
     * ПРОВЕРКА: Можно ли создавать тестовые данные?
     * НЕТ! Согласно Filtering.md все данные только из Airtable.
     */
    public static function canCreateTestData() {
        return false; // НИКОГДА!
    }
    
    /**
     * ПРОВЕРКА: Можно ли использовать методы getRegions(), getCities(), getPois()?
     * Только если локальная база синхронизирована с Airtable.
     */
    public static function canUseLocalMethods() {
        return self::canUseLocalCache();
    }
    
    /**
     * ВЫБРОСИТЬ ОШИБКУ если пытаются использовать локальные данные без Airtable
     */
    public static function enforceAirtableOnly() {
        if (!self::canReadLocalData()) {
            throw new Exception(
                'VIOLATION OF FILTERING.MD: ' .
                'Cannot use local data without Airtable synchronization. ' .
                'All data must come from Airtable. ' .
                'Configure AIRTABLE_TOKEN environment variable or secret file.'
            );
        }
    }
    
    /**
     * ВЫБРОСИТЬ ОШИБКУ если пытаются создать локальные данные
     */
    public static function enforceNoLocalCreation() {
        throw new Exception(
            'VIOLATION OF FILTERING.MD: ' .
            'Cannot create local data. ' .
            'All data must be created in Airtable first, then synchronized locally.'
        );
    }
    
    /**
     * ВЫБРОСИТЬ ОШИБКУ если пытаются создать тестовые данные
     */
    public static function enforceNoTestData() {
        throw new Exception(
            'VIOLATION OF FILTERING.MD: ' .
            'Cannot create test data locally. ' .
            'All data must come from Airtable. ' .
            'Create test data in Airtable instead.'
        );
    }
}
?>
