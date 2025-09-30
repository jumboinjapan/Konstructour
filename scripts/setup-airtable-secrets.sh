#!/usr/bin/env bash
# setup-airtable-secrets.sh
# Полная настройка Airtable секретов на продакшн сервере

set -euo pipefail

# Конфигурация
SECRETS_DIR="/var/konstructour/secrets"
SECRETS_FILE="$SECRETS_DIR/airtable.json"
PAT="patYOUR_TOKEN_HERE"

echo "=== Настройка Airtable секретов ==="
echo

# Определение пользователя веб-сервера
echo "1. Определение пользователя веб-сервера..."
WEB_USER=""
if command -v apache2 >/dev/null 2>&1; then
    WEB_USER=$(ps aux | grep -E '[a]pache2|[h]ttpd' | head -1 | awk '{print $1}' | grep -v root || echo "www-data")
    echo "   Apache обнаружен, пользователь: $WEB_USER"
elif command -v nginx >/dev/null 2>&1; then
    WEB_USER=$(ps aux | grep -E '[n]ginx' | head -1 | awk '{print $1}' | grep -v root || echo "www-data")
    echo "   Nginx обнаружен, пользователь: $WEB_USER"
else
    WEB_USER="www-data"
    echo "   Веб-сервер не определен, используем: $WEB_USER"
fi
echo

# Создание каталога и файла
echo "2. Создание каталога и файла секретов..."
sudo mkdir -p "$SECRETS_DIR"

sudo bash -c "cat > $SECRETS_FILE << 'EOF'
{
  \"current\": { \"token\": null, \"since\": null },
  \"next\":    { \"token\": null, \"since\": null }
}
EOF"

echo "   ✅ Файл создан: $SECRETS_FILE"
echo

# Установка прав доступа
echo "3. Установка прав доступа..."
sudo chown "$WEB_USER:$WEB_USER" "$SECRETS_FILE"
sudo chmod 600 "$SECRETS_FILE"
sudo chown "$WEB_USER:$WEB_USER" "$SECRETS_DIR"
sudo chmod 700 "$SECRETS_DIR"

echo "   ✅ Права установлены: $WEB_USER:$WEB_USER, 600/700"
echo

# Проверка результата
echo "4. Проверка результата..."
echo "   Права файла:"
ls -l "$SECRETS_FILE"

if command -v namei >/dev/null 2>&1; then
    echo "   Путь доступа:"
    namei -l "$SECRETS_FILE"
else
    echo "   namei не установлен, проверка пропущена"
fi
echo

# Проверка доступа
echo "5. Проверка доступа..."
if sudo -u "$WEB_USER" test -r "$SECRETS_FILE" 2>/dev/null; then
    echo "   ✅ Пользователь $WEB_USER может читать файл"
else
    echo "   ❌ Пользователь $WEB_USER НЕ может читать файл"
    echo "   Попробуйте: sudo chown -R $WEB_USER:$WEB_USER $SECRETS_DIR"
    exit 1
fi
echo

# Запрос админ токена
echo "6. Загрузка PAT токена..."
echo "   Введите ваш ADMIN_TOKEN для X-Admin-Token:"
read -r ADMIN_TOKEN

if [ -z "$ADMIN_TOKEN" ]; then
    echo "   ❌ Админ токен не введен, пропускаем загрузку PAT"
else
    echo "   Загружаем PAT в слот NEXT..."
    
    # Определение домена
    echo "   Введите ваш домен (например, https://konstructour.com):"
    read -r DOMAIN
    
    if [ -z "$DOMAIN" ]; then
        DOMAIN="https://konstructour.com"
        echo "   Используем домен по умолчанию: $DOMAIN"
    fi
    
    # Загрузка PAT
    RESPONSE=$(curl -sS -X POST "$DOMAIN/api/config-store-secure.php" \
        -H "Content-Type: application/json" \
        -H "X-Admin-Token: $ADMIN_TOKEN" \
        -d "{\"airtable\":{\"api_key\":\"$PAT\"}}" 2>/dev/null || echo '{"ok":false,"error":"curl failed"}')
    
    echo "   Ответ сервера: $RESPONSE"
    
    if echo "$RESPONSE" | grep -q '"ok":true'; then
        echo "   ✅ PAT токен загружен в слот NEXT"
    else
        echo "   ❌ Ошибка загрузки PAT токена"
    fi
fi
echo

# Промоут токена
echo "7. Промоут NEXT→CURRENT..."
if [ -n "${DOMAIN:-}" ]; then
    echo "   Выполняем whoami для промоута..."
    WHOAMI_RESPONSE=$(curl -sS -X POST "$DOMAIN/api/test-proxy-secure.php?provider=airtable" \
        -H "Content-Type: application/json" \
        -d '{"whoami":true}' 2>/dev/null || echo '{"ok":false,"error":"curl failed"}')
    
    echo "   Ответ whoami: $WHOAMI_RESPONSE"
    
    if echo "$WHOAMI_RESPONSE" | grep -q '"ok":true'; then
        echo "   ✅ Токен промоутнут в CURRENT"
    else
        echo "   ❌ Ошибка промоута токена"
    fi
else
    echo "   Пропускаем промоут (домен не указан)"
fi
echo

# Проверка health
echo "8. Проверка health..."
if [ -n "${DOMAIN:-}" ]; then
    echo "   Проверяем health-airtable.php..."
    HEALTH_RESPONSE=$(curl -sS "$DOMAIN/api/health-airtable.php" 2>/dev/null || echo '{"ok":false,"error":"curl failed"}')
    
    echo "   Ответ health: $HEALTH_RESPONSE"
    
    if echo "$HEALTH_RESPONSE" | grep -q '"ok":true'; then
        echo "   ✅ Health API работает"
        if echo "$HEALTH_RESPONSE" | grep -q '"current":true'; then
            echo "   ✅ Current токен активен"
        else
            echo "   ⚠️  Current токен не активен"
        fi
    else
        echo "   ❌ Health API не работает"
    fi
else
    echo "   Пропускаем проверку health (домен не указан)"
fi
echo

# Тест прямого доступа к Airtable
echo "9. Тест прямого доступа к Airtable..."
echo "   Тестируем PAT токен напрямую..."
AIRTABLE_RESPONSE=$(curl -sS -D- "https://api.airtable.com/v0/meta/whoami" \
    -H "Authorization: Bearer $PAT" 2>/dev/null || echo "curl failed")

if echo "$AIRTABLE_RESPONSE" | grep -q "HTTP/1.1 200"; then
    echo "   ✅ PAT токен валиден (HTTP 200)"
elif echo "$AIRTABLE_RESPONSE" | grep -q "HTTP/1.1 401"; then
    echo "   ❌ PAT токен недействителен (HTTP 401)"
elif echo "$AIRTABLE_RESPONSE" | grep -q "HTTP/1.1 403"; then
    echo "   ❌ PAT токен заблокирован (HTTP 403)"
else
    echo "   ⚠️  Неожиданный ответ от Airtable"
fi
echo

echo "=== Настройка завершена ==="
echo
echo "Следующие шаги:"
echo "1. Откройте Health Dashboard: $DOMAIN/site-admin/health-dashboard.html"
echo "2. Укажите базовый URL: $DOMAIN"
echo "3. Нажмите 'Сохранить URL' → 'Обновить'"
echo "4. Проверьте, что карточки стали зелеными"
echo
echo "Если проблемы остаются:"
echo "- Проверьте логи веб-сервера"
echo "- Убедитесь, что PHP может читать /var/konstructour/secrets/"
echo "- Проверьте настройки open_basedir в PHP"
echo "- Если SELinux включен, настройте контекст файлов"
