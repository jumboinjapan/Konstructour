#!/bin/bash
# Check why system shows "Connected" status

echo "🔍 Проверка причин показа статуса 'Подключено'..."
echo ""

# Check if there's a real token somewhere
echo "1. Проверка переменных окружения:"
echo "AIRTABLE_PAT: ${AIRTABLE_PAT:-NOT_SET}"
echo "AIRTABLE_API_KEY: ${AIRTABLE_API_KEY:-NOT_SET}"
echo ""

# Check config files
echo "2. Проверка конфигурационных файлов:"
if [ -f "api/config.php" ]; then
    echo "api/config.php: EXISTS"
    # Look for any pat- tokens
    if grep -q "pat-" api/config.php; then
        echo "  Found pat- token in config.php"
    else
        echo "  No pat- token found in config.php"
    fi
else
    echo "api/config.php: NOT FOUND"
fi

if [ -f "api/config.sample.php" ]; then
    echo "api/config.sample.php: EXISTS"
else
    echo "api/config.sample.php: NOT FOUND"
fi
echo ""

# Test whoami with placeholder token
echo "3. Тест whoami с плейсхолдером 'pat...':"
response=$(curl -s -w "HTTPSTATUS:%{http_code}" \
    -H "Authorization: Bearer pat..." \
    "https://api.airtable.com/v0/meta/whoami" 2>/dev/null)

http_code=$(echo "$response" | tr -d '\n' | sed -e 's/.*HTTPSTATUS://')
body=$(echo "$response" | sed -e 's/HTTPSTATUS:.*//g')

echo "HTTP Code: $http_code"
if [ "$http_code" = "200" ]; then
    echo "✅ whoami успешен с плейсхолдером!"
    echo "Body: $body"
elif [ "$http_code" = "401" ]; then
    echo "❌ 401 Unauthorized (ожидаемо)"
else
    echo "⚠️  Неожиданный код: $http_code"
fi
echo ""

# Test with real-looking token
echo "4. Тест whoami с токеном 'pat1234567890abcdef':"
response=$(curl -s -w "HTTPSTATUS:%{http_code}" \
    -H "Authorization: Bearer pat1234567890abcdef" \
    "https://api.airtable.com/v0/meta/whoami" 2>/dev/null)

http_code=$(echo "$response" | tr -d '\n' | sed -e 's/.*HTTPSTATUS://')
body=$(echo "$response" | sed -e 's/HTTPSTATUS:.*//g')

echo "HTTP Code: $http_code"
if [ "$http_code" = "200" ]; then
    echo "✅ whoami успешен с тестовым токеном!"
    echo "Body: $body"
elif [ "$http_code" = "401" ]; then
    echo "❌ 401 Unauthorized (ожидаемо)"
else
    echo "⚠️  Неожиданный код: $http_code"
fi
echo ""

echo "💡 Возможные причины показа 'Подключено':"
echo "1. Кэширование старого статуса в localStorage"
echo "2. Наличие валидного токена в другом месте"
echo "3. whoami проходит, но таблицы недоступны"
echo "4. Ошибка в логике проверки статуса"
echo ""

echo "🔧 Для исправления:"
echo "1. Очистите localStorage в браузере"
echo "2. Проверьте все источники токенов"
echo "3. Обновите страницу (Ctrl+F5)"
echo "4. Проверьте консоль браузера на ошибки"
