<?php
require_once 'database.php';
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

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sleepMs($ms) {
    usleep($ms * 1000);
}

function nowIso() {
    return gmdate('Y-m-d\TH:i:s\Z');
}

function isNewer($a, $b) {
    return strtotime($a) > strtotime($b);
}

class BidirectionalSync {
    private $db;
    private $pdo;
    private $config;
    private $pat;
    private $baseId;
    private $tableId;
    private $rateDelayMs = 220; // ~4.5 req/s
    
    // Поля Airtable
    private $fields = [
        'identifier' => 'Идентификатор',
        'name_ru' => 'Название (RU)', 
        'name_en' => 'Название (EN)',
        'updated_at' => 'updated_at',
        'is_deleted' => 'is_deleted'
    ];
    
    public function __construct() {
        $this->db = new Database();
        $this->pdo = $this->db->getConnection();
        $this->config = include 'config.php';
        
        $this->pat = getenv('AIRTABLE_PAT') ?: 'PLACEHOLDER_FOR_REAL_API_KEY';
        $this->baseId = $this->config['airtable_registry']['baseId'];
        $this->tableId = $this->config['airtable_registry']['tables']['region']['tableId'];
        
        if ($this->pat === 'PLACEHOLDER_FOR_REAL_API_KEY') {
            throw new Exception('Airtable token not configured');
        }
    }
    
    public function sync() {
        try {
            $this->pdo->beginTransaction();
            
            $startedAt = nowIso();
            $lastSync = $this->getLastSyncTime();
            
            // 1. Получаем изменения из Airtable
            $airChanged = $this->fetchAirtableChanges($lastSync);
            $this->processAirtableChanges($airChanged);
            
            // 2. Отправляем локальные изменения в Airtable
            $localChanged = $this->getLocalChanges($lastSync);
            $this->pushLocalChanges($localChanged);
            
            // 3. Обновляем время последней синхронизации
            $this->setLastSyncTime($startedAt);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Bidirectional sync completed',
                'summary' => [
                    'airtable_changes' => count($airChanged),
                    'local_changes' => count($localChanged),
                    'sync_time' => $startedAt
                ]
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    private function getLastSyncTime() {
        $stmt = $this->pdo->prepare("SELECT value FROM sync_state WHERE key = ?");
        $stmt->execute(['last_sync_at']);
        $result = $stmt->fetch();
        return $result ? $result['value'] : '1970-01-01T00:00:00Z';
    }
    
    private function setLastSyncTime($time) {
        $stmt = $this->pdo->prepare("REPLACE INTO sync_state(key, value) VALUES (?, ?)");
        $stmt->execute(['last_sync_at', $time]);
    }
    
    private function fetchAirtableChanges($since) {
        $records = [];
        $offset = null;
        
        do {
            $url = "https://api.airtable.com/v0/{$this->baseId}/{$this->tableId}";
            $params = [
                'pageSize' => 100,
                'offset' => $offset
            ];
            
            // Фильтр по updated_at если поле есть
            if (isset($this->fields['updated_at'])) {
                $params['filterByFormula'] = "VALUE({{$this->fields['updated_at']}}) > VALUE(\"{$since}\")";
            }
            
            $response = $this->makeAirtableRequest($url, $params);
            $data = json_decode($response, true);
            
            if (isset($data['records'])) {
                foreach ($data['records'] as $record) {
                    $fields = $record['fields'];
                    $records[] = [
                        'airtable_id' => $record['id'],
                        'identifier' => $fields[$this->fields['identifier']] ?? null,
                        'name_ru' => $fields[$this->fields['name_ru']] ?? '',
                        'name_en' => $fields[$this->fields['name_en']] ?? '',
                        'updated_at' => $fields[$this->fields['updated_at']] ?? $record['createdTime'],
                        'is_deleted' => isset($fields[$this->fields['is_deleted']]) ? ($fields[$this->fields['is_deleted']] ? 1 : 0) : 0
                    ];
                }
            }
            
            $offset = $data['offset'] ?? null;
            sleepMs($this->rateDelayMs);
            
        } while ($offset);
        
        return array_filter($records, function($r) {
            return !empty($r['identifier']);
        });
    }
    
    private function processAirtableChanges($changes) {
        foreach ($changes as $change) {
            $existing = $this->findByIdentifier($change['identifier']);
            
            if (!$existing) {
                // Создаем новую запись
                $this->insertRegion($change);
            } else {
                // Проверяем конфликт
                if (isNewer($change['updated_at'], $existing['updated_at']) || 
                    ($change['updated_at'] === $existing['updated_at'] && $change['airtable_id'])) {
                    // Airtable новее или равенство (Airtable побеждает)
                    $this->updateRegion($existing['id'], $change);
                }
            }
        }
    }
    
    private function getLocalChanges($since) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM regions 
            WHERE updated_at > ? 
            ORDER BY updated_at ASC
        ");
        $stmt->execute([$since]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function pushLocalChanges($changes) {
        foreach ($changes as $change) {
            try {
                $airtableId = $this->createOrUpdateInAirtable($change);
                if ($airtableId && $airtableId !== $change['airtable_id']) {
                    // Обновляем airtable_id в локальной БД
                    $stmt = $this->pdo->prepare("UPDATE regions SET airtable_id = ? WHERE id = ?");
                    $stmt->execute([$airtableId, $change['id']]);
                }
            } catch (Exception $e) {
                error_log("Airtable upsert error: " . $e->getMessage());
            }
        }
    }
    
    private function createOrUpdateInAirtable($record) {
        $fields = [
            $this->fields['identifier'] => $record['identifier'],
            $this->fields['name_ru'] => $record['name_ru'],
            $this->fields['name_en'] => $record['name_en'],
            $this->fields['updated_at'] => $record['updated_at'],
            $this->fields['is_deleted'] => (bool)$record['is_deleted']
        ];
        
        if ($record['airtable_id']) {
            // Обновляем существующую запись
            $url = "https://api.airtable.com/v0/{$this->baseId}/{$this->tableId}/{$record['airtable_id']}";
            $response = $this->makeAirtableRequest($url, ['fields' => $fields], 'PATCH');
            $data = json_decode($response, true);
            return $data['id'] ?? $record['airtable_id'];
        } else {
            // Создаем новую запись
            $url = "https://api.airtable.com/v0/{$this->baseId}/{$this->tableId}";
            $response = $this->makeAirtableRequest($url, ['fields' => $fields], 'POST');
            $data = json_decode($response, true);
            return $data['id'] ?? null;
        }
    }
    
    private function makeAirtableRequest($url, $data = [], $method = 'GET') {
        $ch = curl_init();
        
        $headers = [
            "Authorization: Bearer {$this->pat}",
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
    
    private function findByIdentifier($identifier) {
        $stmt = $this->pdo->prepare("SELECT * FROM regions WHERE identifier = ?");
        $stmt->execute([$identifier]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function insertRegion($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO regions (identifier, name_ru, name_en, airtable_id, updated_at, is_deleted)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['identifier'],
            $data['name_ru'],
            $data['name_en'],
            $data['airtable_id'],
            $data['updated_at'],
            $data['is_deleted']
        ]);
    }
    
    private function updateRegion($id, $data) {
        $stmt = $this->pdo->prepare("
            UPDATE regions 
            SET name_ru = ?, name_en = ?, airtable_id = ?, updated_at = ?, is_deleted = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['name_ru'],
            $data['name_en'],
            $data['airtable_id'],
            $data['updated_at'],
            $data['is_deleted'],
            $id
        ]);
    }
}

// Обработка запроса
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $sync = new BidirectionalSync();
        $result = $sync->sync();
        respond(true, $result);
    } catch (Exception $e) {
        respond(false, ['error' => $e->getMessage()], 500);
    }
} else {
    respond(false, ['error' => 'Method not allowed'], 405);
}
?>
