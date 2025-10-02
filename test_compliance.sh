#!/bin/bash
echo "🔍 Проверка соблюдения Filtering.md..."

# Проверяем, что нет тестовых файлов
TEST_FILES=$(find . -name "test-*.php" -o -name "create-test-*.php" -o -name "sample-*.php" -o -name "mock-*.php" | grep -v node_modules | grep -v .git)
if [ ! -z "$TEST_FILES" ]; then
  echo "❌ Найдены тестовые файлы:"
  echo "$TEST_FILES"
  exit 1
fi

# Проверяем, что data-api.php использует правильную архитектуру (кэш + Airtable)
if ! grep -q "getCachedRegions\|cacheRegions" api/data-api.php; then
  echo "❌ data-api.php не использует кэширование"
  exit 1
fi

# Проверяем, что используется SecretManager
if ! grep -q "secret-manager.php" api/data-api.php; then
  echo "❌ data-api.php не использует SecretManager"
  exit 1
fi

# Проверяем, что есть enforce-filtering.php
if [ ! -f "api/enforce-filtering.php" ]; then
  echo "❌ Отсутствует api/enforce-filtering.php"
  exit 1
fi

echo "✅ Все проверки пройдены успешно!"
