# Настройка Airtable для синхронизации

## 🔑 Получение API токена

1. Перейдите на [Airtable Developer Hub](https://airtable.com/create/tokens)
2. Войдите в свой аккаунт Airtable
3. Создайте новый Personal Access Token
4. Выберите следующие разрешения:
   - `data.records:read` - для чтения записей
   - `data.records:write` - для записи записей
   - `data.records:delete` - для удаления записей
5. Выберите базу данных `apppwhjFN82N9zNqm`
6. Скопируйте созданный токен

## ⚙️ Настройка токена

### Вариант 1: Переменная окружения (рекомендуется)
```bash
export AIRTABLE_PAT="your_token_here"
```

### Вариант 2: Файл .env
Создайте файл `.env` в корне проекта:
```
AIRTABLE_PAT=your_token_here
```

### Вариант 3: Конфигурационный файл
Обновите `api/config.php`:
```php
return array (
  'airtable_registry' => 
  array (
    'baseId' => 'apppwhjFN82N9zNqm',
    'api_key' => 'your_token_here', // Замените на ваш токен
    // ... остальная конфигурация
  ),
);
```

## 🧪 Тестирование подключения

После настройки токена протестируйте подключение:

```bash
# Тест API
curl "https://yourdomain.com/api/sync-api-fixed.php?action=test"

# Тест синхронизации
curl "https://yourdomain.com/api/sync-api-fixed.php?action=sync"
```

## 📊 Структура базы данных Airtable

### Таблица регионов (tblbSajWkzI8X7M4U)
- `Name (RU)` - Название на русском
- `Name (EN)` - Название на английском  
- `ID` - Business ID (REG-XXX)

### Таблица городов (tblHaHc9NV0mA8bSa)
- `Name (RU)` - Название на русском
- `Name (EN)` - Название на английском
- `A ID` - Business ID (CTY-XXX)
- `Type` - Тип (city/location)
- `Region` - Связь с регионом

### Таблица POI (tbl8X7M4U)
- `Name (RU)` - Название на русском
- `Name (EN)` - Название на английском
- `POI ID` - Business ID (POI-XXX)
- `Category` - Категория
- `Description` - Описание
- `City ID` - Связь с городом

## 🔄 Включение реальной синхронизации

После настройки токена обновите админ-панель:

1. Откройте `site-admin/databases.html`
2. Найдите строку с `sync-api-fixed.php`
3. Замените на `bidirectional-sync.php`:

```javascript
// Было:
const response = await fetch('/api/sync-api-fixed.php?action=sync', {

// Стало:
const response = await fetch('/api/bidirectional-sync.php?action=full', {
```

## 🚨 Устранение неполадок

### Ошибка "Airtable token not configured"
- Проверьте, что токен установлен в переменной окружения
- Убедитесь, что токен имеет правильные разрешения
- Проверьте, что токен не истек

### Ошибка "HTTP 401 Unauthorized"
- Токен неверный или истек
- Создайте новый токен с правильными разрешениями

### Ошибка "HTTP 404 Not Found"
- Проверьте правильность Base ID и Table ID
- Убедитесь, что таблицы существуют в Airtable

### Ошибка JSON parsing
- Проверьте, что API возвращает корректный JSON
- Используйте `sync-api-fixed.php` для отладки

## 📝 Логирование

Все операции синхронизации логируются в:
- `/var/log/konstructour-sync.log` (если настроен cron)
- Логи PHP в системном журнале
- Дашборд синхронизации: `/site-admin/sync-dashboard.html`

## 🔧 Автоматическая синхронизация

Для настройки автоматической синхронизации:

```bash
# Запустите скрипт настройки
./scripts/setup-cron.sh

# Проверьте cron jobs
crontab -l

# Просмотрите логи
tail -f /var/log/konstructour-sync.log
```
