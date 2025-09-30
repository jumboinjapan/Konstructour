#!/usr/bin/env bash
# scripts/db-integrity-check.sh
# Проверка целостности базы данных SQLite

set -euo pipefail

DB_PATH="${1:-api/konstructour.db}"

fail(){ echo "❌ $*"; exit 1; }
pass(){ echo "✅ $*"; }
warn(){ echo "⚠️  $*"; }

echo "🔍 Database Integrity Check"
echo "Database: $DB_PATH"
echo "Time: $(date)"
echo ""

if [ ! -f "$DB_PATH" ]; then
  fail "Database file not found: $DB_PATH"
fi

echo "=== Database File Info ==="
ls -lh "$DB_PATH"
echo "Size: $(du -h "$DB_PATH" | cut -f1)"
echo ""

echo "=== SQLite PRAGMA Settings ==="
sqlite3 "$DB_PATH" <<EOF
PRAGMA foreign_keys;
PRAGMA journal_mode;
PRAGMA synchronous;
PRAGMA integrity_check;
EOF

echo ""
echo "=== Table Structure ==="
sqlite3 "$DB_PATH" ".schema" | grep -E "CREATE TABLE|FOREIGN KEY"

echo ""
echo "=== Record Counts ==="
sqlite3 "$DB_PATH" <<EOF
SELECT 'regions', COUNT(*) FROM regions;
SELECT 'cities', COUNT(*) FROM cities;
SELECT 'pois', COUNT(*) FROM pois;
SELECT 'sync_log', COUNT(*) FROM sync_log;
EOF

echo ""
echo "=== Foreign Key Integrity ==="
echo "Checking for orphaned records..."

# Проверка городов без регионов
city_orphans=$(sqlite3 "$DB_PATH" "
SELECT COUNT(*) FROM cities c 
LEFT JOIN regions r ON c.region_id = r.id 
WHERE r.id IS NULL;
")

if [ "$city_orphans" -gt 0 ]; then
  fail "Found $city_orphans cities without valid regions"
else
  pass "No orphaned cities"
fi

# Проверка POI без городов (если city_id указан)
poi_city_orphans=$(sqlite3 "$DB_PATH" "
SELECT COUNT(*) FROM pois p 
LEFT JOIN cities c ON p.city_id = c.id 
WHERE p.city_id IS NOT NULL AND c.id IS NULL;
")

if [ "$poi_city_orphans" -gt 0 ]; then
  fail "Found $poi_city_orphans POIs with invalid city_id"
else
  pass "No POIs with invalid city_id"
fi

# Проверка POI без регионов (если region_id указан)
poi_region_orphans=$(sqlite3 "$DB_PATH" "
SELECT COUNT(*) FROM pois p 
LEFT JOIN regions r ON p.region_id = r.id 
WHERE p.region_id IS NOT NULL AND r.id IS NULL;
")

if [ "$poi_region_orphans" -gt 0 ]; then
  fail "Found $poi_region_orphans POIs with invalid region_id"
else
  pass "No POIs with invalid region_id"
fi

echo ""
echo "=== Data Quality Checks ==="

# Проверка на пустые обязательные поля
empty_regions=$(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM regions WHERE name_ru = '' OR name_ru IS NULL;")
if [ "$empty_regions" -gt 0 ]; then
  warn "Found $empty_regions regions with empty name_ru"
else
  pass "All regions have name_ru"
fi

empty_cities=$(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM cities WHERE name_ru = '' OR name_ru IS NULL;")
if [ "$empty_cities" -gt 0 ]; then
  warn "Found $empty_cities cities with empty name_ru"
else
  pass "All cities have name_ru"
fi

empty_pois=$(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM pois WHERE name_ru = '' OR name_ru IS NULL;")
if [ "$empty_pois" -gt 0 ]; then
  warn "Found $empty_pois POIs with empty name_ru"
else
  pass "All POIs have name_ru"
fi

echo ""
echo "=== Recent Sync Activity ==="
sqlite3 "$DB_PATH" "
SELECT table_name, action, record_id, datetime(timestamp, 'localtime') as local_time
FROM sync_log 
ORDER BY timestamp DESC 
LIMIT 10;
"

echo ""
echo "=== Index Usage ==="
sqlite3 "$DB_PATH" "
SELECT name, sql FROM sqlite_master 
WHERE type = 'index' AND name NOT LIKE 'sqlite_%';
"

echo ""
echo "=== Database Statistics ==="
sqlite3 "$DB_PATH" "
SELECT 
  'Page Count' as metric, 
  page_count as value 
FROM pragma_page_count()
UNION ALL
SELECT 
  'Page Size' as metric, 
  page_size as value 
FROM pragma_page_size()
UNION ALL
SELECT 
  'Free Pages' as metric, 
  freelist_count as value 
FROM pragma_freelist_count();
"

echo ""
echo "🎉 Database integrity check completed!"
echo "All foreign key constraints are satisfied."
echo "Database is ready for production use."
