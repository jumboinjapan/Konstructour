#!/bin/bash

# Быстрый скрипт для commit и push изменений в GitHub
# Использование: ./push.sh "Сообщение коммита"

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Проверяем, передано ли сообщение коммита
if [ -z "$1" ]; then
    echo -e "${YELLOW}Введите сообщение коммита:${NC}"
    read -r commit_message
else
    commit_message="$1"
fi

echo -e "${GREEN}🔄 Синхронизируем с GitHub...${NC}"

# Добавляем все изменения
echo "Добавляем файлы..."
git add -A

# Проверяем, есть ли изменения для коммита
if git diff --staged --quiet; then
    echo -e "${YELLOW}Нет изменений для коммита${NC}"
    exit 0
fi

# Создаем коммит
echo "Создаем коммит: $commit_message"
git commit -m "$commit_message"

# Отправляем на GitHub
echo "Отправляем на GitHub..."
git push origin main

echo -e "${GREEN}✅ Изменения успешно отправлены на GitHub!${NC}"
echo -e "${GREEN}🚀 GitHub Actions автоматически запустит деплой на сервер${NC}"
