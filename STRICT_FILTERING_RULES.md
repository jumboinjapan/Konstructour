# СТРОГИЕ ПРАВИЛА FILTERING.MD

## 🚫 ЗАПРЕЩЕНО НАВСЕГДА

### 1. Создание локальных данных
```php
// ❌ НИКОГДА НЕ ДЕЛАЙТЕ ТАК:
$db->saveRegion($data);
$db->saveCity($data);
$db->savePoi($data);

// ❌ НИКОГДА НЕ СОЗДАВАЙТЕ ТЕСТОВЫЕ ДАННЫЕ:
$testData = ['name_ru' => 'Тест', 'business_id' => 'REG-001'];
```

### 2. Использование локальных данных без Airtable
```php
// ❌ НИКОГДА НЕ ДЕЛАЙТЕ ТАК:
$regions = $db->getRegions(); // Без проверки синхронизации
$cities = $db->getCitiesByRegion($id); // Без проверки Airtable
```

### 3. Создание тестовых скриптов
```php
// ❌ НИКОГДА НЕ СОЗДАВАЙТЕ:
create-test-data.php
test-data.php
sample-data.php
mock-data.php
```

## ✅ РАЗРЕШЕНО ТОЛЬКО

### 1. Чтение из Airtable
```php
// ✅ ПРАВИЛЬНО:
$airtable = new AirtableDataSource();
$regions = $airtable->getRegionsFromAirtable();
```

### 2. Синхронизация с Airtable
```php
// ✅ ПРАВИЛЬНО (только в sync-скриптах):
$db->saveRegion($airtableData); // Данные из Airtable
```

### 3. Использование локального кэша
```php
// ✅ ПРАВИЛЬНО (только если синхронизировано):
DataGuard::enforceAirtableOnly(); // Проверка
$regions = $db->getValidRegions(); // Только валидные данные
```

## 🔧 ИНСТРУМЕНТЫ КОНТРОЛЯ

### 1. Проверка нарушений
```bash
php api/check-filtering-violations.php
```

### 2. Принудительная проверка
```php
DataGuard::enforceAirtableOnly(); // Выбросит ошибку если нет Airtable
DataGuard::enforceNoLocalCreation(); // Выбросит ошибку при создании
DataGuard::enforceNoTestData(); // Выбросит ошибку при тестовых данных
```

### 3. API только для Airtable
```bash
# data-api.php теперь работает только с Airtable
curl "http://localhost:8000/api/data-api.php?action=regions"
```

## 📋 ЧЕКЛИСТ ПЕРЕД КОММИТОМ

- [ ] Нет создания локальных данных
- [ ] Нет тестовых скриптов
- [ ] Все данные берутся из Airtable
- [ ] Локальная база используется только как кэш
- [ ] DataGuard::enforceAirtableOnly() работает
- [ ] php api/check-filtering-violations.php проходит без ошибок

## 🚨 АВТОМАТИЧЕСКИЕ ПРОВЕРКИ

1. **DataGuard** - автоматически проверяет все операции
2. **check-filtering-violations.php** - проверяет состояние системы
3. **data-api.php** - теперь работает только с Airtable
4. **data-api-old.php** - старый API (архив)

## 💡 ПРИНЦИПЫ

1. **Airtable - единственный источник данных**
2. **Локальная база - только кэш**
3. **Никаких тестовых данных локально**
4. **Все создание данных только в Airtable**
5. **Строгие проверки на каждом шаге**
