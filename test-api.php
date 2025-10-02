<?php
// Simple test script for API
require_once 'api/database.php';

echo "Testing API endpoints...\n\n";

try {
    $db = new Database();
    
    // Test regions
    echo "=== REGIONS (VALID ONLY) ===\n";
    $regions = $db->getValidRegions();
    echo "Total valid regions: " . count($regions) . "\n";
    foreach ($regions as $region) {
        echo "- {$region['name_ru']} (ID: {$region['id']}, Business ID: {$region['business_id']})\n";
    }
    
    // Test cities for first region
    if (!empty($regions)) {
        $firstRegion = $regions[0];
        echo "\n=== CITIES FOR {$firstRegion['name_ru']} (VALID ONLY) ===\n";
        $cities = $db->getValidCitiesByRegion($firstRegion['id']);
        echo "Total valid cities: " . count($cities) . "\n";
        foreach ($cities as $city) {
            echo "- {$city['name_ru']} (ID: {$city['id']}, Business ID: {$city['business_id']})\n";
        }
    }
    
    // Test stats
    echo "\n=== STATS ===\n";
    $stats = $db->getStats();
    echo "Regions: {$stats['regions']}\n";
    echo "Cities: {$stats['cities']}\n";
    echo "POIs: {$stats['pois']}\n";
    
    $cityCounts = $db->getCityCountsByRegion();
    echo "\nCity counts by region:\n";
    foreach ($cityCounts as $regionId => $count) {
        $region = $db->getRegion($regionId);
        $regionName = $region ? $region['name_ru'] : "Unknown";
        echo "- {$regionName}: {$count} cities\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
