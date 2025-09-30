# 🔐 Устранение проблем с секретами Airtable

Руководство по решению ошибки "Airtable secret not readable: /var/konstructour/secrets/airtable.json"

## 🚨 Проблема

Health Dashboard показывает:
- 🔴 **Airtable Health** - Auth: "—"
- 🔴 **Proxy WhoAmI** - Auth: "FAIL", Status: "401"
- 🔴 **Performance** - Success Rate: "0%"

В логах: `Airtable secret not readable: /var/konstructour/secrets/airtable.json`

## 🔍 Диагностика

### **Быстрая проверка:**
```bash
# Проверка существования файла
ls -l /var/konstructour/secrets/airtable.json

# Проверка прав доступа
namei -l /var/konstructour/secrets/airtable.json

# Проверка пользователя веб-сервера
ps aux | grep -E '[a]pache2|[h]ttpd|[n]ginx' | head -1
```

### **Автоматическая диагностика:**
```bash
# Запуск диагностического скрипта
./scripts/check-secrets-permissions.sh
```

## 🛠 Решение

### **Автоматическое исправление:**
```bash
# Запуск скрипта исправления
sudo ./scripts/fix-secrets-permissions.sh
```

### **Ручное исправление:**

#### 1. **Создание директории и файла:**
```bash
# Создать директорию
sudo mkdir -p /var/konstructour/secrets
sudo chmod 755 /var/konstructour/secrets

# Создать файл секретов
sudo tee /var/konstructour/secrets/airtable.json > /dev/null << 'EOF'
{
  "current": {
    "token": null,
    "since": null
  },
  "next": {
    "token": null,
    "since": null
  }
}
EOF
```

#### 2. **Установка правильных прав:**
```bash
# Определить пользователя веб-сервера
WEB_USER=$(ps aux | grep -E '[a]pache2|[h]ttpd' | head -1 | awk '{print $1}' | grep -v root || echo "www-data")

# Установить владельца и права
sudo chown $WEB_USER:$WEB_USER /var/konstructour/secrets/airtable.json
sudo chmod 600 /var/konstructour/secrets/airtable.json
```

#### 3. **Проверка результата:**
```bash
# Проверить права
ls -l /var/konstructour/secrets/airtable.json
# Должно быть: -rw------- 1 www-data www-data

# Проверить доступ веб-сервера
sudo -u www-data test -r /var/konstructour/secrets/airtable.json && echo "OK" || echo "FAIL"
```

## 🔄 После исправления

### **1. Перезапуск веб-сервера:**
```bash
# Apache
sudo systemctl restart apache2

# Nginx
sudo systemctl restart nginx
```

### **2. Проверка Health Dashboard:**
- Откройте: `https://www.konstructour.com/site-admin/health-dashboard.html`
- **Airtable Health** должен стать зеленым
- **Proxy WhoAmI** должен показать "OK"
- **Performance** начнет считать успешные запросы

### **3. Установка Airtable токена:**
- Используйте **Quick Fix** на странице "Обзор"
- Или **Rotate Token** в полном Health Dashboard

## 📋 Требования к правам

### **Файл секретов:**
- **Путь:** `/var/konstructour/secrets/airtable.json`
- **Права:** `600` (только владелец может читать/писать)
- **Владелец:** пользователь веб-сервера (обычно `www-data`)

### **Директория секретов:**
- **Путь:** `/var/konstructour/secrets/`
- **Права:** `755` (владелец: rwx, группа: rx, остальные: rx)
- **Владелец:** пользователь веб-сервера

## 🚨 Частые проблемы

### **Проблема: "Permission denied"**
**Решение:**
```bash
sudo chown -R www-data:www-data /var/konstructour/secrets/
sudo chmod 755 /var/konstructour/secrets/
sudo chmod 600 /var/konstructour/secrets/airtable.json
```

### **Проблема: "No such file or directory"**
**Решение:**
```bash
sudo mkdir -p /var/konstructour/secrets
sudo touch /var/konstructour/secrets/airtable.json
# Затем установить права как выше
```

### **Проблема: "Invalid JSON"**
**Решение:**
```bash
# Пересоздать файл с правильной структурой
sudo rm /var/konstructour/secrets/airtable.json
# Затем запустить скрипт исправления
```

### **Проблема: "Different users for web/cron"**
**Решение:**
```bash
# Установить группу для совместного доступа
sudo chown www-data:www-data /var/konstructour/secrets/airtable.json
sudo chmod 660 /var/konstructour/secrets/airtable.json
```

## 🔧 Дополнительные скрипты

### **Диагностика:**
```bash
./scripts/check-secrets-permissions.sh
```

### **Исправление:**
```bash
sudo ./scripts/fix-secrets-permissions.sh
```

### **Тест PHP доступа:**
```bash
php -r "
require_once '/var/www/html/api/secret-airtable.php';
try {
    \$tokens = load_airtable_tokens();
    echo 'OK: ' . json_encode(\$tokens) . PHP_EOL;
} catch (Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage() . PHP_EOL;
}
"
```

## 📊 Ожидаемый результат

После исправления Health Dashboard должен показать:
- 🟢 **Airtable Health** - Auth: current✔ / next✔
- 🟢 **Proxy WhoAmI** - Auth: OK
- 🟢 **Performance** - Success Rate: >90%
- 🟢 **Token Management** - Current: Active

---

**💡 Совет**: Всегда используйте скрипты для диагностики и исправления - они автоматически определяют правильного пользователя веб-сервера и устанавливают корректные права.
