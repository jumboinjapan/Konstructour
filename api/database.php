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
        
        // POI table (обновлённая схема для синхронизации с Airtable)
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
                prefecture_ru TEXT,
                prefecture_en TEXT,
                categories_ru TEXT,
                categories_en TEXT,
                description_ru TEXT,
                description_en TEXT,
                website TEXT,
                working_hours TEXT,
                notes TEXT,
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
        // Сначала пытаемся обновить существующую запись
        $stmt = $this->db->prepare("
            UPDATE regions 
            SET name_ru = ?, name_en = ?, business_id = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $data['name_ru'],
            $data['name_en'] ?? null,
            $data['business_id'] ?? null,
            $data['id']
        ]);
        
        // Если запись не была обновлена, вставляем новую
        if ($stmt->rowCount() === 0) {
            $stmt = $this->db->prepare("
                INSERT INTO regions (id, name_ru, name_en, business_id, created_at, updated_at) 
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            return $stmt->execute([
                $data['id'],
                $data['name_ru'],
                $data['name_en'] ?? null,
                $data['business_id'] ?? null
            ]);
        }
        
        return true;
    }
    
    // Cities methods
    public function getCitiesByRegion($regionId) {
        $stmt = $this->db->prepare("SELECT * FROM cities WHERE region_id = ? ORDER BY name_ru");
        $stmt->execute([$regionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCityById($id) {
        $stmt = $this->db->prepare("SELECT * FROM cities WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAllCities() {
        $stmt = $this->db->prepare("SELECT * FROM cities ORDER BY name_ru");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCity($id) {
        $stmt = $this->db->prepare("SELECT * FROM cities WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function saveCity($data) {
        // Сначала пытаемся обновить существующую запись
        $stmt = $this->db->prepare("
            UPDATE cities 
            SET name_ru = ?, name_en = ?, business_id = ?, type = ?, region_id = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $data['name_ru'],
            $data['name_en'] ?? null,
            $data['business_id'] ?? null,
            $data['type'] ?? 'city',
            $data['region_id'],
            $data['id']
        ]);
        
        // Если запись не была обновлена, вставляем новую
        if ($stmt->rowCount() === 0) {
            $stmt = $this->db->prepare("
                INSERT INTO cities (id, name_ru, name_en, business_id, type, region_id, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
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
        
        return true;
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
        // Обработка массивов категорий
        $cats_ru_json = isset($data['categories_ru']) && is_array($data['categories_ru']) 
            ? json_encode($data['categories_ru'], JSON_UNESCAPED_UNICODE) 
            : null;
        $cats_en_json = isset($data['categories_en']) && is_array($data['categories_en']) 
            ? json_encode($data['categories_en'], JSON_UNESCAPED_UNICODE) 
            : null;
        
        // Для обратной совместимости: category = первая категория из массива
        $category = null;
        if (isset($data['categories_ru']) && is_array($data['categories_ru']) && count($data['categories_ru']) > 0) {
            $category = $data['categories_ru'][0];
        } elseif (isset($data['category'])) {
            $category = $data['category'];
        }
        
        // Сначала пытаемся обновить существующую запись по business_id
        if (isset($data['business_id']) && $data['business_id']) {
            $stmt = $this->db->prepare("
                UPDATE pois 
                SET name_ru = ?, name_en = ?, category = ?, place_id = ?, published = ?, 
                    city_id = ?, region_id = ?, description = ?, 
                    latitude = ?, longitude = ?,
                    prefecture_ru = ?, prefecture_en = ?,
                    categories_ru = ?, categories_en = ?,
                    description_ru = ?, description_en = ?,
                    website = ?, working_hours = ?, notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE business_id = ?
            ");
            $stmt->execute([
                $data['name_ru'],
                $data['name_en'] ?? null,
                $category,
                $data['place_id'] ?? null,
                ($data['published'] ?? false) ? 1 : 0,
                $data['city_id'],
                is_array($data['region_id']) ? $data['region_id'][0] ?? null : $data['region_id'],
                $data['description'] ?? null,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['prefecture_ru'] ?? null,
                $data['prefecture_en'] ?? null,
                $cats_ru_json,
                $cats_en_json,
                $data['description_ru'] ?? null,
                $data['description_en'] ?? null,
                $data['website'] ?? null,
                $data['working_hours'] ?? null,
                $data['notes'] ?? null,
                $data['business_id']
            ]);
            
            // Если запись была обновлена, возвращаем успех
            if ($stmt->rowCount() > 0) {
                return true;
            }
        }
        
        // Если запись не была обновлена по business_id, это ошибка
        // Согласно Filtering.md, мы используем только business_id для логики
        if (isset($data['business_id']) && $data['business_id']) {
            throw new Exception("POI с business_id '{$data['business_id']}' не найден для обновления. Проверьте корректность ID.");
        }
        
        // Если запись не была обновлена, вставляем новую
        $stmt = $this->db->prepare("
            INSERT INTO pois (
                id, name_ru, name_en, category, place_id, published, business_id, 
                city_id, region_id, description, latitude, longitude,
                prefecture_ru, prefecture_en, categories_ru, categories_en,
                description_ru, description_en, website, working_hours, notes,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        return $stmt->execute([
            $data['id'],
            $data['name_ru'],
            $data['name_en'] ?? null,
            $category,
            $data['place_id'] ?? null,
            ($data['published'] ?? false) ? 1 : 0,
            $data['business_id'] ?? null,
            $data['city_id'],
            is_array($data['region_id']) ? $data['region_id'][0] ?? null : $data['region_id'],
            $data['description'] ?? null,
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['prefecture_ru'] ?? null,
            $data['prefecture_en'] ?? null,
            $cats_ru_json,
            $cats_en_json,
            $data['description_ru'] ?? null,
            $data['description_en'] ?? null,
            $data['website'] ?? null,
            $data['working_hours'] ?? null,
            $data['notes'] ?? null
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
    
    // Get city counts by region
    public function getCityCountsByRegion() {
        $stmt = $this->db->query("
            SELECT region_id, COUNT(*) as count 
            FROM cities 
            GROUP BY region_id
        ");
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['region_id']] = (int)$row['count'];
        }
        return $result;
    }
    
    // Get POI counts by city for a specific region
    public function getPoiCountsByCity($regionId) {
        $stmt = $this->db->prepare("
            SELECT c.id as city_id, COUNT(p.id) as count 
            FROM cities c
            LEFT JOIN pois p ON c.id = p.city_id
            WHERE c.region_id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$regionId]);
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['city_id']] = (int)$row['count'];
        }
        return $result;
    }
    
    private function getLastSyncTime() {
        $stmt = $this->db->query("SELECT MAX(timestamp) as last_sync FROM sync_log");
        $result = $stmt->fetch();
        return $result['last_sync'] ?? null;
    }
    
    // Поиск Airtable ID по business_id (для Filtering.md принципов)
    public function getRegionAirtableIdByBusinessId($businessId) {
        $stmt = $this->db->prepare("SELECT id FROM regions WHERE business_id = ?");
        $stmt->execute([$businessId]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    public function getCityAirtableIdByBusinessId($businessId) {
        $stmt = $this->db->prepare("SELECT id FROM cities WHERE business_id = ?");
        $stmt->execute([$businessId]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    public function getPoiAirtableIdByBusinessId($businessId) {
        $stmt = $this->db->prepare("SELECT id FROM pois WHERE business_id = ?");
        $stmt->execute([$businessId]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
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
