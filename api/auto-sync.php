<?php
// Автоматическая синхронизация с разрешением конфликтов
require_once 'database.php';
require_once 'config.php';
require_once 'sync-logger.php';

class AutoSync {
    private $db;
    private $logger;
    private $baseId;
    private $pat;
    
    public function __construct() {
        $this->db = new Database();
        $this->logger = new SyncLogger();
        $this->baseId = 'apppwhjFN82N9zNqm';
        $this->pat = getenv('AIRTABLE_PAT') ?: 'PLACEHOLDER_FOR_REAL_API_KEY';
    }
    
    public function run() {
        if ($this->pat === 'PLACEHOLDER_FOR_REAL_API_KEY') {
            $this->logger->log('auto_sync', 'system', null, 'error', 'Airtable token not configured');
            return false;
        }
        
        $this->logger->log('auto_sync', 'system', null, 'info', 'Auto sync started');
        
        try {
            // 1. Синхронизация из Airtable в локальную БД
            $airtableToLocal = $this->syncFromAirtable();
            
            // 2. Синхронизация из локальной БД в Airtable
            $localToAirtable = $this->syncToAirtable();
            
            // 3. Разрешение конфликтов
            $conflicts = $this->resolveConflicts();
            
            $this->logger->log('auto_sync', 'system', null, 'success', 
                "Auto sync completed: {$airtableToLocal} from Airtable, {$localToAirtable} to Airtable, {$conflicts} conflicts resolved"
            );
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->log('auto_sync', 'system', null, 'error', $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return false;
        }
    }
    
    private function syncFromAirtable() {
        $count = 0;
        
        try {
            // Синхронизируем регионы
            $regions = $this->fetchAirtableData('tblbSajWkzI8X7M4U');
            foreach ($regions as $record) {
                $data = [
                    'id' => $record['id'],
                    'name_ru' => $record['fields']['Name (RU)'] ?? $record['fields']['Название (RU)'] ?? 'Unknown',
                    'name_en' => $record['fields']['Name (EN)'] ?? $record['fields']['Название (EN)'] ?? null,
                    'business_id' => $record['fields']['ID'] ?? null
                ];
                
                // Проверяем, существует ли запись
                $existing = $this->db->getRegionById($data['id']);
                if ($existing) {
                    // Обновляем только если данные изменились
                    if ($this->hasChanges($existing, $data)) {
                        $this->db->updateRegion($data['id'], $data);
                        $this->logger->log('update', 'region', $data['id'], 'success', 'Updated from Airtable');
                        $count++;
                    }
                } else {
                    $this->db->saveRegion($data);
                    $this->logger->log('create', 'region', $data['id'], 'success', 'Created from Airtable');
                    $count++;
                }
            }
            
        } catch (Exception $e) {
            $this->logger->log('sync_from_airtable', 'system', null, 'error', $e->getMessage());
        }
        
        return $count;
    }
    
    private function syncToAirtable() {
        $count = 0;
        
        try {
            // Получаем локальные данные
            $localRegions = $this->db->getRegions();
            
            foreach ($localRegions as $region) {
                // Проверяем, нужно ли обновить Airtable
                if ($this->needsAirtableUpdate($region)) {
                    $this->updateAirtableRecord('tblbSajWkzI8X7M4U', $region);
                    $this->logger->log('update', 'region', $region['id'], 'success', 'Updated in Airtable');
                    $count++;
                }
            }
            
        } catch (Exception $e) {
            $this->logger->log('sync_to_airtable', 'system', null, 'error', $e->getMessage());
        }
        
        return $count;
    }
    
    private function resolveConflicts() {
        $conflicts = 0;
        
        try {
            // Получаем записи с конфликтами (измененные в обеих системах)
            $conflictRecords = $this->getConflictRecords();
            
            foreach ($conflictRecords as $record) {
                // Применяем стратегию разрешения конфликтов
                $resolution = $this->resolveConflict($record);
                
                if ($resolution) {
                    $this->logger->log('conflict_resolution', $record['type'], $record['id'], 'success', 
                        "Conflict resolved: {$resolution['strategy']}"
                    );
                    $conflicts++;
                }
            }
            
        } catch (Exception $e) {
            $this->logger->log('conflict_resolution', 'system', null, 'error', $e->getMessage());
        }
        
        return $conflicts;
    }
    
    private function getConflictRecords() {
        // Получаем записи, которые были изменены в обеих системах
        // за последние 24 часа
        $stmt = $this->db->getConnection()->prepare("
            SELECT * FROM regions 
            WHERE updated_at > datetime('now', '-24 hours')
            AND (airtable_updated_at IS NULL OR airtable_updated_at < updated_at)
        ");
        
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($record) {
            return array_merge($record, ['type' => 'region']);
        }, $records);
    }
    
    private function resolveConflict($record) {
        // Стратегия разрешения конфликтов:
        // 1. Если запись была изменена в Airtable позже - используем Airtable
        // 2. Если запись была изменена локально позже - используем локальную
        // 3. Если время одинаковое - используем Airtable как источник истины
        
        $airtableRecord = $this->getAirtableRecord($record['type'], $record['id']);
        
        if (!$airtableRecord) {
            return null; // Запись не найдена в Airtable
        }
        
        $airtableTime = $this->getAirtableUpdateTime($airtableRecord);
        $localTime = strtotime($record['updated_at']);
        
        if ($airtableTime > $localTime) {
            // Airtable новее - обновляем локальную запись
            $this->updateLocalFromAirtable($record, $airtableRecord);
            return ['strategy' => 'airtable_wins'];
        } else {
            // Локальная новее - обновляем Airtable
            $this->updateAirtableFromLocal($record);
            return ['strategy' => 'local_wins'];
        }
    }
    
    private function hasChanges($existing, $new) {
        return $existing['name_ru'] !== $new['name_ru'] ||
               $existing['name_en'] !== $new['name_en'] ||
               $existing['business_id'] !== $new['business_id'];
    }
    
    private function needsAirtableUpdate($record) {
        // Проверяем, была ли запись изменена локально после последней синхронизации
        $airtableTime = $record['airtable_updated_at'] ?? '1970-01-01 00:00:00';
        $localTime = $record['updated_at'];
        
        return strtotime($localTime) > strtotime($airtableTime);
    }
    
    private function fetchAirtableData($tableId) {
        $url = "https://api.airtable.com/v0/{$this->baseId}/{$tableId}?maxRecords=100";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->pat,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: " . $httpCode . " - " . $response);
        }
        
        $data = json_decode($response, true);
        return $data['records'] ?? [];
    }
    
    private function updateAirtableRecord($tableId, $record) {
        $url = "https://api.airtable.com/v0/{$this->baseId}/{$tableId}/{$record['id']}";
        
        $fields = [
            'Name (RU)' => $record['name_ru'],
            'ID' => $record['business_id']
        ];
        
        if ($record['name_en']) {
            $fields['Name (EN)'] = $record['name_en'];
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->pat,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode(['fields' => $fields]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Failed to update Airtable record: " . $response);
        }
        
        // Обновляем время синхронизации в локальной БД
        $this->updateSyncTime($record['id'], 'airtable_updated_at');
    }
    
    private function updateSyncTime($id, $field) {
        $stmt = $this->db->getConnection()->prepare("
            UPDATE regions 
            SET {$field} = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
    }
    
    private function getAirtableRecord($type, $id) {
        $tableMap = [
            'region' => 'tblbSajWkzI8X7M4U',
            'city' => 'tblHaHc9NV0mA8bSa',
            'poi' => 'tbl8X7M4U'
        ];
        
        $tableId = $tableMap[$type] ?? null;
        if (!$tableId) return null;
        
        $url = "https://api.airtable.com/v0/{$this->baseId}/{$tableId}/{$id}";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->pat,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) return null;
        
        return json_decode($response, true);
    }
    
    private function getAirtableUpdateTime($record) {
        // Airtable не предоставляет время последнего обновления в API
        // Используем время создания записи как приблизительное
        return strtotime($record['createdTime'] ?? '1970-01-01 00:00:00');
    }
    
    private function updateLocalFromAirtable($localRecord, $airtableRecord) {
        $data = [
            'name_ru' => $airtableRecord['fields']['Name (RU)'] ?? $localRecord['name_ru'],
            'name_en' => $airtableRecord['fields']['Name (EN)'] ?? $localRecord['name_en'],
            'business_id' => $airtableRecord['fields']['ID'] ?? $localRecord['business_id']
        ];
        
        $this->db->updateRegion($localRecord['id'], $data);
        $this->updateSyncTime($localRecord['id'], 'airtable_updated_at');
    }
    
    private function updateAirtableFromLocal($record) {
        $this->updateAirtableRecord('tblbSajWkzI8X7M4U', $record);
        $this->updateSyncTime($record['id'], 'airtable_updated_at');
    }
}

// Запуск автоматической синхронизации
if (php_sapi_name() === 'cli') {
    // Запуск из командной строки
    $autoSync = new AutoSync();
    $result = $autoSync->run();
    echo $result ? "Auto sync completed successfully\n" : "Auto sync failed\n";
    exit($result ? 0 : 1);
} else {
    // Запуск через HTTP
    header('Content-Type: application/json; charset=utf-8');
    
    $autoSync = new AutoSync();
    $result = $autoSync->run();
    
    echo json_encode([
        'ok' => $result,
        'message' => $result ? 'Auto sync completed' : 'Auto sync failed',
        'timestamp' => date('c')
    ]);
}
?>
