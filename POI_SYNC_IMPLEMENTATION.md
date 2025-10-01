# Реализация синхронизации POI — Полное руководство

**Дата**: 1 октября 2025  
**Статус**: ✅ Готово к внедрению

## 📋 Точный маппинг полей

На основе скриншотов Airtable определён точный маппинг:

### Форма POI → Локальная БД → Airtable

| Поле в форме | Локальная БД | Airtable | Тип в Airtable |
|--------------|--------------|----------|----------------|
| `name_ru` | `name_ru` | `POI Name (RU)` | Text |
| `name_en` | `name_en` | `POI Name (EN)` | Text |
| `pref_ru` | `prefecture_ru` | `Prefecture (RU)` | Text/Select |
| `pref_en` | `prefecture_en` | `Prefecture (EN)` | Text/Select |
| `cats_ru[]` | `categories_ru` (JSON) | `POI Category (RU)` | Multi-select |
| `cats_en[]` | `categories_en` (JSON) | `POI Category (EN)` | Multi-select |
| `place_id` | `place_id` | `Place ID` | Text |
| `website` | `website` | `Website` | URL |
| `hours` | `working_hours` | `Working Hours` | Text |
| `desc_ru` | `description_ru` | `Description (RU)` | Long Text |
| `desc_en` | `description_en` | `Description (EN)` | Long Text |
| `notes` | `notes` | `Notes` | Long Text |
| - | `business_id` | `POI ID` | Text |
| - | `city_id` | `City Location` | Linked Record |
| - | `region_id` | `Regions` | Linked Record |

## 🔧 Созданные файлы

### 1. `api/migrate-poi-schema.php` — Миграция схемы БД

**Назначение:** Обновляет таблицу `pois` в SQLite, добавляет новые поля.

**Новые поля:**
- `prefecture_ru TEXT`
- `prefecture_en TEXT`
- `categories_ru TEXT` (JSON массив)
- `categories_en TEXT` (JSON массив)
- `description_ru TEXT`
- `description_en TEXT`
- `website TEXT`
- `working_hours TEXT`
- `notes TEXT`

**Как запустить:**
```bash
# В браузере:
https://www.konstructour.com/api/migrate-poi-schema.php
```

### 2. `api/database.php` — Обновлённый класс Database

**Изменения:**
- ✅ Обновлена схема таблицы `pois` в `initTables()`
- ✅ Обновлён метод `savePoi()` для работы с новыми полями
- ✅ Автоматическая конвертация массивов категорий в JSON
- ✅ Обратная совместимость (старое поле `category` = первая категория)

### 3. `api/save-poi.php` — Новый endpoint для сохранения POI

**Функции:**
- ✅ Валидация входных данных
- ✅ Генерация POI ID для новых записей
- ✅ Сохранение в локальную БД
- ✅ **Автоматическая синхронизация с Airtable**
- ✅ Правильный маппинг всех полей
- ✅ Обработка linked records (City Location, Regions)
- ✅ Обработка multi-select полей (Categories)

**Использование:**
```javascript
fetch('/api/save-poi.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    name_ru: '...',
    name_en: '...',
    prefecture_ru: 'tokyo',
    prefecture_en: 'tokyo',
    categories_ru: ['Буддийский храм', 'Музей'],
    categories_en: ['Buddhist Temple', 'Museum'],
    // ... остальные поля
  })
});
```

## 📊 Схема синхронизации

```
┌──────────────────────┐
│  Форма POI           │
│  (databases.html)    │
└──────────┬───────────┘
           │ POST данных
           ↓
┌──────────────────────┐
│  save-poi.php        │
├──────────────────────┤
│ 1. Валидация         │
│ 2. Генерация ID      │
│ 3. → Локальная БД    │
│ 4. → Airtable API    │
└──────────┬───────────┘
           │
     ┌─────┴─────┐
     ↓           ↓
┌─────────┐  ┌──────────────┐
│SQLite DB│  │  Airtable    │
│(local)  │  │  (cloud)     │
└─────────┘  └──────────────┘
```

## 🎯 Что нужно сделать дальше

### Шаг 1: Запустить миграцию БД ✅

```bash
# Откройте в браузере:
https://www.konstructour.com/api/migrate-poi-schema.php
```

**Проверьте вывод:**
- Должны увидеть список добавленных колонок
- Должна пройти миграция данных из старых полей

### Шаг 2: Добавить Tickets в форму ⏳

Нужно добавить блок с билетами в форму редактирования POI.

**Вопросы:**
- Сколько типов билетов может быть? (фиксировано 3 или динамическое количество?)
- Какие поля для каждого билета? (название, цена, валюта, заметка?)
- Как связаны с Airtable (`Tickets 1`, `Tickets 2`, `Tickets 3`)?

### Шаг 3: Обновить фронтенд для использования save-poi.php ⏳

Изменить обработчик сохранения в форме POI.

### Шаг 4: Тестирование полного цикла ⏳

1. Создать новый POI через форму
2. Проверить сохранение в локальной БД
3. Проверить синхронизацию в Airtable
4. Отредактировать POI
5. Проверить обновление в обеих БД

## 📝 Инструкция по деплою

```bash
# 1. Запустите миграцию БД
curl https://www.konstructour.com/api/migrate-poi-schema.php

# 2. Проверьте что файлы задеплоены
ls -la api/save-poi.php
ls -la api/migrate-poi-schema.php
ls -la api/database.php

# 3. Проверьте что БД обновлена
sqlite3 api/konstructour.db "PRAGMA table_info(pois);"
```

## ⚠️ Важные примечания

### Для Tickets (билеты)

Нужна дополнительная информация:
- Структура данных билетов
- Как они связаны с POI
- Формат полей `Tickets 1`, `Tickets 2`, `Tickets 3` в Airtable

### Для Published (опубликовать)

Кнопка "Опубликовать" в форме:
- **НЕ сохраняет** статус в БД
- **Триггерит** синхронизацию с Airtable
- После успешной синхронизации можно показать статус

---

**Готов продолжить реализацию!** Жду уточнений по Tickets. 🚀

