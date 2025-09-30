<?php
require_once __DIR__ . '/db.php';
$db = konstructour_db();
$path = __DIR__ . '/konstructour.db';
$exists = file_exists($path);
$size = $exists ? filesize($path) : 0;

$rc = $db->query("SELECT COUNT(*) FROM regions")->fetchColumn();
$cc = $db->query("SELECT COUNT(*) FROM cities")->fetchColumn();
$pc = $db->query("SELECT COUNT(*) FROM pois")->fetchColumn();

echo "<pre>";
echo "DB path: $path\n";
echo "Exists : " . ($exists?'yes':'no') . "\n";
echo "Size   : $size bytes\n\n";
echo "regions: $rc\ncities : $cc\npois   : $pc\n";
echo "</pre>";
?>
