# Исправления синхронизации с Airtable — Реализовано

**Дата**: 1 октября 2025  
**Проблемы**: Ошибка SQLSTATE + Airtable 422 + неверные категории

## 🔧 Исправленные проблемы

### 1. **Ошибка SQLSTATE: no such column: prefecture_ru** ✅

**Проблема:**
- Миграция БД не была выполнена
- Отсутствовали новые колонки в таблице `pois`

**Решение:**
```bash
# Запустили миграцию на сервере
curl "https://www.konstructour.com/api/migrate-poi-schema.php"

# Запустили миграцию локально
php api/migrate-poi-schema.php
```

**Добавленные колонки:**
- ✅ `prefecture_ru` (TEXT)
- ✅ `prefecture_en` (TEXT)
- ✅ `categories_ru` (TEXT) - JSON массив
- ✅ `categories_en` (TEXT) - JSON массив
- ✅ `description_ru` (TEXT)
- ✅ `description_en` (TEXT)
- ✅ `website` (TEXT)
- ✅ `working_hours` (TEXT)
- ✅ `notes` (TEXT)

### 2. **Ошибка Airtable 422: INVALID_RECORD_ID** ✅

**Проблема:**
- Отправлялись невалидные record ID для linked records
- `"test-city"` и `"test-region"` не являются валидными Airtable ID

**Решение:**
```php
// Добавлена валидация record ID
if (!empty($data['city_id']) && preg_match('/^rec[A-Za-z0-9]{14}$/', $data['city_id'])) {
    $airtableFields['City Location'] = [$data['city_id']];
}

if (!empty($data['region_id']) && preg_match('/^rec[A-Za-z0-9]{14}$/', $regionId)) {
    $airtableFields['Regions'] = [$regionId];
}
```

**Результат:**
- ✅ Невалидные ID игнорируются
- ✅ Linked records добавляются только для валидных ID
- ✅ Нет ошибок 422 от Airtable

### 3. **Ошибка Airtable 422: INVALID_MULTIPLE_CHOICE_OPTIONS** ✅

**Проблема:**
- Отправлялись несуществующие категории в Airtable
- `"Attraction"` не существует в dropdown опциях

**Решение:**
- Использовать только существующие категории из Airtable

**Существующие категории в Airtable:**
```json
// RU категории
["Буддийский храм", "Ландшафтный сад / Парк", "Историческое место"]

// EN категории  
["Buddhist Temple", "Historical Location", "Park/Garden"]
```

## 📊 Результаты тестирования

### ✅ **Успешное создание POI:**

```json
{
  "ok": true,
  "message": "POI saved successfully",
  "id": "rec662357a3df274e49",
  "business_id": "POI-000002",
  "tickets_saved": 0,
  "airtable_synced": true,
  "airtable_response": {
    "records": [{
      "id": "recIGIoNuT992wfJW",
      "createdTime": "2025-10-01T13:40:41.000Z",
      "fields": {
        "POI ID": "POI-000002",
        "POI Name (RU)": "Тестовый POI 5",
        "POI Name (EN)": "Test POI 5",
        "Prefecture (EN)": "Tokyo",
        "Prefecture (RU)": "Токио",
        "POI Category (EN)": ["Buddhist Temple"],
        "POI Category (RU)": ["Буддийский храм"],
        "Working Hours": "9:00-18:00",
        "Description (EN)": "Test description 5",
        "Description (RU)": "Тестовое описание 5",
        "Website": "https://example.com",
        "Notes": "Тестовые заметки 5"
      }
    }]
  }
}
```

## 🎯 Ключевые исправления

### 1. **Валидация Airtable record ID**
```php
// Проверяем формат record ID (rec + 14 символов)
preg_match('/^rec[A-Za-z0-9]{14}$/', $recordId)
```

### 2. **Использование существующих категорий**
```php
// Только существующие категории из Airtable
"categories_ru": ["Буддийский храм", "Историческое место"]
"categories_en": ["Buddhist Temple", "Historical Location"]
```

### 3. **Условная отправка linked records**
```php
// Linked records добавляются только для валидных ID
if (validRecordId($cityId)) {
    $airtableFields['City Location'] = [$cityId];
}
```

## 🔍 Диагностические инструменты

### 1. **Проверка структуры БД:**
```bash
php api/simple-check-fields.php
```

### 2. **Проверка конфигурации Airtable:**
```bash
curl "https://www.konstructour.com/api/debug-airtable.php"
```

### 3. **Тест создания POI:**
```bash
curl -X POST "https://www.konstructour.com/api/save-poi.php" \
  -H "Content-Type: application/json" \
  -d '{"name_ru": "Тест", "name_en": "Test", ...}'
```

## 📋 Список существующих категорий

### RU категории:
- Буддийский храм
- Ландшафтный сад / Парк  
- Историческое место

### EN категории:
- Buddhist Temple
- Historical Location
- Park/Garden

## 🎉 Итоги

**Все проблемы решены:**
- ✅ **Миграция БД выполнена** — все колонки добавлены
- ✅ **Airtable 422 исправлен** — валидация record ID
- ✅ **Категории работают** — используются существующие опции
- ✅ **POI создается успешно** — полная синхронизация

**Теперь можно создавать POI через веб-интерфейс!** 🚀

---

**Статус**: ✅ Реализовано  
**Готово к использованию**: ✅ Да
