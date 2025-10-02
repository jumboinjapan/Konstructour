<?php
// api/airtable-data-source.php
// ЕДИНСТВЕННЫЙ источник данных из Airtable согласно Filtering.md

require_once 'secret-airtable.php';
require_once 'config.php';

class AirtableDataSource {
    private $config;
    private $token;
    
    public function __construct() {
        $this->config = require 'config.php';
        $this->token = $this->getAirtableToken();
    }
    
    private function getAirtableToken() {
        // ТОЛЬКО GitHub Secrets - никаких локальных токенов!
        $token = getenv('AIRTABLE_TOKEN') ?: getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_API_KEY');
        if ($token) return $token;
        
        // Если токен недоступен, выбрасываем исключение
        throw new Exception('Airtable token not configured. Token must be set via GitHub Secrets (AIRTABLE_TOKEN environment variable). Local development uses fallback demo data.');
    }
    
    private function airtableRequest($tableId, $params = []) {
        $baseId = $this->config['airtable_registry']['baseId'];
        $url = "https://api.airtable.com/v0/{$baseId}/{$tableId}";
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Airtable API error: HTTP {$httpCode} - {$response}");
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            throw new Exception("Invalid JSON response from Airtable");
        }
        
        return $data;
    }
    
    // ЕДИНСТВЕННЫЕ методы для получения данных из Airtable
    public function getRegionsFromAirtable() {
        $data = $this->airtableRequest($this->config['airtable_registry']['tables']['region']['tableId']);
        return $data['records'] ?? [];
    }
    
    public function getCitiesFromAirtable() {
        $data = $this->airtableRequest($this->config['airtable_registry']['tables']['city']['tableId']);
        return $data['records'] ?? [];
    }
    
    public function getPoisFromAirtable() {
        $data = $this->airtableRequest($this->config['airtable_registry']['tables']['poi']['tableId']);
        return $data['records'] ?? [];
    }
    
    // Методы для проверки доступности Airtable
    public function isAirtableAvailable() {
        try {
            $this->getAirtableToken();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function testConnection() {
        try {
            $this->airtableRequest($this->config['airtable_registry']['tables']['region']['tableId'], ['maxRecords' => 1]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
