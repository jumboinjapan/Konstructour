#!/bin/bash

# Скрипт автоматического деплоя проекта Constructour
# Использует SSH для выгрузки на сервер

set -e  # Остановить выполнение при любой ошибке

echo "🚀 Начинаем деплой проекта Constructour..."

# Цвета для вывода
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Конфигурация (замените на ваши данные)
SERVER_HOST="your-server.com"
SERVER_USER="your-username"
SERVER_PATH="/var/www/html/constructour"
LOCAL_BUILD_PATH="./dist"

# Функция для вывода сообщений
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Проверка наличия файла конфигурации
if [ -f ".env.deploy" ]; then
    log_info "Загружаем конфигурацию из .env.deploy"
    source .env.deploy
fi

# Проверка SSH подключения
log_info "Проверяем SSH подключение к серверу..."
if ! ssh -o ConnectTimeout=10 -o BatchMode=yes "$SERVER_USER@$SERVER_HOST" exit 2>/dev/null; then
    log_error "Не удается подключиться к серверу $SERVER_HOST"
    log_info "Убедитесь, что SSH ключи настроены корректно"
    exit 1
fi

# Создание бэкапа на сервере
log_info "Создаем бэкап текущей версии на сервере..."
ssh "$SERVER_USER@$SERVER_HOST" "
    if [ -d '$SERVER_PATH' ]; then
        cp -r '$SERVER_PATH' '${SERVER_PATH}_backup_$(date +%Y%m%d_%H%M%S)'
        echo 'Бэкап создан'
    else
        echo 'Директория $SERVER_PATH не существует, создаем...'
        mkdir -p '$SERVER_PATH'
    fi
"

# Синхронизация файлов
log_info "Синхронизируем файлы с сервером..."

# Если есть build директория, используем её, иначе синхронизируем всё
if [ -d "$LOCAL_BUILD_PATH" ]; then
    log_info "Найдена build директория, синхронизируем $LOCAL_BUILD_PATH"
    rsync -avz --delete \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='.env*' \
        --exclude='*.log' \
        "$LOCAL_BUILD_PATH/" "$SERVER_USER@$SERVER_HOST:$SERVER_PATH/"
else
    log_warning "Build директория не найдена, синхронизируем весь проект"
    rsync -avz --delete \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='.env*' \
        --exclude='*.log' \
        --exclude='deploy.sh' \
        ./ "$SERVER_USER@$SERVER_HOST:$SERVER_PATH/"
fi

# Выполнение команд на сервере после деплоя
log_info "Выполняем команды на сервере..."
ssh "$SERVER_USER@$SERVER_HOST" "
    cd '$SERVER_PATH'
    
    # Установка зависимостей если есть package.json
    if [ -f 'package.json' ]; then
        echo 'Устанавливаем зависимости...'
        npm install --production
    fi
    
    # Установка прав доступа
    echo 'Устанавливаем права доступа...'
    find '$SERVER_PATH' -type f -exec chmod 644 {} \;
    find '$SERVER_PATH' -type d -exec chmod 755 {} \;
    
    # Перезапуск веб-сервера (если нужно)
    # systemctl reload nginx
    
    echo 'Деплой завершен успешно!'
"

log_info "✅ Деплой завершен успешно!"
log_info "Сайт доступен по адресу: http://$SERVER_HOST"

# Опциональная проверка доступности сайта
if command -v curl >/dev/null 2>&1; then
    log_info "Проверяем доступность сайта..."
    if curl -f -s "http://$SERVER_HOST" >/dev/null; then
        log_info "✅ Сайт доступен!"
    else
        log_warning "⚠️  Сайт может быть недоступен, проверьте настройки сервера"
    fi
fi
