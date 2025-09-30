# 🔄 Экспорт карточек из Airtable

## 📋 Текущая ситуация

В локальной базе данных **нет карточек (POI)** - только тестовые регионы и города. Все карточки хранятся в Airtable и должны быть экспортированы оттуда.

## 🎯 Шаги для экспорта из Airtable

### 1. Получение API токена Airtable

1. Перейдите на https://airtable.com/create/tokens
2. Войдите в свой аккаунт Airtable
3. Создайте новый токен с правами:
   - `data.records:read` - для чтения записей
   - `data.bases:read` - для чтения баз данных
4. Скопируйте созданный токен

### 2. Настройка токена

```bash
# Установите токен в переменную окружения
export AIRTABLE_PAT="your_token_here"

# Или используйте скрипт настройки
./setup-airtable-token.sh
```

### 3. Запуск экспорта

```bash
# Экспорт всех данных из Airtable
php export-airtable.php
```

## 📊 Структура данных в Airtable

### Таблицы:
- **Regions** (`tblbSajWkzI8X7M4U`) - Регионы Японии
- **Cities** (`tblHaHc9NV0mA8bSa`) - Города и локации  
- **POIs** (`tblVCmFcHRpXUT24y`) - **Карточки (точки интереса)**

### Поля карточек (POI):
- `POI Name (RU)` - Название на русском
- `POI Name (EN)` - Название на английском
- `Category` - Категория
- `Place ID` - Google Places ID
- `Published` - Статус публикации
- `A ID` - Бизнес-идентификатор (POI-XXXX)
- `Regions` - Связь с регионом
- `Description` - Описание
- `Latitude`, `Longitude` - Координаты

## 🔧 Альтернативные способы экспорта

### 1. Через веб-интерфейс Airtable
1. Откройте таблицу POIs в Airtable
2. Нажмите "Export" → "CSV"
3. Выберите все поля
4. Скачайте файл

### 2. Через Airtable API напрямую
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://api.airtable.com/v0/apppwhjFN82N9zNqm/tblVCmFcHRpXUT24y?pageSize=100" \
  > pois_export.json
```

### 3. Через наш тестовый интерфейс
Откройте `test-api-browser.html` в браузере для тестирования API

## 📁 Результат экспорта

После успешного экспорта в папке `exports/` появятся файлы:

```
exports/
├── regions_YYYY-MM-DD_HH-MM-SS.json
├── regions_YYYY-MM-DD_HH-MM-SS.csv
├── cities_YYYY-MM-DD_HH-MM-SS.json
├── cities_YYYY-MM-DD_HH-MM-SS.csv
├── pois_YYYY-MM-DD_HH-MM-SS.json      ← КАРТОЧКИ
├── pois_YYYY-MM-DD_HH-MM-SS.csv       ← КАРТОЧКИ
└── all_data_YYYY-MM-DD_HH-MM-SS.json
```

## ⚠️ Важные замечания

1. **Токен безопасности**: Никогда не коммитьте токен в git
2. **Лимиты API**: Airtable имеет лимиты на количество запросов
3. **Пагинация**: Скрипт автоматически обрабатывает большие объемы данных
4. **Кодировка**: Все файлы экспортируются в UTF-8

## 🚨 Устранение проблем

### "API key not configured"
```bash
export AIRTABLE_PAT="your_token_here"
```

### "HTTP 401 Unauthorized"
- Проверьте правильность токена
- Убедитесь, что токен не истек

### "HTTP 404 Not Found"
- Проверьте Base ID: `apppwhjFN82N9zNqm`
- Проверьте Table ID для POIs: `tblVCmFcHRpXUT24y`

### "No data exported"
- Убедитесь, что в таблице POIs есть данные
- Проверьте права доступа токена
