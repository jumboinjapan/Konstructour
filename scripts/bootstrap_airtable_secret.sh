#!/usr/bin/env bash
# bootstrap_airtable_secret.sh
# Обязательный шаг деплоя: создание структуры секретов с правильными правами

set -euo pipefail

# Конфигурация
SECRET_DIR="/var/konstructour/secrets"
SECRET_FILE="$SECRET_DIR/airtable.json"
PHP_USER="www-data"  # измените при необходимости

echo "=== Bootstrap Airtable Secret ==="
echo "Directory: $SECRET_DIR"
echo "File: $SECRET_FILE"
echo "PHP User: $PHP_USER"
echo

# Создание каталога
echo "1. Создание каталога секретов..."
sudo mkdir -p "$SECRET_DIR"
echo "   ✅ Каталог создан: $SECRET_DIR"

# Установка прав на каталог
echo "2. Установка прав на каталог..."
sudo chown "$PHP_USER:$PHP_USER" "$SECRET_DIR"
sudo chmod 700 "$SECRET_DIR"
echo "   ✅ Права каталога: $PHP_USER:$PHP_USER, 700"

# Создание файла секрета (если не существует)
echo "3. Проверка файла секрета..."
if [ ! -f "$SECRET_FILE" ]; then
    echo "   Файл не существует, создаем..."
    sudo bash -c "cat > '$SECRET_FILE' <<'EOF'
{
  \"current\": { \"token\": null, \"since\": null },
  \"next\":    { \"token\": null, \"since\": null }
}
EOF"
    echo "   ✅ Файл создан: $SECRET_FILE"
else
    echo "   ✅ Файл уже существует: $SECRET_FILE"
fi

# Установка прав на файл
echo "4. Установка прав на файл..."
sudo chown "$PHP_USER:$PHP_USER" "$SECRET_FILE"
sudo chmod 600 "$SECRET_FILE"
echo "   ✅ Права файла: $PHP_USER:$PHP_USER, 600"

# Проверка доступа
echo "5. Проверка доступа..."
if sudo -u "$PHP_USER" test -r "$SECRET_FILE" 2>/dev/null; then
    echo "   ✅ Пользователь $PHP_USER может читать файл"
else
    echo "   ❌ Пользователь $PHP_USER НЕ может читать файл"
    echo "   Попробуйте: sudo chown -R $PHP_USER:$PHP_USER $SECRET_DIR"
    exit 1
fi

# Проверка содержимого
echo "6. Проверка содержимого..."
if sudo -u "$PHP_USER" test -s "$SECRET_FILE" 2>/dev/null; then
    echo "   ✅ Файл не пустой"
    # Проверяем, что это валидный JSON
    if sudo -u "$PHP_USER" cat "$SECRET_FILE" | jq . >/dev/null 2>&1; then
        echo "   ✅ Файл содержит валидный JSON"
    else
        echo "   ⚠️  Файл не содержит валидный JSON"
    fi
else
    echo "   ⚠️  Файл пустой"
fi

echo
echo "=== Bootstrap завершен ==="
echo "Секрет готов для использования:"
echo "- Каталог: $SECRET_DIR (700, $PHP_USER:$PHP_USER)"
echo "- Файл: $SECRET_FILE (600, $PHP_USER:$PHP_USER)"
echo
echo "Следующие шаги:"
echo "1. Загрузите PAT токен через API"
echo "2. Проверьте health: curl https://konstructour.com/api/health-airtable.php"
echo "3. Откройте Health Dashboard: https://konstructour.com/site-admin/health-dashboard.html"
