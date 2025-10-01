# Анализ синхронизации POI — Отчет о несоответствиях

**Дата**: 1 октября 2025  
**Задача**: Синхронизация формы POI ↔ Локальная БД ↔ Airtable

## 📋 Текущая ситуация

### Форма POI отправляет (из `openCreatePOIModal`):

```javascript
{
  name_ru: "...",        // ✅
  name_en: "...",        // ✅
  pref_ru: "tokyo",      // ❓ Префектура RU
  pref_en: "tokyo",      // ❓ Prefecture EN
  cats_ru: ["...", "..."], // ❓ Категории RU (массив)
  cats_en: ["...", "..."], // ❓ Категории EN (массив)
  place_id: "ChIJ...",   // ✅
  website: "https://...", // ❓ Сайт
  hours: "09:00-17:00",  // ❓ Часы работы
  desc_ru: "...",        // ❓ Описание RU
  desc_en: "...",        // ❓ Description EN
  published: true/false, // ✅
  notes: "..."           // ❓ Заметки (внутренние)
}
```

### Локальная БД (SQLite) — таблица `pois`:

```sql
CREATE TABLE pois (
    id TEXT PRIMARY KEY,
    name_ru TEXT NOT NULL,          -- ✅ Есть
    name_en TEXT,                   -- ✅ Есть
    category TEXT,                  -- ⚠️ ОДНА категория, не массив!
    place_id TEXT,                  -- ✅ Есть
    published BOOLEAN,              -- ✅ Есть
    business_id TEXT,               -- ✅ Есть (POI ID)
    city_id TEXT,                   -- ✅ Есть
    region_id TEXT,                 -- ✅ Есть
    description TEXT,               -- ⚠️ ОДНО описание, не RU/EN!
    latitude REAL,                  -- ❌ Не используется в форме
    longitude REAL,                 -- ❌ Не используется в форме
    created_at DATETIME,
    updated_at DATETIME
)
```

**Отсутствующие поля в локальной БД:**
- ❌ `prefecture_ru` / `prefecture_en` (префектуры)
- ❌ `categories_ru` / `categories_en` (массивы категорий)
- ❌ `website` (сайт)
- ❌ `hours` (часы работы)
- ❌ `description_ru` / `description_en` (отдельные описания)
- ❌ `notes` (заметки)

### Airtable — таблица POI (предполагаемая структура):

Нужно уточнить точные названия полей! ⚠️

**Возможные поля:**
```
- Name (RU)              → name_ru
- Name (EN)              → name_en
- Категория (RU) ???     → cats_ru (массив?)
- Category (EN) ???      → cats_en (массив?)
- Префектура (RU) ???    → pref_ru
- Prefecture (EN) ???    → pref_en
- Google Place ID        → place_id
- Сайт / Website ???     → website
- Часы работы / Hours ???→ hours
- Описание (RU) ???      → desc_ru
- Description (EN) ???   → desc_en
- Опубликовано ???       → published
- Заметки / Notes ???    → notes
- POI ID                 → business_id
- City                   → city_id (linked record?)
- Region ???             → region_id (linked record?)
```

## 🔴 КРИТИЧЕСКИЕ ВОПРОСЫ

### 1. **Категории POI**

**В форме:** Массив checkbox (можно выбрать несколько)
```javascript
cats_ru: ["Буддийский храм", "Музей"]
cats_en: ["Buddhist Temple", "Museum"]
```

**В локальной БД:** Одно текстовое поле
```sql
category TEXT  -- только одна категория!
```

❓ **ВОПРОС 1:** Как хранить категории в Airtable?
- A) Одно поле (первая выбранная категория)
- B) Multi-select поле (все выбранные категории)
- C) Два поля: categories_ru и categories_en (multi-select)
- D) Другой вариант?

❓ **ВОПРОС 2:** Какое точное название поля категорий в Airtable?
- "Категория"
- "Category"
- "Категории"
- "Categories"
- Другое?

### 2. **Префектуры**

**В форме:** Два поля (RU и EN, синхронизированные)
```javascript
pref_ru: "tokyo"  // value, отображается как "Токио"
pref_en: "tokyo"  // value, отображается как "Tokyo"
```

**В локальной БД:** Отсутствует! ❌

❓ **ВОПРОС 3:** Как хранить префектуры?
- A) Добавить поля в локальную БД: `prefecture_ru`, `prefecture_en`
- B) Хранить только в Airtable
- C) Не хранить вообще
- D) Хранить как часть описания

❓ **ВОПРОС 4:** Какое название поля префектуры в Airtable?
- "Префектура (RU)"
- "Prefecture (EN)"
- "Prefecture" (одно поле)
- Другое?

### 3. **Описания**

**В форме:** Два textarea (RU и EN отдельно)
```javascript
desc_ru: "Описание на русском"
desc_en: "Description in English"
```

**В локальной БД:** Одно текстовое поле
```sql
description TEXT  -- только одно описание!
```

❓ **ВОПРОС 5:** Как хранить описания?
- A) Два поля в БД: `description_ru`, `description_en`
- B) Одно поле (только RU или EN)
- C) Объединять в одно поле через разделитель
- D) Другой вариант?

❓ **ВОПРОС 6:** Названия полей описаний в Airtable?
- "Описание (RU)" и "Description (EN)"
- "Description" (одно поле)
- Другое?

### 4. **Дополнительные поля**

**В форме, но НЕТ в БД:**
- `website` (Сайт)
- `hours` (Часы работы)
- `notes` (Заметки)

❓ **ВОПРОС 7:** Нужно ли добавлять эти поля в локальную БД?
- A) Да, добавить все три поля
- B) Хранить только в Airtable
- C) Не хранить вообще

❓ **ВОПРОС 8:** Названия этих полей в Airtable?
- Website / Сайт: "___"
- Hours / Часы работы: "___"
- Notes / Заметки: "___"

### 5. **Связанные записи (Linked Records)**

❓ **ВОПРОС 9:** Как POI связаны с городами в Airtable?
- A) Поле "City" (linked record)
- B) Поле "Город" (linked record)
- C) Текстовое поле с city_id
- D) Другое название: "___"

❓ **ВОПРОС 10:** Как POI связаны с регионами в Airtable?
- A) Поле "Region" (linked record)
- B) Поле "Регион" (linked record)
- C) Не связаны напрямую (только через City)
- D) Другое: "___"

## 📊 Таблица маппинга (требует уточнения)

| Поле в форме | Локальная БД | Airtable | Статус |
|--------------|--------------|----------|--------|
| `name_ru` | `name_ru` | `Name (RU)` ??? | ❓ |
| `name_en` | `name_en` | `Name (EN)` ??? | ❓ |
| `pref_ru` | ❌ Нет | ??? | ❓ |
| `pref_en` | ❌ Нет | ??? | ❓ |
| `cats_ru[]` | `category` (одна) | ??? | ❓ |
| `cats_en[]` | ❌ Нет | ??? | ❓ |
| `place_id` | `place_id` | `Google Place ID` ??? | ❓ |
| `website` | ❌ Нет | ??? | ❓ |
| `hours` | ❌ Нет | ??? | ❓ |
| `desc_ru` | `description` (одно) | ??? | ❓ |
| `desc_en` | ❌ Нет | ??? | ❓ |
| `published` | `published` | `Published` ??? | ❓ |
| `notes` | ❌ Нет | ??? | ❓ |
| - | `business_id` | `POI ID` ??? | ❓ |
| - | `city_id` | `City` ??? | ❓ |
| - | `region_id` | `Region` ??? | ❓ |

## 🎯 Рекомендуемые действия

### Вариант 1: Расширить локальную БД (рекомендуется)

```sql
ALTER TABLE pois ADD COLUMN prefecture_ru TEXT;
ALTER TABLE pois ADD COLUMN prefecture_en TEXT;
ALTER TABLE pois ADD COLUMN categories_ru TEXT;  -- JSON массив
ALTER TABLE pois ADD COLUMN categories_en TEXT;  -- JSON массив
ALTER TABLE pois ADD COLUMN description_ru TEXT;
ALTER TABLE pois ADD COLUMN description_en TEXT;
ALTER TABLE pois ADD COLUMN website TEXT;
ALTER TABLE pois ADD COLUMN hours TEXT;
ALTER TABLE pois ADD COLUMN notes TEXT;

-- Удалить старые поля:
-- ALTER TABLE pois DROP COLUMN category;
-- ALTER TABLE pois DROP COLUMN description;
```

**Преимущества:**
- ✅ Полное соответствие форме и Airtable
- ✅ Все данные доступны локально
- ✅ Можно работать offline

### Вариант 2: Упростить форму

Убрать из формы поля которых нет в БД.

**Недостатки:**
- ❌ Потеря функциональности
- ❌ Меньше информации о POI

## 📝 Что мне нужно от вас

Пожалуйста, уточните **точные названия полей в Airtable** для таблицы POI:

### Обязательные поля:
1. **Название RU**: "___"
2. **Название EN**: "___"
3. **Категории RU**: "___" (тип поля: single-select / multi-select / text?)
4. **Категории EN**: "___" (тип поля: single-select / multi-select / text?)
5. **POI ID**: "___"
6. **Связь с городом**: "___" (название поля linked record)

### Дополнительные поля:
7. **Префектура RU**: "___" (если есть)
8. **Prefecture EN**: "___" (если есть)
9. **Google Place ID**: "___" (если есть)
10. **Website / Сайт**: "___" (если есть)
11. **Hours / Часы**: "___" (если есть)
12. **Описание RU**: "___" (если есть)
13. **Description EN**: "___" (если есть)
14. **Published / Опубликовано**: "___" (если есть)
15. **Notes / Заметки**: "___" (если есть)
16. **Связь с регионом**: "___" (если есть, linked record?)

## 🔍 Как предоставить информацию

Можете просто скопировать список полей из Airtable или ответить в формате:

```
1. Название RU: "Name (RU)"
2. Название EN: "Name (EN)"
3. Категории RU: "Категории" (multi-select)
4. Категории EN: "Categories" (multi-select)
...
```

После получения этой информации я смогу:
- ✅ Обновить локальную БД схему
- ✅ Создать правильный маппинг полей
- ✅ Настроить синхронизацию форма ↔ БД ↔ Airtable
- ✅ Убедиться что все данные сохраняются корректно

---

**Ожидаю ваших уточнений!** 📝

