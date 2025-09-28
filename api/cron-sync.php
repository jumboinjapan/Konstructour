<?php
// Cron job for automatic sync
require_once 'sync-airtable.php';

// Run sync
$results = syncFromAirtable();

// Log results
$logFile = __DIR__ . '/sync.log';
$logEntry = date('Y-m-d H:i:s') . " - Sync completed: " . json_encode($results) . "\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Send notification if errors
if (!empty($results['errors'])) {
    error_log("Airtable sync errors: " . implode(', ', $results['errors']));
}
?>
