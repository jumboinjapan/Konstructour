<?php
// Система логирования синхронизации
require_once 'database.php';

class SyncLogger {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        $this->createLogTable();
    }
    
    private function createLogTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS sync_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(20) NOT NULL,
            entity_id VARCHAR(100),
            status VARCHAR(20) NOT NULL,
            message TEXT,
            details TEXT,
            user_agent TEXT,
            ip_address VARCHAR(45)
        )";
        
        $this->db->getConnection()->exec($sql);
    }
    
    public function log($action, $entityType, $entityId, $status, $message, $details = null) {
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO sync_logs (action, entity_type, entity_id, status, message, details, user_agent, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $action,
            $entityType,
            $entityId,
            $status,
            $message,
            $details ? json_encode($details) : null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
    
    public function getLogs($limit = 100, $offset = 0) {
        $stmt = $this->db->getConnection()->prepare("
            SELECT * FROM sync_logs 
            ORDER BY timestamp DESC 
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getSyncStats() {
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                action,
                status,
                COUNT(*) as count,
                MAX(timestamp) as last_occurrence
            FROM sync_logs 
            WHERE timestamp > datetime('now', '-24 hours')
            GROUP BY action, status
            ORDER BY last_occurrence DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getRecentErrors($limit = 10) {
        $stmt = $this->db->getConnection()->prepare("
            SELECT * FROM sync_logs 
            WHERE status = 'error' 
            ORDER BY timestamp DESC 
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function cleanup($days = 30) {
        $stmt = $this->db->getConnection()->prepare("
            DELETE FROM sync_logs 
            WHERE timestamp < datetime('now', '-{$days} days')
        ");
        
        return $stmt->execute();
    }
}

// API endpoints для логирования
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $logger = new SyncLogger();
    
    header('Content-Type: application/json; charset=utf-8');
    
    switch ($action) {
        case 'logs':
            $limit = intval($_GET['limit'] ?? 100);
            $offset = intval($_GET['offset'] ?? 0);
            $logs = $logger->getLogs($limit, $offset);
            echo json_encode(['ok' => true, 'logs' => $logs]);
            break;
            
        case 'stats':
            $stats = $logger->getSyncStats();
            echo json_encode(['ok' => true, 'stats' => $stats]);
            break;
            
        case 'errors':
            $limit = intval($_GET['limit'] ?? 10);
            $errors = $logger->getRecentErrors($limit);
            echo json_encode(['ok' => true, 'errors' => $errors]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid action']);
    }
}
?>
