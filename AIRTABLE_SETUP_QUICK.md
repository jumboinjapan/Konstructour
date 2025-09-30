# 🚀 Быстрая настройка Airtable секретов

## Автоматическая настройка (рекомендуется)

```bash
# На продакшн сервере выполните:
sudo ./scripts/setup-airtable-secrets.sh
```

Скрипт автоматически:
- Создаст каталог и файл секретов
- Установит правильные права доступа
- Запросит админ токен и домен
- Загрузит PAT токен в слот NEXT
- Промоутнет токен в CURRENT
- Проверит health API
- Протестирует прямой доступ к Airtable

---

## Ручная настройка

### 1. Создание файла секретов
```bash
sudo mkdir -p /var/konstructour/secrets

sudo bash -c 'cat > /var/konstructour/secrets/airtable.json <<EOF
{
  "current": { "token": null, "since": null },
  "next":    { "token": null, "since": null }
}
EOF'

# Замените WWW_USER на пользователя PHP (часто www-data)
sudo chown WWW_USER:WWW_USER /var/konstructour/secrets/airtable.json
sudo chmod 600 /var/konstructour/secrets/airtable.json
sudo chown WWW_USER:WWW_USER /var/konstructour/secrets
sudo chmod 700 /var/konstructour/secrets
```

### 2. Загрузка PAT токена
```bash
export ADMIN_TOKEN="<ВАШ_ADMIN_TOKEN>"
export PAT="patYOUR_TOKEN_HERE"

curl -sS -X POST https://konstructour.com/api/config-store-secure.php \
  -H "Content-Type: application/json" \
  -H "X-Admin-Token: $ADMIN_TOKEN" \
  -d "{\"airtable\":{\"api_key\":\"$PAT\"}}"
```

### 3. Промоут токена
```bash
curl -sS -X POST https://konstructour.com/api/test-proxy-secure.php?provider=airtable \
  -H "Content-Type: application/json" \
  -d '{"whoami":true}'
```

### 4. Проверка health
```bash
curl -sS https://konstructour.com/api/health-airtable.php
```

### 5. Обновление дашборда
- Откройте `https://konstructour.com/site-admin/health-dashboard.html`
- Укажите базовый URL: `https://konstructour.com`
- Нажмите "Сохранить URL" → "Обновить"

---

## Проверка результата

### Ожидаемые API ответы:
- **config-store-secure.php:** `{"ok":true}`
- **health-airtable.php:** `{"ok":true,"auth":{"current":true}}`

### Ожидаемый вид дашборда:
- 🟢 **Airtable Health** - зеленая точка
- 🟢 **Proxy WhoAmI** - зеленая точка
- 🟢 **Performance** - зеленая точка
- 🟢 **Token Management** - зеленая точка

---

## Диагностика проблем

### Проблема: "Permission denied"
```bash
sudo chown -R www-data:www-data /var/konstructour/secrets/
sudo chmod 755 /var/konstructour/secrets/
sudo chmod 600 /var/konstructour/secrets/airtable.json
```

### Проблема: "Invalid admin token"
- Проверьте правильность админ токена
- Убедитесь, что токен не истек

### Проблема: "Invalid PAT format"
- Проверьте правильность Airtable PAT токена
- Убедитесь, что токен начинается с `pat.`

### Проблема: "Airtable secret not readable"
- Проверьте права файла: `ls -l /var/konstructour/secrets/airtable.json`
- Проверьте путь доступа: `namei -l /var/konstructour/secrets/airtable.json`
- Убедитесь, что PHP может читать файл

### Проблема: "open_basedir restriction"
- Переместите секреты в разрешенную директорию
- Или расширьте `open_basedir` в PHP

### Проблема: "SELinux Enforcing"
```bash
sudo chcon -t httpd_exec_t /var/konstructour/secrets/airtable.json
sudo setsebool -P httpd_can_network_connect 1
```

---

## Тест прямого доступа к Airtable

```bash
export PAT="patYOUR_TOKEN_HERE"
curl -sS -D- https://api.airtable.com/v0/meta/whoami \
  -H "Authorization: Bearer $PAT"
```

- **HTTP 200** - токен валиден
- **HTTP 401** - токен недействителен
- **HTTP 403** - токен заблокирован

---

## Готово! 🎉

После успешного выполнения всех шагов Health Dashboard должен показать зеленые индикаторы, и система будет полностью работать с Airtable.
