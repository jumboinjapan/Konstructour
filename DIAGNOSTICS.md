# 🔬 Диагностические скрипты Konstructour

Полный набор инструментов для диагностики, тестирования и мониторинга системы интеграции с Airtable.

## 📋 Доступные скрипты

### 1. **kt_airtable_diag.sh** - Базовая диагностика
```bash
./scripts/kt_airtable_diag.sh [host]
```
**Что проверяет:**
- ✅ Health check Airtable
- ✅ WhoAmI тест через прокси
- ✅ Синхронизация данных
- ✅ Целостность базы данных SQLite
- ✅ Foreign Key ограничения
- ✅ Нагрузочное тестирование (10 запросов)
- ✅ Проверка безопасности

### 2. **test-failover.sh** - Тестирование failover
```bash
./scripts/test-failover.sh <host> <admin_token>
```
**Что проверяет:**
- 🔄 Ротация токенов (next → current)
- 🔄 Автоматический failover при 401
- 🔄 Rate limiting и retry логика
- 🔒 Безопасность (неавторизованный доступ)
- 🔒 Валидация формата PAT

### 3. **performance-test.sh** - Нагрузочное тестирование
```bash
./scripts/performance-test.sh [host] [requests]
```
**Что проверяет:**
- ⚡ Время ответа (мин/макс/среднее/медиана)
- ⚡ Стабильность производительности
- ⚡ Rate limiting (429 ошибки)
- ⚡ Ошибки аутентификации
- ⚡ Общий процент успешности

### 4. **db-integrity-check.sh** - Проверка базы данных
```bash
./scripts/db-integrity-check.sh [db_path]
```
**Что проверяет:**
- 🗄️ Структура базы данных
- 🗄️ PRAGMA настройки SQLite
- 🗄️ Foreign Key целостность
- 🗄️ Сиротские записи
- 🗄️ Качество данных
- 🗄️ Статистика использования

### 5. **full-diagnostics.sh** - Полная диагностика
```bash
./scripts/full-diagnostics.sh [host] [admin_token]
```
**Что проверяет:**
- 🔍 Все компоненты системы
- 🔍 Зависимости и доступность
- 🔍 Интеграционные тесты
- 🔍 Производительность
- 🔍 Безопасность
- 🔍 Готовность к продакшену

## 🚀 Быстрый старт

### Локальное тестирование
```bash
# Запуск локального сервера
php -S localhost:8000 -t .

# Базовая диагностика
./scripts/kt_airtable_diag.sh http://localhost:8000

# Полная диагностика (если есть админ токен)
./scripts/full-diagnostics.sh http://localhost:8000 your-admin-token
```

### Продакшен тестирование
```bash
# Базовая диагностика
./scripts/kt_airtable_diag.sh https://www.konstructour.com

# Нагрузочное тестирование
./scripts/performance-test.sh https://www.konstructour.com 50

# Полная диагностика
./scripts/full-diagnostics.sh https://www.konstructour.com $ADMIN_TOKEN
```

## 📊 Интерпретация результатов

### ✅ Успешные тесты
- **Health Check**: `ok: true` - система работает
- **WhoAmI**: `auth: true` - токены валидны
- **Sync**: `ok: true` - синхронизация работает
- **DB Integrity**: `0 orphans` - нет сиротских записей
- **Performance**: `100% success rate` - стабильная работа

### ⚠️ Предупреждения
- **Rate Limiting**: 429 ошибки - нормально при высокой нагрузке
- **Empty Database**: 0 записей - нужно запустить синхронизацию
- **Unstable Performance**: большие колебания времени ответа

### ❌ Критические ошибки
- **Health Check**: `ok: false` - система не работает
- **Auth Errors**: 401/403 - проблемы с токенами
- **DB Orphans**: >0 - нарушена целостность данных
- **Missing Files**: отсутствуют критические файлы

## 🔧 Устранение неполадок

### Проблема: Health Check Failed
```bash
# Проверить токены
curl -sS https://www.konstructour.com/api/health-airtable.php | jq .

# Проверить логи
tail -f /var/log/php_errors.log | grep "HEALTH-AIRTABLE"
```

### Проблема: Database Orphans
```bash
# Запустить проверку целостности
./scripts/db-integrity-check.sh

# Исправить сиротские записи
sqlite3 api/konstructour.db "DELETE FROM cities WHERE region_id NOT IN (SELECT id FROM regions);"
```

### Проблема: Performance Issues
```bash
# Запустить нагрузочный тест
./scripts/performance-test.sh https://www.konstructour.com 100

# Проверить rate limiting
curl -sS -X POST https://www.konstructour.com/api/test-proxy-secure.php?provider=airtable \
  -H 'Content-Type: application/json' -d '{"whoami":true}' -w "%{http_code}"
```

## 📈 Мониторинг в продакшене

### Cron задачи
```bash
# Каждые 15 минут - проверка здоровья
*/15 * * * * cd /path/to/konstructour && ./scripts/kt_airtable_diag.sh https://www.konstructour.com >> /var/log/konstructour-health.log 2>&1

# Каждый час - полная диагностика
0 * * * * cd /path/to/konstructour && ./scripts/full-diagnostics.sh https://www.konstructour.com $ADMIN_TOKEN >> /var/log/konstructour-full.log 2>&1
```

### Алерты
```bash
# Скрипт для отправки алертов
#!/bin/bash
if ! ./scripts/kt_airtable_diag.sh https://www.konstructour.com >/dev/null 2>&1; then
  # Отправить уведомление (email, Slack, etc.)
  echo "Konstructour health check failed" | mail -s "Alert" admin@example.com
fi
```

## 🎯 Go/No-Go критерии

### ✅ Готово к продакшену
- [ ] Все диагностические тесты проходят
- [ ] Health check стабильно `ok: true` ≥ 24 часа
- [ ] Нет сиротских записей в БД
- [ ] Производительность в пределах SLA
- [ ] Нет утечек токенов в логах
- [ ] Cron задачи работают стабильно

### ❌ Не готово
- [ ] Любые критические ошибки
- [ ] Нестабильная производительность
- [ ] Проблемы с безопасностью
- [ ] Нарушена целостность данных
- [ ] Отсутствуют критические файлы

## 📚 Дополнительные ресурсы

- [SECURE_SETUP.md](SECURE_SETUP.md) - Настройка безопасности
- [API Documentation](api/) - Документация API
- [Logs](logs/) - Логи системы
- [Monitoring](monitoring/) - Мониторинг и алерты

---

**💡 Совет**: Запускайте диагностику регулярно для раннего обнаружения проблем и поддержания стабильности системы.
