# Маппинг полей POI — Airtable ↔ Локальная БД ↔ Форма

**Дата**: 1 октября 2025  
**Источник**: Скриншоты Airtable таблицы POI

## 📋 Точные названия полей из Airtable

### Основные поля (видимые на скриншотах):

| № | Поле в Airtable | Тип | Назначение |
|---|-----------------|-----|------------|
| 1 | `POI ID` | Text | Уникальный идентификатор POI |
| 2 | `POI Name (RU)` | Text | Название на русском |
| 3 | `POI Name (EN)` | Text | Название на английском |
| 4 | `City Location` | Linked Record | Связь с городом |
| 5 | `Prefecture (RU)` | Text/Select | Префектура на русском |
| 6 | `Prefecture (EN)` | Text/Select | Префектура на английском |
| 7 | `Place ID` | Text | Google Place ID |
| 8 | `Description (RU)` | Long Text | Описание на русском |
| 9 | `Description (EN)` | Long Text | Описание на английском |
| 10 | `POI Category (RU)` | Multi-select | Категории на русском |
| 11 | `POI Category (EN)` | Multi-select | Категории на английском |
| 12 | `Working Hours` | Text | Часы работы |
| 13 | `Website` | URL | Сайт POI |
| 14 | `Tickets 1` | ? | Билет тип 1 |
| 15 | `Tickets 2` | ? | Билет тип 2 |
| 16 | `Tickets 3` | ? | Билет тип 3 |
| 17 | `Attachments` | Attachments | Прикрепленные файлы |
| 18 | `Regions` | Linked Record | Связь с регионом |
| 19 | `Notes` | Long Text | Внутренние заметки |

## 🗺️ Полный маппинг

### Форма POI → Локальная БД → Airtable

| Поле в форме | Локальная БД | Airtable | Статус |
|--------------|--------------|----------|--------|
| `name_ru` | `name_ru` ✅ | `POI Name (RU)` | ✅ |
| `name_en` | `name_en` ✅ | `POI Name (EN)` | ✅ |
| `pref_ru` | ❌ **Нет** | `Prefecture (RU)` | ⚠️ Добавить |
| `pref_en` | ❌ **Нет** | `Prefecture (EN)` | ⚠️ Добавить |
| `cats_ru[]` | `category` ⚠️ (только одна) | `POI Category (RU)` (multi) | ⚠️ Исправить |
| `cats_en[]` | ❌ **Нет** | `POI Category (EN)` (multi) | ⚠️ Добавить |
| `place_id` | `place_id` ✅ | `Place ID` | ✅ |
| `website` | ❌ **Нет** | `Website` | ⚠️ Добавить |
| `hours` | ❌ **Нет** | `Working Hours` | ⚠️ Добавить |
| `desc_ru` | `description` ⚠️ (одно поле) | `Description (RU)` | ⚠️ Разделить |
| `desc_en` | ❌ **Нет** | `Description (EN)` | ⚠️ Добавить |
| `published` | `published` ✅ | ? (не видно) | ❓ |
| `notes` | ❌ **Нет** | `Notes` | ⚠️ Добавить |
| - | `business_id` ✅ | `POI ID` | ✅ |
| - | `city_id` ✅ | `City Location` (linked) | ✅ |
| - | `region_id` ✅ | `Regions` (linked) | ✅ |

## 🔧 Необходимые изменения

### 1. Обновить локальную БД

Нужно добавить недостающие поля:

```sql
-- Префектуры
ALTER TABLE pois ADD COLUMN prefecture_ru TEXT;
ALTER TABLE pois ADD COLUMN prefecture_en TEXT;

-- Категории (JSON массивы)
ALTER TABLE pois ADD COLUMN categories_ru TEXT;  -- JSON: ["cat1", "cat2"]
ALTER TABLE pois ADD COLUMN categories_en TEXT;  -- JSON: ["cat1", "cat2"]

-- Описания (разделить на RU и EN)
ALTER TABLE pois ADD COLUMN description_ru TEXT;
ALTER TABLE pois ADD COLUMN description_en TEXT;

-- Дополнительные поля
ALTER TABLE pois ADD COLUMN website TEXT;
ALTER TABLE pois ADD COLUMN working_hours TEXT;
ALTER TABLE pois ADD COLUMN notes TEXT;

-- Удалить старые поля (после миграции данных):
-- ALTER TABLE pois DROP COLUMN category;  (заменить на categories_ru/en)
-- ALTER TABLE pois DROP COLUMN description; (заменить на description_ru/en)
```

### 2. Маппинг для синхронизации

```javascript
// Форма → Airtable
const formToAirtable = {
  name_ru:     'POI Name (RU)',
  name_en:     'POI Name (EN)',
  pref_ru:     'Prefecture (RU)',
  pref_en:     'Prefecture (EN)',
  cats_ru:     'POI Category (RU)',    // multi-select
  cats_en:     'POI Category (EN)',    // multi-select
  place_id:    'Place ID',
  website:     'Website',
  hours:       'Working Hours',
  desc_ru:     'Description (RU)',
  desc_en:     'Description (EN)',
  notes:       'Notes',
  city_id:     'City Location',        // linked record
  region_id:   'Regions'               // linked record
};

// Airtable → Локальная БД
const airtableToDb = {
  'POI ID':             'business_id',
  'POI Name (RU)':      'name_ru',
  'POI Name (EN)':      'name_en',
  'Prefecture (RU)':    'prefecture_ru',
  'Prefecture (EN)':    'prefecture_en',
  'POI Category (RU)':  'categories_ru',  // JSON.stringify
  'POI Category (EN)':  'categories_en',  // JSON.stringify
  'Place ID':           'place_id',
  'Website':            'website',
  'Working Hours':      'working_hours',
  'Description (RU)':   'description_ru',
  'Description (EN)':   'description_en',
  'Notes':              'notes',
  'City Location':      'city_id',        // extract ID from linked
  'Regions':            'region_id'       // extract ID from linked
};
```

## ❓ Вопросы для уточнения

### 1. Published/Опубликовано

Не видно на скриншотах поля "Published" или "Опубликовано".

❓ **Есть ли такое поле в Airtable?**
- A) Да, называется: "_______"
- B) Нет, не используется
- C) Используется другое поле для статуса

### 2. Tickets 1, 2, 3

Видны поля `Tickets 1`, `Tickets 2`, `Tickets 3`.

❓ **Что это за поля?**
- A) Linked records к отдельной таблице билетов
- B) Текстовые поля с информацией о билетах
- C) Числовые поля с ценами
- D) Другое

### 3. Attachments

❓ **Используется ли поле Attachments в форме?**
- A) Да, нужно добавить в форму
- B) Нет, не используется в админке
- C) Планируется в будущем

## ✅ Готово к реализации

Теперь у меня есть все названия полей! Могу приступить к:

1. ✅ Обновлению схемы локальной БД
2. ✅ Созданию миграции данных
3. ✅ Настройке синхронизации форма ↔ БД
4. ✅ Настройке синхронизации БД ↔ Airtable
5. ✅ Тестированию полного цикла

---

**Ответьте на 3 вопроса выше, и я начну реализацию!** 🚀

