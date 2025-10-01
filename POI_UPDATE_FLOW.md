# Процесс обновления POI — Текущая реализация и исправления

**Дата**: 1 октября 2025

## ⚠️ ТЕКУЩИЙ ПРОЦЕСС (ПРОБЛЕМА!)

### Что происходит сейчас:

```
[Пользователь] 
    ↓
[Нажимает кнопку "✏️ Редактировать" на карточке POI]
    ↓
[Открывается модальное окно openCreatePOIModal(poi)]
    ↓
[Пользователь редактирует данные]
    ↓
[Нажимает кнопку "Обновить"]
    ↓
[Валидация формы]
    ↓
[Собираются данные в объект data]
    ↓
[done(data) - форма закрывается]
    ↓
[Object.assign(poi, {...data}) - обновление в памяти]
    ↓
[renderPois() - перерисовка карточек]
    ↓
❌ ДАННЫЕ ТОЛЬКО В ПАМЯТИ БРАУЗЕРА!
❌ НЕ сохранены в локальной БД!
❌ НЕ синхронизированы с Airtable!
```

### Что не так:

```javascript
// Обработчик edit-poi
const data = await openCreatePOIModal(poi);
if (!data) return;

// Обновляем ТОЛЬКО локальный объект в памяти
Object.assign(poi, {
  name: data.name_ru,
  name_en: data.name_en,
  // ... и т.д.
});

// Перерисовываем
await renderPois();

// ❌ НЕТ вызова API для сохранения!
// ❌ При перезагрузке страницы данные потеряются!
```

## ✅ ПРАВИЛЬНЫЙ ПРОЦЕСС (НУЖНО РЕАЛИЗОВАТЬ)

### Как должно работать:

```
[Пользователь] 
    ↓
[Нажимает "✏️ Редактировать"]
    ↓
[Открывается форма]
    ↓
[Редактирует данные]
    ↓
[Нажимает "Обновить"]
    ↓
[Валидация формы]
    ↓
[Собираются данные]
    ↓
[done(data)]
    ↓
┌─────────────────────────────┐
│ СОХРАНЕНИЕ В БД И AIRTABLE  │
├─────────────────────────────┤
│ 1. POST → /api/save-poi.php │
│ 2. Сохранение в SQLite      │
│ 3. Синхронизация Airtable   │
│ 4. Сохранение Tickets       │
└─────────────────────────────┘
    ↓
[Получен ответ от API]
    ↓
[Object.assign(poi, data) - обновление локального объекта]
    ↓
[renderPois() - перерисовка]
    ↓
✅ Данные сохранены в БД
✅ Данные синхронизированы с Airtable
✅ Билеты обновлены
✅ При перезагрузке данные сохранятся
```

## 🔧 Необходимые изменения

### В обработчике edit-poi (строка ~2285)

**Сейчас:**
```javascript
const data = await openCreatePOIModal(poi);
if (!data) return;

// Обновляем ТОЛЬКО в памяти
Object.assign(poi, {...});
await renderPois();
```

**Должно быть:**
```javascript
const data = await openCreatePOIModal(poi);
if (!data) return;

try {
  // Показываем индикатор загрузки
  cards.innerHTML = '<div class="loading">Сохранение...</div>';
  
  // СОХРАНЕНИЕ В БД И AIRTABLE
  const saveResponse = await fetch('/api/save-poi.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      id: poi.id,
      airtable_id: poi.airtable_id, // Если есть
      business_id: poi.poi_id,
      name_ru: data.name_ru,
      name_en: data.name_en,
      prefecture_ru: data.pref_ru,
      prefecture_en: data.pref_en,
      categories_ru: data.cats_ru,
      categories_en: data.cats_en,
      place_id: data.place_id,
      website: data.website,
      working_hours: data.hours,
      description_ru: data.desc_ru,
      description_en: data.desc_en,
      notes: data.notes,
      published: data.published,
      tickets: data.tickets,
      city_id: selectedCity.id,
      region_id: selectedRegion.id
    })
  });
  
  const result = await saveResponse.json();
  
  if (!result.ok) {
    throw new Error(result.error || 'Ошибка сохранения');
  }
  
  // Обновляем локальный объект ПОСЛЕ успешного сохранения
  Object.assign(poi, {
    name: data.name_ru,
    name_en: data.name_en,
    type: data.cats_ru[0] || poi.type,
    // ... и т.д.
  });
  
  // Перерисовываем
  await renderPois();
  
  // Уведомление
  if (window.osNotify) {
    window.osNotify('POI сохранён', `${data.name_ru} обновлён в БД и Airtable`);
  }
  
} catch(e) {
  alert('Не удалось сохранить POI: ' + e.message);
  // Откатываем изменения или показываем ошибку
}
```

## 📊 Сравнение

| Действие | Сейчас | Должно быть |
|----------|--------|-------------|
| **Сохранение в память** | ✅ Да | ✅ Да |
| **Сохранение в SQLite** | ❌ Нет | ✅ Да |
| **Синхронизация Airtable** | ❌ Нет | ✅ Да |
| **Сохранение Tickets** | ❌ Нет | ✅ Да |
| **Сохраняется при перезагрузке** | ❌ Нет | ✅ Да |
| **Индикатор загрузки** | ❌ Нет | ✅ Да |
| **Обработка ошибок** | ⚠️ Частично | ✅ Полная |

## ⚠️ Критическая проблема

**При текущей реализации:**

1. Пользователь редактирует POI
2. Нажимает "Обновить"
3. Видит изменения на карточке ✅
4. **Перезагружает страницу (F5)**
5. ❌ **Изменения ПОТЕРЯНЫ!**
6. Карточка показывает старые данные из БД

**Почему:** Данные обновились только в `poisByCity` объекте в памяти браузера, но не были сохранены в базу данных.

---

## 🎯 Что нужно исправить

Добавлю вызов API для сохранения данных в обработчике `edit-poi`.

