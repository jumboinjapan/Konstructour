#!/usr/bin/env bash
# fix-secrets-permissions.sh
# Автоматическое исправление прав доступа к файлу секретов Airtable

set -euo pipefail

SECRETS_DIR="/var/konstructour/secrets"
SECRETS_FILE="$SECRETS_DIR/airtable.json"

echo "=== Исправление прав доступа к секретам Airtable ==="
echo

# Определяем пользователя веб-сервера
WEB_USER=""
if command -v apache2 >/dev/null 2>&1; then
    WEB_USER=$(ps aux | grep -E '[a]pache2|[h]ttpd' | head -1 | awk '{print $1}' | grep -v root || echo "www-data")
elif command -v nginx >/dev/null 2>&1; then
    WEB_USER=$(ps aux | grep -E '[n]ginx' | head -1 | awk '{print $1}' | grep -v root || echo "www-data")
else
    WEB_USER="www-data"
fi

echo "Определенный пользователь веб-сервера: $WEB_USER"
echo

# Создаем директорию если не существует
echo "1. Создание директории секретов..."
sudo mkdir -p "$SECRETS_DIR"
sudo chmod 755 "$SECRETS_DIR"
echo "   ✅ Директория создана: $SECRETS_DIR"
echo

# Создаем файл если не существует
echo "2. Создание файла секретов..."
if [ ! -f "$SECRETS_FILE" ]; then
    sudo tee "$SECRETS_FILE" > /dev/null << 'EOF'
{
  "current": {
    "token": null,
    "since": null
  },
  "next": {
    "token": null,
    "since": null
  }
}
EOF
    echo "   ✅ Файл создан: $SECRETS_FILE"
else
    echo "   ✅ Файл уже существует: $SECRETS_FILE"
fi
echo

# Устанавливаем правильные права
echo "3. Установка прав доступа..."
sudo chown "$WEB_USER:$WEB_USER" "$SECRETS_FILE"
sudo chmod 600 "$SECRETS_FILE"
echo "   ✅ Права установлены: $WEB_USER:$WEB_USER, 600"
echo

# Проверяем результат
echo "4. Проверка результата..."
ls -l "$SECRETS_FILE"
echo

# Тестируем доступ
echo "5. Тестирование доступа..."
if sudo -u "$WEB_USER" test -r "$SECRETS_FILE" 2>/dev/null; then
    echo "   ✅ Веб-сервер может читать файл"
else
    echo "   ❌ Ошибка: веб-сервер все еще не может читать файл"
    echo "   Попробуйте запустить: sudo chown -R $WEB_USER:$WEB_USER $SECRETS_DIR"
    exit 1
fi
echo

# Создаем тестовый скрипт для проверки через PHP
echo "6. Создание тестового скрипта..."
TEST_SCRIPT="/tmp/test_airtable_secrets.php"
cat > "$TEST_SCRIPT" << 'EOF'
<?php
require_once '/var/www/html/api/secret-airtable.php';

echo "Тест доступа к секретам Airtable:\n";
echo "=====================================\n";

try {
    $tokens = load_airtable_tokens();
    echo "✅ Функция load_airtable_tokens() работает\n";
    echo "Текущие токены:\n";
    echo "- Current: " . (empty($tokens['current']) ? 'НЕТ' : 'ЕСТЬ') . "\n";
    echo "- Next: " . (empty($tokens['next']) ? 'НЕТ' : 'ЕСТЬ') . "\n";
    
    if (!empty($tokens['current'])) {
        echo "✅ Current токен доступен\n";
    } else {
        echo "ℹ️  Current токен не установлен (это нормально)\n";
    }
    
    if (!empty($tokens['next'])) {
        echo "✅ Next токен доступен\n";
    } else {
        echo "ℹ️  Next токен не установлен (это нормально)\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n";
}

echo "\n=====================================\n";
echo "Тест завершен\n";
?>
EOF

echo "   ✅ Тестовый скрипт создан: $TEST_SCRIPT"
echo

# Запускаем тест
echo "7. Запуск теста..."
if command -v php >/dev/null 2>&1; then
    cd /var/www/html 2>/dev/null || cd /var/www 2>/dev/null || echo "Предупреждение: не удалось перейти в директорию веб-сервера"
    php "$TEST_SCRIPT"
else
    echo "   PHP не установлен локально, тест пропущен"
fi

rm -f "$TEST_SCRIPT"
echo

echo "=== Исправление завершено ==="
echo
echo "Следующие шаги:"
echo "1. Перезапустите веб-сервер:"
echo "   - Apache: sudo systemctl restart apache2"
echo "   - Nginx: sudo systemctl restart nginx"
echo
echo "2. Проверьте Health Dashboard:"
echo "   - Airtable Health должен стать зеленым"
echo "   - Proxy WhoAmI должен показать OK"
echo "   - Performance начнет считать успешные запросы"
echo
echo "3. Установите Airtable токен через Quick Fix или Rotate Token"
