<?php
// api/airtable-api.php
// API который работает ТОЛЬКО с Airtable согласно Filtering.md

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once 'airtable-data-source.php';

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $airtable = new AirtableDataSource();
    
    // Проверяем доступность Airtable
    if (!$airtable->isAirtableAvailable()) {
        respond(false, [
            'error' => 'Airtable not configured',
            'message' => 'Airtable token not found. Configure AIRTABLE_TOKEN environment variable or secret file.'
        ], 503);
    }
    
    if (!$airtable->testConnection()) {
        respond(false, [
            'error' => 'Airtable connection failed',
            'message' => 'Cannot connect to Airtable. Check token and network.'
        ], 503);
    }
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'regions':
            $regions = $airtable->getRegionsFromAirtable();
            $processedRegions = [];
            
            foreach ($regions as $record) {
                $fields = $record['fields'] ?? [];
                $businessId = $fields['REGION ID'] ?? null;
                
                if (!$businessId || !preg_match('/^REG-\d+$/', $businessId)) {
                    continue; // Пропускаем записи без корректного business_id
                }
                
                $processedRegions[] = [
                    'id' => $record['id'],
                    'business_id' => $businessId,
                    'name_ru' => $fields['Name (RU)'] ?? 'Неизвестно',
                    'name_en' => $fields['Name (EN)'] ?? 'Unknown'
                ];
            }
            
            respond(true, ['items' => $processedRegions]);
            break;
            
        case 'cities':
            $regionId = $_GET['region_id'] ?? '';
            if (!$regionId) {
                respond(false, ['error' => 'Region ID required'], 400);
            }
            
            if (!preg_match('/^REG-\d+$/', $regionId)) {
                respond(false, ['error' => 'Invalid region ID format'], 400);
            }
            
            $cities = $airtable->getCitiesFromAirtable();
            $processedCities = [];
            
            foreach ($cities as $record) {
                $fields = $record['fields'] ?? [];
                $businessId = $fields['CITY ID'] ?? null;
                $regionBusinessId = $fields['Region ID'] ?? null;
                
                if (!$businessId || !preg_match('/^(CTY|LOC)-\d+$/', $businessId)) {
                    continue;
                }
                
                if ($regionBusinessId !== $regionId) {
                    continue; // Фильтруем по региону
                }
                
                $processedCities[] = [
                    'id' => $record['id'],
                    'business_id' => $businessId,
                    'name_ru' => $fields['Name (RU)'] ?? 'Неизвестно',
                    'name_en' => $fields['Name (EN)'] ?? 'Unknown',
                    'region_id' => $regionBusinessId
                ];
            }
            
            respond(true, ['items' => $processedCities]);
            break;
            
        case 'pois':
            $cityId = $_GET['city_id'] ?? '';
            if (!$cityId) {
                respond(false, ['error' => 'City ID required'], 400);
            }
            
            if (!preg_match('/^(CTY|LOC)-\d+$/', $cityId)) {
                respond(false, ['error' => 'Invalid city ID format'], 400);
            }
            
            $pois = $airtable->getPoisFromAirtable();
            $processedPois = [];
            
            foreach ($pois as $record) {
                $fields = $record['fields'] ?? [];
                $businessId = $fields['POI ID'] ?? null;
                $cityBusinessId = $fields['City Location'] ?? null;
                
                if (!$businessId || !preg_match('/^POI-\d+$/', $businessId)) {
                    continue;
                }
                
                if ($cityBusinessId !== $cityId) {
                    continue; // Фильтруем по городу
                }
                
                $processedPois[] = [
                    'id' => $record['id'],
                    'business_id' => $businessId,
                    'name_ru' => $fields['POI Name (RU)'] ?? 'Неизвестно',
                    'name_en' => $fields['POI Name (EN)'] ?? 'Unknown',
                    'city_id' => $cityBusinessId
                ];
            }
            
            respond(true, ['items' => $processedPois]);
            break;
            
        case 'health':
            respond(true, [
                'message' => 'Airtable API is working',
                'airtable_available' => true,
                'connection_test' => true
            ]);
            break;
            
        default:
            respond(false, ['error' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    respond(false, [
        'error' => $e->getMessage(),
        'message' => 'Airtable API error'
    ], 500);
}
?>
