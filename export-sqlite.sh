#!/bin/bash
# Export data from SQLite database using sqlite3 command

echo "🚀 Экспорт данных из SQLite базы данных..."
echo ""

# Create exports directory
mkdir -p exports
timestamp=$(date '+%Y-%m-%d_%H-%M-%S')

# Check if database exists
if [ ! -f "api/konstructour.db" ]; then
    echo "❌ База данных не найдена: api/konstructour.db"
    exit 1
fi

echo "📊 Экспорт регионов..."
sqlite3 api/konstructour.db -header -csv "SELECT * FROM regions ORDER BY name_ru;" > "exports/regions_${timestamp}.csv"
regions_count=$(sqlite3 api/konstructour.db "SELECT COUNT(*) FROM regions;")
echo "✅ Регионы: $regions_count записей"

echo "📊 Экспорт городов..."
sqlite3 api/konstructour.db -header -csv "SELECT * FROM cities ORDER BY name_ru;" > "exports/cities_${timestamp}.csv"
cities_count=$(sqlite3 api/konstructour.db "SELECT COUNT(*) FROM cities;")
echo "✅ Города: $cities_count записей"

echo "📊 Экспорт POI..."
sqlite3 api/konstructour.db -header -csv "SELECT * FROM pois ORDER BY name_ru;" > "exports/pois_${timestamp}.csv"
pois_count=$(sqlite3 api/konstructour.db "SELECT COUNT(*) FROM pois;")
echo "✅ POI: $pois_count записей"

echo "📊 Экспорт билетов..."
sqlite3 api/konstructour.db -header -csv "SELECT * FROM tickets ORDER BY created_at;" > "exports/tickets_${timestamp}.csv"
tickets_count=$(sqlite3 api/konstructour.db "SELECT COUNT(*) FROM tickets;")
echo "✅ Билеты: $tickets_count записей"

echo "📊 Экспорт лога синхронизации..."
sqlite3 api/konstructour.db -header -csv "SELECT * FROM sync_log ORDER BY timestamp;" > "exports/sync_log_${timestamp}.csv"
sync_count=$(sqlite3 api/konstructour.db "SELECT COUNT(*) FROM sync_log;")
echo "✅ Лог синхронизации: $sync_count записей"

# Create summary file
cat > "exports/export_summary_${timestamp}.txt" << EOF
Экспорт данных Konstructour
Дата: $(date)
База данных: api/konstructour.db

Сводка:
- Регионы: $regions_count записей
- Города: $cities_count записей  
- POI: $pois_count записей
- Билеты: $tickets_count записей
- Лог синхронизации: $sync_count записей

Файлы:
- regions_${timestamp}.csv
- cities_${timestamp}.csv
- pois_${timestamp}.csv
- tickets_${timestamp}.csv
- sync_log_${timestamp}.csv
EOF

echo ""
echo "🎉 Экспорт завершен успешно!"
echo "📁 Файлы сохранены в папке: exports/"
echo "📄 Сводка: exports/export_summary_${timestamp}.txt"
echo ""
echo "📈 Итого экспортировано:"
echo "- Регионы: $regions_count"
echo "- Города: $cities_count"
echo "- POI: $pois_count"
echo "- Билеты: $tickets_count"
echo "- Лог: $sync_count"
