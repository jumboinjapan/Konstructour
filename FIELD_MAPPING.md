# Сопоставление полей между локальной БД и Airtable

## 🎯 Принципы синхронизации

1. **НЕ создаем новые колонки** в Airtable
2. **Используем только существующие поля** в обеих системах
3. **Airtable Record ID** используется как первичный ключ для связи
4. **Business ID** используется для идентификации записей

## 📊 Сопоставление полей

### Регионы (Regions)

| Локальная БД | Airtable | Описание |
|--------------|----------|----------|
| `id` | `id` (Record ID) | Airtable Record ID - первичный ключ |
| `name_ru` | `Name (RU)` | Название на русском |
| `name_en` | `Name (EN)` | Название на английском |
| `business_id` | `ID` | Business ID (REG-XXX) |

**Airtable таблица**: `tblbSajWkzI8X7M4U`

### Города (Cities)

| Локальная БД | Airtable | Описание |
|--------------|----------|----------|
| `id` | `id` (Record ID) | Airtable Record ID - первичный ключ |
| `name_ru` | `Name (RU)` | Название на русском |
| `name_en` | `Name (EN)` | Название на английском |
| `business_id` | `A ID` | Business ID (CTY-XXX или LOC-XXX) |
| `type` | `Type` | Тип (city/location) |
| `region_id` | `Region` | Связь с регионом (Record ID) |

**Airtable таблица**: `tblHaHc9NV0mA8bSa`

### POI (Points of Interest)

| Локальная БД | Airtable | Описание |
|--------------|----------|----------|
| `id` | `id` (Record ID) | Airtable Record ID - первичный ключ |
| `name_ru` | `Name (RU)` | Название на русском |
| `name_en` | `Name (EN)` | Название на английском |
| `business_id` | `POI ID` | Business ID (POI-XXX) |
| `category` | `Category` | Категория |
| `description` | `Description` | Описание |
| `city_id` | `City ID` | Связь с городом (Record ID) |
| `region_id` | `Region` | Связь с регионом (Record ID) |

**Airtable таблица**: `tbl8X7M4U`

## 🔄 Логика синхронизации

### 1. Airtable → Локальная БД

```php
// Для каждой записи из Airtable:
$data = [
    'id' => $record['id'], // Airtable Record ID
    'name_ru' => $fields['Name (RU)'] ?? 'Unknown',
    'name_en' => $fields['Name (EN)'] ?? null,
    'business_id' => $fields['ID'] ?? null
];

// Проверяем существование записи
$existing = $db->getRecord($data['id']);
if ($existing) {
    // Обновляем только если данные изменились
    if ($hasChanges($existing, $data)) {
        $db->updateRecord($data['id'], $data);
    }
} else {
    // Создаем новую запись
    $db->saveRecord($data);
}
```

### 2. Локальная БД → Airtable

```php
// Для каждой записи из локальной БД:
$fields = [
    'Name (RU)' => $record['name_ru']
];

if ($record['name_en']) {
    $fields['Name (EN)'] = $record['name_en'];
}

if ($record['business_id']) {
    $fields['ID'] = $record['business_id'];
}

// Обновляем запись в Airtable
updateAirtableRecord($tableId, $record['id'], $fields);
```

## ⚠️ Важные моменты

### Связи между таблицами

1. **Регион → Город**: Используется Airtable Record ID региона
2. **Город → POI**: Используется Airtable Record ID города  
3. **Регион → POI**: Используется Airtable Record ID региона

### Обработка пустых полей

- Если поле в Airtable пустое, используем значение по умолчанию
- Если поле в локальной БД пустое, не передаем его в Airtable
- Обязательные поля: `name_ru`, `id` (Record ID)

### Конфликты данных

- **Airtable является источником истины** для новых записей
- **Локальная БД является источником истины** для изменений
- При конфликтах приоритет у Airtable

## 🧪 Тестирование

### Проверка структуры Airtable

```bash
# Проверить какие поля есть в Airtable
curl "https://yourdomain.com/api/check-airtable-structure.php"
```

### Тестовая синхронизация

```bash
# Запустить тестовую синхронизацию
curl "https://yourdomain.com/api/sync-correct-test.php?action=sync"
```

### Реальная синхронизация

```bash
# Настроить токен Airtable
export AIRTABLE_PAT="your_token_here"

# Запустить реальную синхронизацию
curl "https://yourdomain.com/api/sync-correct.php?action=sync"
```

## 🔧 Настройка

### 1. Получить Airtable токен

1. Перейдите на [Airtable Developer Hub](https://airtable.com/create/tokens)
2. Создайте Personal Access Token
3. Выберите разрешения: `data.records:read`, `data.records:write`
4. Выберите базу данных `apppwhjFN82N9zNqm`

### 2. Настроить токен

```bash
# Вариант 1: Переменная окружения
export AIRTABLE_PAT="your_token_here"

# Вариант 2: Файл .env
echo "AIRTABLE_PAT=your_token_here" >> .env
```

### 3. Переключить на реальную синхронизацию

В `site-admin/databases.html` замените:
```javascript
// Было:
const response = await fetch('/api/sync-correct-test.php?action=sync', {

// Стало:
const response = await fetch('/api/sync-correct.php?action=sync', {
```

## 📝 Логирование

Все операции синхронизации логируются:
- Успешные операции: количество синхронизированных записей
- Ошибки: детальное описание проблем
- Конфликты: информация о разрешении конфликтов

Логи доступны в дашборде синхронизации: `/site-admin/sync-dashboard.html`
