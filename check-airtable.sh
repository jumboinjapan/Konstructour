#!/bin/bash
# Check Airtable data availability

echo "🔍 Проверка доступности Airtable..."
echo ""

# Airtable configuration
BASE_ID="apppwhjFN82N9zNqm"
TABLES=("tblbSajWkzI8X7M4U:tblbSajWkzI8X7M4U" "tblHaHc9NV0mA8bSa:cities" "tblVCmFcHRpXUT24y:pois")

echo "📊 Проверка таблиц Airtable:"
echo "Base ID: $BASE_ID"
echo ""

for table_info in "${TABLES[@]}"; do
    IFS=':' read -r table_id table_name <<< "$table_info"
    echo "🔍 Проверка таблицы: $table_name (ID: $table_id)"
    
    # Try to access table (HEAD request)
    response=$(curl -s -I "https://api.airtable.com/v0/$BASE_ID/$table_id?pageSize=1" 2>/dev/null)
    http_code=$(echo "$response" | head -n 1 | cut -d' ' -f2)
    
    if [ "$http_code" = "200" ]; then
        echo "✅ Таблица доступна (HTTP 200)"
    elif [ "$http_code" = "401" ]; then
        echo "🔐 Требуется авторизация (HTTP 401)"
    elif [ "$http_code" = "404" ]; then
        echo "❌ Таблица не найдена (HTTP 404)"
    else
        echo "⚠️  Неожиданный ответ (HTTP $http_code)"
    fi
    echo ""
done

echo "💡 Для получения точного количества записей:"
echo "1. Получите токен на https://airtable.com/create/tokens"
echo "2. Установите: export AIRTABLE_PAT='your_token_here'"
echo "3. Запустите: php count-airtable-data.php"
echo ""

echo "🔧 Альтернативные способы:"
echo "- Используйте веб-интерфейс Airtable для подсчета"
echo "- Экспортируйте данные через Airtable UI"
echo "- Используйте Airtable API напрямую с токеном"
echo ""

echo "📋 Информация о таблицах:"
echo "- Regions: $BASE_ID/tblbSajWkzI8X7M4U"
echo "- Cities/Locations: $BASE_ID/tblHaHc9NV0mA8bSa"
echo "- POIs: $BASE_ID/tblVCmFcHRpXUT24y"
