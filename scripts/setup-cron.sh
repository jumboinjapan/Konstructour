#!/bin/bash
# Настройка автоматической синхронизации через cron

echo "🔧 Настройка автоматической синхронизации..."

# Получаем абсолютный путь к проекту
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
AUTO_SYNC_SCRIPT="$PROJECT_DIR/api/auto-sync.php"

echo "📁 Проект: $PROJECT_DIR"
echo "📄 Скрипт: $AUTO_SYNC_SCRIPT"

# Проверяем, что скрипт существует
if [ ! -f "$AUTO_SYNC_SCRIPT" ]; then
    echo "❌ Скрипт auto-sync.php не найден!"
    exit 1
fi

# Создаем cron job для синхронизации каждые 5 минут
CRON_JOB="*/5 * * * * cd $PROJECT_DIR && php $AUTO_SYNC_SCRIPT >> /var/log/konstructour-sync.log 2>&1"

echo "⏰ Cron job: $CRON_JOB"

# Добавляем cron job (если его еще нет)
if ! crontab -l 2>/dev/null | grep -q "auto-sync.php"; then
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo "✅ Cron job добавлен успешно!"
else
    echo "⚠️  Cron job уже существует"
fi

# Создаем лог файл
sudo touch /var/log/konstructour-sync.log
sudo chmod 666 /var/log/konstructour-sync.log

echo "📝 Лог файл: /var/log/konstructour-sync.log"

# Показываем текущие cron jobs
echo ""
echo "📋 Текущие cron jobs:"
crontab -l 2>/dev/null | grep -E "(konstructour|auto-sync)" || echo "Нет cron jobs для Konstructour"

echo ""
echo "🎉 Настройка завершена!"
echo "💡 Для проверки логов используйте: tail -f /var/log/konstructour-sync.log"
echo "💡 Для удаления cron job используйте: crontab -e"
