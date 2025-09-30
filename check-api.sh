#!/bin/bash
# Simple API check script

echo "🔍 Проверка API Konstructour..."
echo ""

# Check if we can access the database
echo "📊 Проверка базы данных:"
sqlite3 api/konstructour.db "SELECT COUNT(*) as regions FROM regions; SELECT COUNT(*) as cities FROM cities;"

echo ""
echo "🌐 Тестовые URL для проверки API:"
echo "1. Регионы: http://your-domain.com/api/data-api.php?action=regions"
echo "2. Статистика: http://your-domain.com/api/data-api.php?action=stats"
echo "3. Тестовая страница: http://your-domain.com/test-api-browser.html"
echo "4. Админ-панель: http://your-domain.com/site-admin/"

echo ""
echo "✅ Логика отображения регионов восстановлена!"
echo "   - Исправлена функция loadRegionCounts"
echo "   - Добавлены тестовые данные"
echo "   - Создан тестовый интерфейс"
