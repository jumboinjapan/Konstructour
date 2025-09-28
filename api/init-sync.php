<?php
// Initial sync script - run once to populate database
require_once 'sync-airtable.php';

echo "Starting initial sync...\n";

try {
    $results = syncFromAirtable();
    
    echo "Sync completed successfully!\n";
    echo "Regions: " . $results['regions'] . "\n";
    echo "Cities: " . $results['cities'] . "\n";
    echo "POIs: " . $results['pois'] . "\n";
    
    if (!empty($results['errors'])) {
        echo "Errors:\n";
        foreach ($results['errors'] as $error) {
            echo "- " . $error . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Sync failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
