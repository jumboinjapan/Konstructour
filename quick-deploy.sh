#!/bin/bash

# Быстрый деплой: push в GitHub + деплой на сервер
# Использование: ./quick-deploy.sh "Сообщение коммита"

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

COMMIT_MSG="${1:-Обновление проекта}"

echo -e "${GREEN}🚀 Быстрый деплой проекта...${NC}"

# 1. Push в GitHub
echo "📤 Отправляем в GitHub..."
./push.sh "$COMMIT_MSG"

# 2. Деплой на сервер
echo "🌐 Деплой на сервер..."
rsync -avz --delete \
  -e "ssh -i ~/.ssh/konstructour_deploy" \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='.DS_Store' \
  --exclude='*.log' \
  ./ revidovi@162.241.225.33:/home2/revidovi/public_html/konstructour/

echo -e "${GREEN}✅ Деплой завершен!${NC}"
echo -e "${GREEN}🌐 Сайт: http://konstructour.com${NC}"
