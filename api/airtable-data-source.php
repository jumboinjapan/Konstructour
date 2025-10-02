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
        // В продакшене токен приходит из GitHub Secrets через переменные окружения
        $token = getenv('AIRTABLE_TOKEN') ?: getenv('AIRTABLE_PAT') ?: getenv('AIRTABLE_API_KEY');
        if ($token) return $token;
        
        // Для локальной разработки используем токен из config.php
        $configToken = $this->config['airtable']['api_key'] ?? null;
        if ($configToken && $configToken !== 'patTest123456789') {
            return $configToken;
        }
        
        throw new Exception('Airtable token not configured. In production, set AIRTABLE_TOKEN environment variable from GitHub Secrets. For local development, configure real token in config.php');
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
