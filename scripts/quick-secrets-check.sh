#!/usr/bin/env bash
# quick-secrets-check.sh
# Быстрая проверка прав доступа к файлу секретов

set -euo pipefail

SECRETS_FILE="/var/konstructour/secrets/airtable.json"

echo "=== Быстрая проверка секретов Airtable ==="
echo

# Проверка существования файла
echo "1. Проверка файла:"
if [ -f "$SECRETS_FILE" ]; then
    echo "   ✅ Файл существует: $SECRETS_FILE"
    ls -l "$SECRETS_FILE"
else
    echo "   ❌ Файл не существует: $SECRETS_FILE"
    echo "   Создаем файл..."
    sudo mkdir -p "$(dirname "$SECRETS_FILE")"
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
    echo "   ✅ Файл создан"
fi
echo

# Проверка пути (namei)
echo "2. Проверка пути доступа:"
if command -v namei >/dev/null 2>&1; then
    namei -l "$SECRETS_FILE"
else
    echo "   namei не установлен, проверяем вручную..."
    echo "   Директория: $(dirname "$SECRETS_FILE")"
    ls -ld "$(dirname "$SECRETS_FILE")"
fi
echo

# Определение пользователя веб-сервера
echo "3. Определение пользователя веб-сервера:"
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

# Проверка прав доступа
echo "4. Проверка прав доступа:"
echo "   Текущие права:"
ls -l "$SECRETS_FILE"

echo "   Проверка чтения пользователем $WEB_USER:"
if sudo -u "$WEB_USER" test -r "$SECRETS_FILE" 2>/dev/null; then
    echo "   ✅ Пользователь $WEB_USER может читать файл"
else
    echo "   ❌ Пользователь $WEB_USER НЕ может читать файл"
    echo "   Исправляем права..."
    sudo chown "$WEB_USER:$WEB_USER" "$SECRETS_FILE"
    sudo chmod 600 "$SECRETS_FILE"
    echo "   ✅ Права исправлены"
fi
echo

# Проверка содержимого
echo "5. Проверка содержимого:"
if [ -s "$SECRETS_FILE" ]; then
    echo "   ✅ Файл не пустой"
    echo "   Содержимое:"
    cat "$SECRETS_FILE" | jq . 2>/dev/null || cat "$SECRETS_FILE"
else
    echo "   ❌ Файл пустой"
fi
echo

# Тест через PHP
echo "6. Тест через PHP:"
PHP_TEST="/tmp/test_secrets_quick.php"
cat > "$PHP_TEST" << 'EOF'
<?php
$file = '/var/konstructour/secrets/airtable.json';
echo "PHP тест доступа к секретам:\n";
echo "Файл: $file\n";
echo "Существует: " . (file_exists($file) ? 'да' : 'нет') . "\n";
echo "Читается: " . (is_readable($file) ? 'да' : 'нет') . "\n";

if (file_exists($file) && is_readable($file)) {
    $content = file_get_contents($file);
    $json = json_decode($content, true);
    if ($json) {
        echo "JSON валиден: да\n";
        echo "Current токен: " . (empty($json['current']) ? 'НЕТ' : 'ЕСТЬ') . "\n";
        echo "Next токен: " . (empty($json['next']) ? 'НЕТ' : 'ЕСТЬ') . "\n";
    } else {
        echo "JSON валиден: нет\n";
        echo "Ошибка: " . json_last_error_msg() . "\n";
    }
} else {
    echo "Ошибка: файл недоступен\n";
}
?>
EOF

if command -v php >/dev/null 2>&1; then
    php "$PHP_TEST"
else
    echo "   PHP не установлен локально"
fi

rm -f "$PHP_TEST"
echo

echo "=== Проверка завершена ==="
echo
echo "Если все проверки прошли успешно, перезапустите веб-сервер:"
echo "sudo systemctl restart apache2  # или nginx"
