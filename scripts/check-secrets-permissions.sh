#!/usr/bin/env bash
# check-secrets-permissions.sh
# Диагностика прав доступа к файлу секретов Airtable

set -euo pipefail

SECRETS_DIR="/var/konstructour/secrets"
SECRETS_FILE="$SECRETS_DIR/airtable.json"

echo "=== Диагностика прав доступа к секретам Airtable ==="
echo

# Проверяем существование директории
echo "1. Проверка директории секретов:"
if [ -d "$SECRETS_DIR" ]; then
    echo "   ✅ Директория существует: $SECRETS_DIR"
    ls -ld "$SECRETS_DIR"
else
    echo "   ❌ Директория не существует: $SECRETS_DIR"
    echo "   Создаем директорию..."
    sudo mkdir -p "$SECRETS_DIR"
    sudo chmod 755 "$SECRETS_DIR"
    echo "   ✅ Директория создана"
fi
echo

# Проверяем существование файла
echo "2. Проверка файла секретов:"
if [ -f "$SECRETS_FILE" ]; then
    echo "   ✅ Файл существует: $SECRETS_FILE"
    ls -l "$SECRETS_FILE"
else
    echo "   ❌ Файл не существует: $SECRETS_FILE"
    echo "   Создаем файл с начальной структурой..."
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

# Проверяем права доступа
echo "3. Проверка прав доступа:"
echo "   Текущие права:"
ls -l "$SECRETS_FILE"

# Определяем пользователя веб-сервера
WEB_USER=""
if command -v apache2 >/dev/null 2>&1; then
    WEB_USER=$(ps aux | grep -E '[a]pache2|[h]ttpd' | head -1 | awk '{print $1}' | grep -v root || echo "www-data")
elif command -v nginx >/dev/null 2>&1; then
    WEB_USER=$(ps aux | grep -E '[n]ginx' | head -1 | awk '{print $1}' | grep -v root || echo "www-data")
else
    WEB_USER="www-data"
fi

echo "   Определенный пользователь веб-сервера: $WEB_USER"

# Проверяем, может ли веб-сервер читать файл
echo "   Проверка чтения файла веб-сервером:"
if sudo -u "$WEB_USER" test -r "$SECRETS_FILE" 2>/dev/null; then
    echo "   ✅ Веб-сервер может читать файл"
else
    echo "   ❌ Веб-сервер НЕ может читать файл"
    echo "   Исправляем права доступа..."
    sudo chown "$WEB_USER:$WEB_USER" "$SECRETS_FILE"
    sudo chmod 600 "$SECRETS_FILE"
    echo "   ✅ Права исправлены"
fi
echo

# Проверяем содержимое файла
echo "4. Проверка содержимого файла:"
if [ -s "$SECRETS_FILE" ]; then
    echo "   ✅ Файл не пустой"
    echo "   Содержимое:"
    cat "$SECRETS_FILE" | jq . 2>/dev/null || cat "$SECRETS_FILE"
else
    echo "   ❌ Файл пустой"
fi
echo

# Проверяем PHP доступ
echo "5. Проверка PHP доступа:"
PHP_TEST_SCRIPT="/tmp/test_secrets_access.php"
cat > "$PHP_TEST_SCRIPT" << 'EOF'
<?php
$secrets_file = '/var/konstructour/secrets/airtable.json';
echo "Проверка доступа к файлу секретов:\n";
echo "Файл: $secrets_file\n";
echo "Существует: " . (file_exists($secrets_file) ? 'да' : 'нет') . "\n";
echo "Читается: " . (is_readable($secrets_file) ? 'да' : 'нет') . "\n";
echo "Размер: " . (file_exists($secrets_file) ? filesize($secrets_file) : 'N/A') . " байт\n";

if (file_exists($secrets_file) && is_readable($secrets_file)) {
    $content = file_get_contents($secrets_file);
    echo "Содержимое:\n";
    echo $content . "\n";
    
    $json = json_decode($content, true);
    if ($json) {
        echo "JSON валиден: да\n";
        echo "Структура:\n";
        print_r($json);
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
    php "$PHP_TEST_SCRIPT"
else
    echo "   PHP не установлен локально, проверка пропущена"
fi

rm -f "$PHP_TEST_SCRIPT"
echo

# Рекомендации
echo "6. Рекомендации:"
echo "   - Файл должен иметь права 600 (только владелец может читать/писать)"
echo "   - Владелец должен быть пользователем веб-сервера ($WEB_USER)"
echo "   - Директория должна иметь права 755"
echo "   - После исправления перезапустите веб-сервер"
echo

echo "=== Диагностика завершена ==="
