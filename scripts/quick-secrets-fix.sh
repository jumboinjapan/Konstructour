#!/usr/bin/env bash
# quick-secrets-fix.sh
# Быстрое исправление прав доступа к файлу секретов

set -euo pipefail

SECRETS_DIR="/var/konstructour/secrets"
SECRETS_FILE="$SECRETS_DIR/airtable.json"

echo "=== Быстрое исправление секретов Airtable ==="
echo

# Определение пользователя веб-сервера
WEB_USER=""
if command -v apache2 >/dev/null 2>&1; then
    WEB_USER=$(ps aux | grep -E '[a]pache2|[h]ttpd' | head -1 | awk '{print $1}' | grep -v root || echo "www-data")
elif command -v nginx >/dev/null 2>&1; then
    WEB_USER=$(ps aux | grep -E '[n]ginx' | head -1 | awk '{print $1}' | grep -v root || echo "www-data")
else
    WEB_USER="www-data"
fi

echo "Пользователь веб-сервера: $WEB_USER"
echo

# Создание директории
echo "1. Создание директории:"
sudo mkdir -p "$SECRETS_DIR"
sudo chmod 755 "$SECRETS_DIR"
echo "   ✅ Директория создана: $SECRETS_DIR"
echo

# Создание файла
echo "2. Создание файла секретов:"
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

# Установка прав
echo "3. Установка прав доступа:"
sudo chown "$WEB_USER:$WEB_USER" "$SECRETS_FILE"
sudo chmod 600 "$SECRETS_FILE"
echo "   ✅ Права установлены: $WEB_USER:$WEB_USER, 600"
echo

# Проверка результата
echo "4. Проверка результата:"
ls -l "$SECRETS_FILE"
echo

# Тест доступа
echo "5. Тест доступа:"
if sudo -u "$WEB_USER" test -r "$SECRETS_FILE" 2>/dev/null; then
    echo "   ✅ Пользователь $WEB_USER может читать файл"
else
    echo "   ❌ Ошибка: пользователь $WEB_USER все еще не может читать файл"
    echo "   Попробуйте: sudo chown -R $WEB_USER:$WEB_USER $SECRETS_DIR"
    exit 1
fi
echo

# Тест через PHP
echo "6. Тест через PHP:"
PHP_TEST="/tmp/test_secrets_fix.php"
cat > "$PHP_TEST" << 'EOF'
<?php
require_once '/var/www/html/api/secret-airtable.php';

echo "Тест функции load_airtable_tokens():\n";
try {
    $tokens = load_airtable_tokens();
    echo "✅ Функция работает\n";
    echo "Current: " . (empty($tokens['current']) ? 'НЕТ' : 'ЕСТЬ') . "\n";
    echo "Next: " . (empty($tokens['next']) ? 'НЕТ' : 'ЕСТЬ') . "\n";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>
EOF

if command -v php >/dev/null 2>&1; then
    cd /var/www/html 2>/dev/null || cd /var/www 2>/dev/null || echo "Предупреждение: не удалось перейти в директорию веб-сервера"
    php "$PHP_TEST"
else
    echo "   PHP не установлен локально"
fi

rm -f "$PHP_TEST"
echo

echo "=== Исправление завершено ==="
echo
echo "Следующие шаги:"
echo "1. Перезапустите веб-сервер:"
echo "   sudo systemctl restart apache2  # или nginx"
echo
echo "2. Проверьте Health Dashboard:"
echo "   https://www.konstructour.com/site-admin/health-dashboard.html"
echo
echo "3. Airtable Health должен стать зеленым!"
