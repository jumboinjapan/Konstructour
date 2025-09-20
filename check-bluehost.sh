#!/bin/bash

# Скрипт проверки настроек Bluehost для деплоя
# Поможет определить правильные значения для GitHub секретов

echo "🔍 Проверяем настройки Bluehost для деплоя..."
echo "================================================"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

# Функции для вывода
info() { echo -e "${BLUE}[INFO]${NC} $1"; }
success() { echo -e "${GREEN}[OK]${NC} $1"; }
warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; }

echo ""
info "Для настройки автоматического деплоя нам нужно определить 4 секрета:"
echo "1. SERVER_HOST - адрес сервера Bluehost"
echo "2. SERVER_USER - ваше имя пользователя для SSH/FTP"
echo "3. SERVER_PATH - путь к папке konstructour.com"
echo "4. DEPLOY_SSH_KEY - SSH ключ для безопасного подключения"
echo ""

# Проверяем, есть ли уже SSH ключи
echo "🔑 Проверяем существующие SSH ключи..."
if [ -f ~/.ssh/id_rsa ]; then
    success "Найден стандартный SSH ключ: ~/.ssh/id_rsa"
    echo "   Публичный ключ:"
    cat ~/.ssh/id_rsa.pub | head -c 50
    echo "..."
else
    warning "Стандартный SSH ключ не найден"
fi

if [ -f ~/.ssh/konstructour_deploy ]; then
    success "Найден ключ для деплоя: ~/.ssh/konstructour_deploy"
else
    warning "Ключ для деплоя не найден"
fi

echo ""
echo "📝 Интерактивная настройка секретов:"
echo "======================================"

# SERVER_HOST
echo ""
info "1. SERVER_HOST (адрес сервера)"
echo "   Возможные варианты для Bluehost:"
echo "   - box1234.bluehost.com (найдите в cPanel → Server Information)"
echo "   - revidovich.net (ваш основной домен)"
echo "   - IP адрес (например: 198.46.xxx.xxx)"
echo ""
read -p "Введите SERVER_HOST (или нажмите Enter для пропуска): " server_host
if [ ! -z "$server_host" ]; then
    success "SERVER_HOST: $server_host"
    
    # Проверяем доступность
    info "Проверяем доступность сервера..."
    if ping -c 1 "$server_host" >/dev/null 2>&1; then
        success "Сервер $server_host доступен"
    else
        warning "Сервер $server_host недоступен или не отвечает на ping"
    fi
fi

# SERVER_USER
echo ""
info "2. SERVER_USER (имя пользователя)"
echo "   Найдите в cPanel:"
echo "   - Account Information → Username"
echo "   - FTP Accounts → главный аккаунт"
echo "   - Обычно короткое имя: revidov1, revido01, etc."
echo ""
read -p "Введите SERVER_USER: " server_user
if [ ! -z "$server_user" ]; then
    success "SERVER_USER: $server_user"
fi

# SERVER_PATH
echo ""
info "3. SERVER_PATH (путь к папке проекта)"
if [ ! -z "$server_user" ]; then
    suggested_path="/home2/$server_user/public_html/konstructour"
    echo "   По структуре директорий: $suggested_path"
    echo "   (Видно из скриншота: /home2/revidovi/public_html/konstructour)"
    read -p "Нажмите Enter для использования этого пути или введите свой: " server_path
    if [ -z "$server_path" ]; then
        server_path="$suggested_path"
    fi
    success "SERVER_PATH: $server_path"
else
    echo "   По вашей структуре: /home2/revidovi/public_html/konstructour"
    read -p "Введите SERVER_PATH: " server_path
    if [ ! -z "$server_path" ]; then
        success "SERVER_PATH: $server_path"
    fi
fi

# SSH Test
echo ""
info "4. Тестируем SSH подключение..."
if [ ! -z "$server_host" ] && [ ! -z "$server_user" ]; then
    echo "   Попробуем подключиться: ssh $server_user@$server_host"
    echo "   (Если запросит пароль - SSH работает, но нужен ключ)"
    echo "   (Если 'Permission denied' - возможно SSH отключен)"
    echo ""
    read -p "Попробовать подключиться? (y/n): " test_ssh
    if [ "$test_ssh" = "y" ]; then
        ssh -o ConnectTimeout=10 -o BatchMode=yes "$server_user@$server_host" "echo 'SSH работает!'" 2>/dev/null
        if [ $? -eq 0 ]; then
            success "SSH подключение работает!"
        else
            warning "SSH подключение не удалось. Попробуем интерактивно..."
            ssh -o ConnectTimeout=10 "$server_user@$server_host" "pwd; ls -la public_html/ | grep konstructour"
        fi
    fi
fi

# SSH Key generation
echo ""
info "5. Создание SSH ключа для деплоя"
if [ ! -f ~/.ssh/konstructour_deploy ]; then
    read -p "Создать новый SSH ключ для деплоя? (y/n): " create_key
    if [ "$create_key" = "y" ]; then
        info "Создаем SSH ключ..."
        ssh-keygen -t rsa -b 4096 -C "deploy-konstructour-$(date +%Y%m%d)" -f ~/.ssh/konstructour_deploy -N ""
        success "SSH ключ создан: ~/.ssh/konstructour_deploy"
        
        echo ""
        info "ПУБЛИЧНЫЙ КЛЮЧ (добавьте в cPanel → SSH Access):"
        echo "================================================"
        cat ~/.ssh/konstructour_deploy.pub
        echo "================================================"
        
        echo ""
        info "ПРИВАТНЫЙ КЛЮЧ (скопируйте в GitHub секрет DEPLOY_SSH_KEY):"
        echo "============================================================"
        echo "Скопируйте ВЕСЬ текст ниже (включая BEGIN/END строки):"
        echo ""
        cat ~/.ssh/konstructour_deploy
        echo ""
        echo "============================================================"
    fi
else
    success "SSH ключ уже существует"
    echo ""
    info "Показать публичный ключ для добавления в cPanel? (y/n)"
    read show_public
    if [ "$show_public" = "y" ]; then
        echo "ПУБЛИЧНЫЙ КЛЮЧ:"
        cat ~/.ssh/konstructour_deploy.pub
    fi
    
    echo ""
    info "Показать приватный ключ для GitHub? (y/n)"
    read show_private  
    if [ "$show_private" = "y" ]; then
        echo "ПРИВАТНЫЙ КЛЮЧ (для DEPLOY_SSH_KEY):"
        cat ~/.ssh/konstructour_deploy
    fi
fi

# Summary
echo ""
echo "📋 ИТОГОВЫЕ СЕКРЕТЫ ДЛЯ GITHUB:"
echo "==============================="
if [ ! -z "$server_host" ]; then
    echo "SERVER_HOST: $server_host"
fi
if [ ! -z "$server_user" ]; then
    echo "SERVER_USER: $server_user"
fi
if [ ! -z "$server_path" ]; then
    echo "SERVER_PATH: $server_path"
fi
echo "DEPLOY_SSH_KEY: (содержимое файла ~/.ssh/konstructour_deploy)"

echo ""
info "Следующие шаги:"
echo "1. Добавьте публичный ключ в cPanel → SSH Access → Manage SSH Keys"
echo "2. Добавьте все секреты в GitHub → Settings → Secrets and variables → Actions"
echo "3. Сделайте тестовый push для проверки деплоя"
echo ""
success "Проверка завершена!"
