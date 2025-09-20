#!/bin/bash

# Ручной FTP деплой для Bluehost
# Используйте когда GitHub Actions не работает

echo "🚀 Ручной FTP деплой на Bluehost..."

# Конфигурация
SERVER="162.241.225.33"
USERNAME="revidovi"
REMOTE_PATH="/public_html/konstructour"

# Проверка наличия lftp
if ! command -v lftp &> /dev/null; then
    echo "❌ lftp не установлен. Установите: brew install lftp"
    exit 1
fi

echo "📤 Загружаем файлы на сервер..."

# FTP загрузка
lftp -c "
set ftp:ssl-allow no
open ftp://$USERNAME@$SERVER
cd $REMOTE_PATH
mirror --reverse --delete --verbose ./ ./
bye
"

echo "✅ Деплой завершен!"
echo "🌐 Проверьте сайт: http://konstructour.com"
