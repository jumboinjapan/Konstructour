# 🚨 Airtable Integration Runbook

## Быстрая диагностика

### 1. Проверка Health Dashboard
```bash
# Откройте в браузере
https://konstructour.com/site-admin/health-dashboard.html

# Или проверьте API напрямую
curl -s https://konstructour.com/api/health-airtable.php | jq .
```

### 2. Проверка состояния секретов
```bash
# Проверка файла
ls -la /var/konstructour/secrets/airtable.json

# Проверка прав доступа
namei -l /var/konstructour/secrets/airtable.json

# Проверка содержимого
sudo cat /var/konstructour/secrets/airtable.json | jq .
```

---

## Типовые ошибки и решения

### 🔴 **FILE MISSING** - Секрет не создан на сервере

**Симптомы:**
- Health Dashboard: красная точка
- Message: "🔴 Секрет не создан на сервере"
- API: `"reason": "file_missing"`

**Решение:**
```bash
# Автоматическое исправление
sudo ./scripts/bootstrap_airtable_secret.sh

# Или вручную
sudo mkdir -p /var/konstructour/secrets
sudo bash -c 'cat > /var/konstructour/secrets/airtable.json <<EOF
{
  "current": { "token": null, "since": null },
  "next":    { "token": null, "since": null }
}
EOF'
sudo chown www-data:www-data /var/konstructour/secrets/airtable.json
sudo chmod 600 /var/konstructour/secrets/airtable.json
sudo chown www-data:www-data /var/konstructour/secrets
sudo chmod 700 /var/konstructour/secrets
```

### 🔴 **PERMISSION DENIED** - Нет прав доступа к файлу

**Симптомы:**
- Health Dashboard: красная точка
- Message: "🔴 Нет прав доступа к файлу секрета"
- API: `"reason": "permission_denied"`

**Решение:**
```bash
# Исправление прав
sudo chown -R www-data:www-data /var/konstructour/secrets/
sudo chmod 755 /var/konstructour/secrets/
sudo chmod 600 /var/konstructour/secrets/airtable.json

# Проверка
sudo -u www-data test -r /var/konstructour/secrets/airtable.json && echo "OK" || echo "FAIL"
```

### 🔴 **TOKEN MISSING** - Нет токенов в файле секрета

**Симптомы:**
- Health Dashboard: красная точка
- Message: "🔴 Нет токенов в файле секрета"
- API: `"reason": "token_missing"`

**Решение:**
```bash
# Загрузка PAT токена
export ADMIN_TOKEN="<ВАШ_ADMIN_TOKEN>"
export PAT="patYOUR_TOKEN_HERE"

curl -sS -X POST https://konstructour.com/api/config-store-secure.php \
  -H "Content-Type: application/json" \
  -H "X-Admin-Token: $ADMIN_TOKEN" \
  -d "{\"airtable\":{\"api_key\":\"$PAT\"}}"

# Промоут токена
curl -sS -X POST https://konstructour.com/api/test-proxy-secure.php?provider=airtable \
  -H "Content-Type: application/json" \
  -d '{"whoami":true}'
```

### 🔴 **ALL TOKENS INVALID** - Все токены недействительны

**Симптомы:**
- Health Dashboard: красная точка
- Message: "All Airtable tokens are invalid"
- API: `"reason": "all_tokens_invalid"`

**Решение:**
```bash
# Проверка токена напрямую
export PAT="patYOUR_TOKEN_HERE"
curl -sS -D- https://api.airtable.com/v0/meta/whoami \
  -H "Authorization: Bearer $PAT"

# Если 401/403 - токен недействителен, нужен новый
# Если 200 - проблема в коде, проверьте логи
```

---

## Полная диагностика

### 1. Проверка файловой системы
```bash
# Структура каталогов
ls -la /var/konstructour/
ls -la /var/konstructour/secrets/

# Права доступа
namei -l /var/konstructour/secrets/airtable.json
stat /var/konstructour/secrets/airtable.json
```

### 2. Проверка PHP пользователя
```bash
# Кто запускает PHP
ps aux | grep php-fpm
ps aux | grep apache2

# Проверка от имени PHP пользователя
sudo -u www-data test -r /var/konstructour/secrets/airtable.json && echo "OK" || echo "FAIL"
```

### 3. Проверка содержимого секрета
```bash
# Чтение файла
sudo cat /var/konstructour/secrets/airtable.json

# Валидация JSON
sudo cat /var/konstructour/secrets/airtable.json | jq .

# Проверка токенов
sudo cat /var/konstructour/secrets/airtable.json | jq '.current.token'
sudo cat /var/konstructour/secrets/airtable.json | jq '.next.token'
```

### 4. Проверка API эндпоинтов
```bash
# Health API
curl -s https://konstructour.com/api/health-airtable.php | jq .

# WhoAmI API
curl -s -X POST https://konstructour.com/api/test-proxy-secure.php?provider=airtable \
  -H "Content-Type: application/json" \
  -d '{"whoami":true}' | jq .

# Data Parity API
curl -s https://konstructour.com/api/data-parity.php | jq .
```

---

## Автоматические скрипты

### 1. Полная диагностика
```bash
./scripts/kt_airtable_diag.sh
```

### 2. Исправление прав
```bash
sudo ./scripts/quick-secrets-fix.sh
```

### 3. Bootstrap секретов
```bash
sudo ./scripts/bootstrap_airtable_secret.sh
```

### 4. Настройка с нуля
```bash
sudo ./scripts/setup-airtable-secrets.sh
```

---

## Мониторинг и алерты

### 1. Health Check для мониторинга
```bash
# Простой health check
curl -s https://konstructour.com/api/health-airtable.php | jq -r '.ok'

# Детальный health check
curl -s https://konstructour.com/api/health-airtable.php | jq -r '.reason'
```

### 2. Cron задача для проверки
```bash
# Добавить в crontab
*/5 * * * * curl -s https://konstructour.com/api/health-airtable.php | jq -r '.ok' | grep -q true || echo "Airtable health check failed" | mail -s "Airtable Alert" admin@konstructour.com
```

### 3. Логи для отладки
```bash
# Логи веб-сервера
tail -f /var/log/apache2/error.log | grep -i airtable
tail -f /var/log/nginx/error.log | grep -i airtable

# Логи PHP
tail -f /var/log/php/error.log | grep -i airtable
```

---

## Troubleshooting

### Проблема: open_basedir restriction
```bash
# Проверка настроек PHP
php -i | grep open_basedir

# Решение: переместить секреты в разрешенную директорию
sudo mkdir -p /var/www/secrets
sudo mv /var/konstructour/secrets/airtable.json /var/www/secrets/
sudo chown www-data:www-data /var/www/secrets/airtable.json
sudo chmod 600 /var/www/secrets/airtable.json

# Обновить путь в secret-airtable.php
```

### Проблема: SELinux Enforcing
```bash
# Проверка статуса
sestatus

# Установка контекста
sudo chcon -t httpd_exec_t /var/konstructour/secrets/airtable.json
sudo setsebool -P httpd_can_network_connect 1

# Или отключение SELinux (не рекомендуется)
sudo setenforce 0
```

### Проблема: Разные пользователи web/cron
```bash
# Проверка пользователей
ps aux | grep php-fpm
ps aux | grep cron

# Синхронизация пользователей
sudo crontab -u www-data -e
# Добавить: */15 * * * * /usr/bin/php /var/www/konstructour/api/cron-sync.php
```

---

## Контакты и эскалация

- **Документация:** `AIRTABLE_SETUP_QUICK.md`
- **Диагностика:** `DIAGNOSTICS.md`
- **Health Dashboard:** `https://konstructour.com/site-admin/health-dashboard.html`
- **API Health:** `https://konstructour.com/api/health-airtable.php`

---

## Готовые команды для копирования

```bash
# 1. Bootstrap секретов
sudo ./scripts/bootstrap_airtable_secret.sh

# 2. Загрузка токена
export ADMIN_TOKEN="<ВАШ_ADMIN_TOKEN>"
export PAT="patYOUR_TOKEN_HERE"
curl -sS -X POST https://konstructour.com/api/config-store-secure.php \
  -H "Content-Type: application/json" \
  -H "X-Admin-Token: $ADMIN_TOKEN" \
  -d "{\"airtable\":{\"api_key\":\"$PAT\"}}"

# 3. Промоут токена
curl -sS -X POST https://konstructour.com/api/test-proxy-secure.php?provider=airtable \
  -H "Content-Type: application/json" \
  -d '{"whoami":true}'

# 4. Проверка
curl -s https://konstructour.com/api/health-airtable.php | jq .
```

---

**🎯 Цель:** Все карточки в Health Dashboard должны быть зелеными!
