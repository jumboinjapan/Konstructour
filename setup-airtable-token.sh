#!/bin/bash
# Setup Airtable token for export

echo "🔑 Настройка Airtable API токена"
echo ""

# Check if token is already set
if [ ! -z "$AIRTABLE_PAT" ]; then
    echo "✅ Токен уже установлен: ${AIRTABLE_PAT:0:10}..."
    echo "Хотите изменить токен? (y/n)"
    read -r change_token
    if [ "$change_token" != "y" ]; then
        echo "Токен не изменен"
        exit 0
    fi
fi

echo "Для получения токена Airtable:"
echo "1. Перейдите на https://airtable.com/create/tokens"
echo "2. Создайте новый токен с правами на чтение данных"
echo "3. Скопируйте токен"
echo ""

echo "Введите ваш Airtable токен:"
read -r token

if [ -z "$token" ]; then
    echo "❌ Токен не может быть пустым"
    exit 1
fi

# Set token for current session
export AIRTABLE_PAT="$token"

# Add to .bashrc for persistence
echo "export AIRTABLE_PAT=\"$token\"" >> ~/.bashrc

echo ""
echo "✅ Токен установлен для текущей сессии"
echo "✅ Токен добавлен в ~/.bashrc для постоянного использования"
echo ""
echo "Теперь вы можете запустить экспорт:"
echo "php export-airtable.php"
echo ""
echo "Или экспорт локальной базы данных:"
echo "php export-local-db.php"
