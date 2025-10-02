#!/bin/bash
# scripts/clear-local-data.sh
# Скрипт для очистки локальных данных в соответствии с Filtering.md

echo "🗑️ Очистка локальных данных..."

# Очищаем базу данных
php -r "
require_once 'api/database.php';
\$db = new Database();
\$db->clearAll();
echo 'База данных очищена\n';
"

# Удаляем тестовые файлы
echo "🧹 Удаление тестовых файлов..."
rm -f api/test-*.php
rm -f api/create-test-*.php
rm -f api/sample-*.php
rm -f api/mock-*.php
rm -f api/init-test-*.php

echo "✅ Локальные данные очищены в соответствии с Filtering.md"
echo "📊 Текущее состояние:"
curl -s "http://localhost:8000/api/enforce-filtering.php" | jq '.stats'
