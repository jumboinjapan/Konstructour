# 🚀 Руководство по деплою

## ✅ Что уже настроено

### 1. GitHub Actions Workflow
- ✅ **Файл:** `.github/workflows/deploy.yml`
- ✅ **Секреты:** Автоматически берет `AIRTABLE_PAT` из GitHub Secrets
- ✅ **Путь:** Создает файл в `$HOME/konstructour/secrets/airtable.json`
- ✅ **Health checks:** Проверяет работоспособность после деплоя

### 2. SecretManager
- ✅ **Файл:** `api/secret-manager.php`
- ✅ **Логика:** ENV → $HOME → legacy fallback
- ✅ **Формат:** `{"current":{"token":"pat.xxx","since":"2025-10-01"},"next":{"token":null,"since":null}}`

### 3. Health Monitoring
- ✅ **Файл:** `api/health-airtable.php`
- ✅ **Обновлен:** Работает с SecretManager
- ✅ **Диагностика:** Детальная информация о состоянии

## 🔧 Что нужно сделать

### 1. Настроить GitHub Secrets
В настройках репозитория добавить:
```
AIRTABLE_PAT=pat.xxx... (ваш токен Airtable)
API_BASE=https://your-domain.com
SERVER_HOST=your-server.com
SERVER_USER=username
SERVER_PATH=/path/to/site
SECRET_DIR=/home/username/konstructour/secrets
```

### 2. Запустить деплой
```bash
# Через GitHub Actions (рекомендуется)
git push origin main

# Или вручную через workflow_dispatch
# Перейти в Actions → Deploy to Bluehost → Run workflow
```

### 3. Проверить результат
```bash
# Health check
curl "https://your-domain.com/api/health-airtable.php" | jq .

# Должно вернуть:
{
  "ok": true,
  "reason": "current_working",
  "message": "Current token is working"
}
```

## 🔍 Диагностика проблем

### Проблема: "file_missing"
```bash
# Проверить на сервере
ssh user@server "ls -la ~/konstructour/secrets/"
# Должен быть файл airtable.json
```

### Проблема: "permission_denied"
```bash
# Проверить права доступа
ssh user@server "ls -la ~/konstructour/secrets/airtable.json"
# Должно быть: -rw------- 1 user user
```

### Проблема: "token_missing"
```bash
# Проверить содержимое файла
ssh user@server "cat ~/konstructour/secrets/airtable.json"
# Должен быть валидный JSON с токеном
```

## 📊 Мониторинг

### После успешного деплоя доступны:

1. **Диагностика системы:**
   ```
   GET /api/diagnostics.php
   ```

2. **Отчет о расхождениях:**
   ```
   GET /api/reference-parity.php
   ```

3. **Исследование билетов:**
   ```
   GET /api/tickets-discover.php
   ```

4. **Синхронизация справочников:**
   ```
   GET /api/sync-references.php
   ```

5. **Батч-синхронизация POI:**
   ```
   GET /api/poi-batch-sync.php
   ```

## 🎯 Ожидаемый результат

После успешного деплоя:
- ✅ Токен Airtable доступен на сервере
- ✅ Health check возвращает `ok: true`
- ✅ Все новые API эндпоинты работают
- ✅ Система готова к синхронизации данных

## 🚨 Если что-то не работает

1. **Проверить логи GitHub Actions** - там будет детальная информация
2. **Проверить health-airtable.php** - покажет точную причину проблемы
3. **Проверить права доступа** - файл должен быть читаемым PHP-процессом
4. **Проверить формат JSON** - должен соответствовать SecretManager

---

**Система готова к деплою!** 🚀
