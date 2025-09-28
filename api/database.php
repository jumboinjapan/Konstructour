<?php
// Database connection and management
class Database {
    private $db;
    
    public function __construct() {
        $this->db = new PDO('sqlite:' . __DIR__ . '/konstructour.db');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initTables();
    }
    
    private function initTables() {
        // Regions table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS regions (
                id TEXT PRIMARY KEY,
                name_ru TEXT NOT NULL,
                name_en TEXT,
                business_id TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Cities table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS cities (
                id TEXT PRIMARY KEY,
                name_ru TEXT NOT NULL,
                name_en TEXT,
                business_id TEXT,
                type TEXT DEFAULT 'city',
                region_id TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (region_id) REFERENCES regions(id)
            )
        ");
        
        // POI table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS pois (
                id TEXT PRIMARY KEY,
                name_ru TEXT NOT NULL,
                name_en TEXT,
                category TEXT,
                place_id TEXT,
                published BOOLEAN DEFAULT 0,
                business_id TEXT,
                city_id TEXT,
                region_id TEXT,
                description TEXT,
                latitude REAL,
                longitude REAL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (city_id) REFERENCES cities(id),
                FOREIGN KEY (region_id) REFERENCES regions(id)
            )
        ");
        
        // Tickets table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                poi_id TEXT,
                category TEXT,
                price INTEGER,
                currency TEXT DEFAULT 'JPY',
                note TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (poi_id) REFERENCES pois(id)
            )
        ");
        
        // Sync log table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS sync_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                table_name TEXT,
                action TEXT,
                record_id TEXT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    // Regions methods
    public function getRegions() {
        $stmt = $this->db->query("SELECT * FROM regions ORDER BY name_ru");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getRegion($id) {
        $stmt = $this->db->prepare("SELECT * FROM regions WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function saveRegion($data) {
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO regions (id, name_ru, name_en, business_id, updated_at) 
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        return $stmt->execute([
            $data['id'],
            $data['name_ru'],
            $data['name_en'] ?? null,
            $data['business_id'] ?? null
        ]);
    }
    
    // Cities methods
    public function getCitiesByRegion($regionId) {
        $stmt = $this->db->prepare("SELECT * FROM cities WHERE region_id = ? ORDER BY name_ru");
        $stmt->execute([$regionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCity($id) {
        $stmt = $this->db->prepare("SELECT * FROM cities WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function saveCity($data) {
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO cities (id, name_ru, name_en, business_id, type, region_id, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        return $stmt->execute([
            $data['id'],
            $data['name_ru'],
            $data['name_en'] ?? null,
            $data['business_id'] ?? null,
            $data['type'] ?? 'city',
            $data['region_id']
        ]);
    }
    
    // POI methods
    public function getPoisByCity($cityId) {
        $stmt = $this->db->prepare("SELECT * FROM pois WHERE city_id = ? ORDER BY name_ru");
        $stmt->execute([$cityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPoi($id) {
        $stmt = $this->db->prepare("SELECT * FROM pois WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function savePoi($data) {
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO pois (
                id, name_ru, name_en, category, place_id, published, business_id, 
                city_id, region_id, description, latitude, longitude, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        return $stmt->execute([
            $data['id'],
            $data['name_ru'],
            $data['name_en'] ?? null,
            $data['category'] ?? null,
            $data['place_id'] ?? null,
            $data['published'] ? 1 : 0,
            $data['business_id'] ?? null,
            $data['city_id'],
            $data['region_id'],
            $data['description'] ?? null,
            $data['latitude'] ?? null,
            $data['longitude'] ?? null
        ]);
    }
    
    // Statistics
    public function getStats() {
        $regions = $this->db->query("SELECT COUNT(*) as count FROM regions")->fetch()['count'];
        $cities = $this->db->query("SELECT COUNT(*) as count FROM cities")->fetch()['count'];
        $pois = $this->db->query("SELECT COUNT(*) as count FROM pois")->fetch()['count'];
        
        return [
            'regions' => $regions,
            'cities' => $cities,
            'pois' => $pois,
            'last_sync' => $this->getLastSyncTime()
        ];
    }
    
    private function getLastSyncTime() {
        $stmt = $this->db->query("SELECT MAX(timestamp) as last_sync FROM sync_log");
        $result = $stmt->fetch();
        return $result['last_sync'] ?? null;
    }
    
    // Clear all data
    public function clearAll() {
        $this->db->exec("DELETE FROM tickets");
        $this->db->exec("DELETE FROM pois");
        $this->db->exec("DELETE FROM cities");
        $this->db->exec("DELETE FROM regions");
        $this->db->exec("DELETE FROM sync_log");
    }
}
?>
