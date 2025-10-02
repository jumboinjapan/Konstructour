# 🎯 Filtering - Жесткая фиксация фильтрации по уровням данных

**Дата**: 1 октября 2025  
**Статус**: ✅ Реализовано  
**Версия**: 1.0

---

## 🎯 **Принципы работы с данными**

### **Источник истины:**
- **Airtable** - единственный источник истины для всех данных
- **Локальная база данных** - только кэш для отображения и удобства работы

### **Правила работы с данными:**

#### **A. Для отображения информации:**
- ✅ **Читаем данные** из Airtable через API
- ✅ **Кэшируем** в локальной БД для быстрого отображения
- ✅ **Синхронизируем** регулярно для актуальности

#### **B. Для создания новых записей:**
- ✅ **Создаем** в удобном формате через интерфейс
- ✅ **Сохраняем** в локальную БД как черновик
- ✅ **Синхронизируем** с Airtable при сохранении

#### **C. Запрещено:**
- ❌ **Создавать записи** только в локальной БД
- ❌ **Изменять данные** без синхронизации с Airtable
- ❌ **Использовать** устаревшие данные из локальной БД

---

## 🚫 **СТРОГИЕ ПРАВИЛА - НАРУШЕНИЯ НЕДОПУСТИМЫ**

### **1. ЗАПРЕЩЕНО НАВСЕГДА**

#### **A. Создание локальных данных:**
```php
// ❌ НИКОГДА НЕ ДЕЛАЙТЕ ТАК:
$db->saveRegion($data);
$db->saveCity($data);
$db->savePoi($data);

// ❌ НИКОГДА НЕ СОЗДАВАЙТЕ ТЕСТОВЫЕ ДАННЫЕ:
$testData = ['name_ru' => 'Тест', 'business_id' => 'REG-001'];
```

#### **B. Использование локальных данных без Airtable:**
```php
// ❌ НИКОГДА НЕ ДЕЛАЙТЕ ТАК:
$regions = $db->getRegions(); // Без проверки синхронизации
$cities = $db->getCitiesByRegion($id); // Без проверки Airtable
```

#### **C. Создание тестовых скриптов:**
```php
// ❌ НИКОГДА НЕ СОЗДАВАЙТЕ:
create-test-data.php
test-data.php
sample-data.php
mock-data.php
```

### **2. РАЗРЕШЕНО ТОЛЬКО**

#### **A. Чтение из Airtable:**
```php
// ✅ ПРАВИЛЬНО:
$airtable = new AirtableDataSource();
$regions = $airtable->getRegionsFromAirtable();
```

#### **B. Синхронизация с Airtable:**
```php
// ✅ ПРАВИЛЬНО (только в sync-скриптах):
$db->saveRegion($airtableData); // Данные из Airtable
```

#### **C. Использование локального кэша:**
```php
// ✅ ПРАВИЛЬНО (только если синхронизировано):
DataGuard::enforceAirtableOnly(); // Проверка
$regions = $db->getValidRegions(); // Только валидные данные
```

### **3. ИНСТРУМЕНТЫ КОНТРОЛЯ**

#### **A. Автоматические проверки:**
```php
DataGuard::enforceAirtableOnly(); // Выбросит ошибку если нет Airtable
DataGuard::enforceNoLocalCreation(); // Выбросит ошибку при создании
DataGuard::enforceNoTestData(); // Выбросит ошибку при тестовых данных
```

#### **B. Проверка нарушений:**
```bash
php api/check-filtering-violations.php
```

#### **C. API только для Airtable:**
```bash
# data-api.php теперь работает только с Airtable
curl "http://localhost:8000/api/data-api.php?action=regions"
```

### **4. ЧЕКЛИСТ ПЕРЕД КОММИТОМ**

- [ ] Нет создания локальных данных
- [ ] Нет тестовых скриптов
- [ ] Все данные берутся из Airtable
- [ ] Локальная база используется только как кэш
- [ ] DataGuard::enforceAirtableOnly() работает
- [ ] php api/check-filtering-violations.php проходит без ошибок

### **5. АВТОМАТИЧЕСКИЕ ПРОВЕРКИ**

1. **DataGuard** - автоматически проверяет все операции
2. **check-filtering-violations.php** - проверяет состояние системы
3. **data-api.php** - теперь работает только с Airtable
4. **data-api-old.php** - старый API (архив)

---

## 📊 **Структура данных и оптимальные фильтры**

### **Иерархия данных:**
```
Регионы (9) → Города (32) → POI (N)
```

### **1. Регионы (9 записей)**
- **Оптимальный фильтр**: `business_id`
- **Формат**: `REG-0001`, `REG-0002`, ..., `REG-0009`
- **Паттерн**: `/^REG-\d+$/`
- **Примеры**: 
  - `REG-0001` - Канто
  - `REG-0002` - Кансай
  - `REG-0008` - Хоккайдо

### **2. Города (32 записи)**
- **Оптимальный фильтр**: `business_id`
- **Формат**: `CTY-XXXX` или `LOC-XXXX`
- **Паттерн**: `/^(CTY|LOC)-\d+$/`
- **Типы**:
  - `CTY-` - обычные города (28 записей)
  - `LOC-` - локации/достопримечательности (3 записи)
- **Примеры**:
  - `CTY-0001` - Токио
  - `CTY-0008` - Киото
  - `LOC-0001` - Гора Фудзи

### **3. POI (Points of Interest)**
- **Оптимальный фильтр**: `business_id`
- **Формат**: `POI-XXXXXX`
- **Паттерн**: `/^POI-\d+$/`
- **Примеры**:
  - `POI-000001` - Кинкакудзи
  - `POI-000002` - Гинкакудзи
  - `POI-000003` - Тестовый храм

---

## 🔧 **Техническая реализация**

### **Константы фильтрации:**
```php
// Паттерны для валидации
define('REGION_ID_PATTERN', '/^REG-\d+$/');
define('CITY_ID_PATTERN', '/^(CTY|LOC)-\d+$/');
define('POI_ID_PATTERN', '/^POI-\d+$/');

// Максимальные номера
define('MAX_REGION_NUMBER', 9);
define('MAX_CITY_NUMBER', 32);
define('MAX_POI_NUMBER', 999999);

// Типы ID для городов
define('CITY_TYPE_CTY', 'CTY');
define('CITY_TYPE_LOC', 'LOC');
```

### **Функции валидации:**
```php
/**
 * Валидация business_id по типу
 */
function validateBusinessId($id, $type) {
    switch ($type) {
        case 'region':
            return preg_match(REGION_ID_PATTERN, $id);
        case 'city':
            return preg_match(CITY_ID_PATTERN, $id);
        case 'poi':
            return preg_match(POI_ID_PATTERN, $id);
        default:
            return false;
    }
}

/**
 * Генерация следующего business_id
 */
function generateNextBusinessId($type, $currentMax = 0) {
    $prefix = '';
    $maxNumber = 0;
    
    switch ($type) {
        case 'region':
            $prefix = 'REG';
            $maxNumber = MAX_REGION_NUMBER;
            break;
        case 'city':
            $prefix = 'CTY'; // По умолчанию CTY, можно изменить на LOC
            $maxNumber = MAX_CITY_NUMBER;
            break;
        case 'poi':
            $prefix = 'POI';
            $maxNumber = MAX_POI_NUMBER;
            break;
        default:
            throw new Exception("Неизвестный тип ID: $type");
    }
    
    $nextNumber = $currentMax + 1;
    if ($nextNumber > $maxNumber) {
        throw new Exception("Превышен максимальный номер для типа $type: $maxNumber");
    }
    
    return $prefix . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
}

/**
 * Извлечение номера из business_id
 */
function extractNumberFromBusinessId($businessId) {
    if (preg_match('/^[A-Z]+-(\d+)$/', $businessId, $matches)) {
        return intval($matches[1]);
    }
    return 0;
}

/**
 * Получение типа ID
 */
function getBusinessIdType($businessId) {
    if (preg_match(REGION_ID_PATTERN, $businessId)) {
        return 'region';
    } elseif (preg_match(CITY_ID_PATTERN, $businessId)) {
        return 'city';
    } elseif (preg_match(POI_ID_PATTERN, $businessId)) {
        return 'poi';
    }
    return null;
}

/**
 * Проверка связности ID (город принадлежит региону, POI принадлежит городу)
 */
function validateHierarchy($childId, $parentId) {
    $childType = getBusinessIdType($childId);
    $parentType = getBusinessIdType($parentId);
    
    if (!$childType || !$parentType) {
        return false;
    }
    
    // POI должен принадлежать городу
    if ($childType === 'poi' && $parentType === 'city') {
        return true;
    }
    
    // Город должен принадлежать региону
    if ($childType === 'city' && $parentType === 'region') {
        return true;
    }
    
    return false;
}

/**
 * Получение оптимального поля для фильтрации по типу
 */
function getOptimalFilterField($type) {
    switch ($type) {
        case 'region':
            return 'business_id';
        case 'city':
            return 'business_id';
        case 'poi':
            return 'business_id';
        default:
            return 'id';
    }
}

/**
 * Получение связующего поля для иерархии
 */
function getHierarchyField($type) {
    switch ($type) {
        case 'city':
            return 'region_id';
        case 'poi':
            return 'city_id';
        default:
            return null;
    }
}
```

---

## 🚫 **Что НЕ используется для фильтрации**

### **❌ Airtable record ID:**
- `recRB3qLChpLwKH5K` - нестабильный, меняется
- `rec9r4ypqZyki2QkT` - нечеловекочитаемый
- `rec20157cf344eeddf5` - случайный

### **❌ Локальные ID:**
- `1`, `2`, `3` - не уникальны между таблицами
- `uuid` - слишком длинные
- `timestamp` - не последовательные

### **❌ Названия:**
- `"Киото"` - могут дублироваться
- `"Tokyo"` - зависят от языка
- `"Гора Фудзи"` - могут изменяться

---

## 📈 **Преимущества жесткой фиксации**

### **1. Производительность:**
- ✅ **Индексируемые поля** - быстрый поиск
- ✅ **Уникальные ключи** - нет дубликатов
- ✅ **Человекочитаемые** - легко отлаживать

### **2. Надежность:**
- ✅ **Валидация на входе** - ошибки отлавливаются сразу
- ✅ **Консистентность** - один формат для всех операций
- ✅ **Предсказуемость** - всегда знаем, что ожидать

### **3. Масштабируемость:**
- ✅ **Последовательные ID** - легко определить следующий
- ✅ **Типизированные ID** - CTY vs LOC
- ✅ **Иерархические связи** - четкая структура

---

## 🎯 **Правила использования**

### **1. Всегда используйте business_id для фильтрации:**
```php
// ✅ ПРАВИЛЬНО
$cities = $db->getCitiesByRegion('REG-0002');
$pois = $db->getPoisByCity('CTY-0008');

// ❌ НЕПРАВИЛЬНО
$cities = $db->getCitiesByRegion('recRB3qLChpLwKH5K');
$pois = $db->getPoisByCity('rec9r4ypqZyki2QkT');
```

### **2. Валидируйте ID перед использованием:**
```php
// ✅ ПРАВИЛЬНО
if (validateBusinessId($regionId, 'region')) {
    $cities = $db->getCitiesByRegion($regionId);
}

// ❌ НЕПРАВИЛЬНО
$cities = $db->getCitiesByRegion($regionId); // без валидации
```

### **3. Генерируйте ID последовательно:**
```php
// ✅ ПРАВИЛЬНО
$nextPoiId = generateNextBusinessId('poi', $currentMax);

// ❌ НЕПРАВИЛЬНО
$randomId = 'POI-' . rand(1000, 9999);
```

---

## 🔧 **API с жесткой валидацией**

### **Валидация в data-api.php:**
```php
case 'cities':
    if ($method === 'GET') {
        $regionId = $_GET['region_id'] ?? '';
        if (!$regionId) {
            respond(false, ['error' => 'Region ID required'], 400);
        }
        
        // ЖЕСТКАЯ ВАЛИДАЦИЯ: проверяем формат region_id
        if (!validateBusinessId($regionId, 'region')) {
            respond(false, ['error' => 'Invalid region ID format. Expected: REG-XXXX'], 400);
        }
        
        $cities = $db->getCitiesByRegion($regionId);
        respond(true, ['items' => $cities]);
    }
    break;

case 'pois':
    if ($method === 'GET') {
        $cityId = $_GET['city_id'] ?? '';
        if (!$cityId) {
            respond(false, ['error' => 'City ID required'], 400);
        }
        
        // ЖЕСТКАЯ ВАЛИДАЦИЯ: проверяем формат city_id
        if (!validateBusinessId($cityId, 'city')) {
            respond(false, ['error' => 'Invalid city ID format. Expected: CTY-XXXX or LOC-XXXX'], 400);
        }
        
        $pois = $db->getPoisByCity($cityId);
        respond(true, ['items' => $pois]);
    }
    break;
```

---

## 🔄 **Синхронизация с Airtable**

### **Принципы синхронизации:**

#### **1. Направление синхронизации:**
- **Airtable → Локальная БД** - основное направление
- **Локальная БД → Airtable** - только при создании/изменении записей

#### **2. Процесс синхронизации:**
1. **Читаем данные** из Airtable через API
2. **Валидируем** business_id согласно паттернам
3. **Проверяем связи** между таблицами
4. **Сохраняем** в локальную БД только валидные данные
5. **Логируем ошибки** для отладки

#### **3. Обработка ошибок:**
- **Пропускаем записи** с невалидными business_id
- **Пропускаем записи** с несуществующими связями
- **Логируем предупреждения** для отладки
- **Не создаем** записи локально без синхронизации с Airtable

### **Поиск по business_id вместо Airtable record ID:**
```php
// СТРОГО: Получаем business_id региона из поля Regions
$regionBusinessId = null;
if (isset($record['fields']['Regions'])) {
    $regions = $record['fields']['Regions'];
    if (is_array($regions) && !empty($regions)) {
        $regionBusinessId = $regions[0];
    } elseif (is_string($regions)) {
        $regionBusinessId = $regions;
    }
}

// СТРОГО: Найдем регион по business_id (REG-XXXX)
$regionId = null;
if ($regionBusinessId && preg_match('/^REG-\d+$/', $regionBusinessId)) {
    $regions = $db->getRegions();
    foreach ($regions as $region) {
        if ($region['business_id'] === $regionBusinessId) {
            $regionId = $region['id'];
            break;
        }
    }
}

// СТРОГО: Найдем город по business_id из поля City Location
$cityId = null;
if (isset($record['fields']['City Location']) && is_array($record['fields']['City Location'])) {
    $cityBusinessId = $record['fields']['City Location'][0];
    if (preg_match('/^(CTY|LOC)-\d+$/', $cityBusinessId)) {
        // Ищем город по business_id
        $cities = $db->getAllCities();
        foreach ($cities as $city) {
            if ($city['business_id'] === $cityBusinessId) {
                $cityId = $city['id'];
                break;
            }
        }
    }
}
```

---

## ⚠️ **Строгие запреты**

### **❌ НИКОГДА не делайте:**

1. **Создание записей только в локальной БД:**
   ```php
   // ❌ ЗАПРЕЩЕНО
   $db->savePoi($poiData); // Без синхронизации с Airtable
   ```

2. **Использование устаревших данных:**
   ```php
   // ❌ ЗАПРЕЩЕНО
   $pois = $db->getPoisByCity($cityId); // Без проверки актуальности
   ```

3. **Изменение данных без синхронизации:**
   ```php
   // ❌ ЗАПРЕЩЕНО
   $db->updatePoi($poiId, $data); // Без отправки в Airtable
   ```

4. **Создание тестовых данных:**
   ```php
   // ❌ ЗАПРЕЩЕНО
   $testPoi = ['business_id' => 'POI-999999', 'name_ru' => 'Тест'];
   $db->savePoi($testPoi);
   ```

### **✅ ВСЕГДА делайте:**

1. **Синхронизируйте с Airtable:**
   ```php
   // ✅ ПРАВИЛЬНО
   $airtableResult = syncWithAirtable($poiData);
   if ($airtableResult['ok']) {
       $db->savePoi($poiData);
   }
   ```

2. **Проверяйте актуальность данных:**
   ```php
   // ✅ ПРАВИЛЬНО
   if (isDataFresh()) {
       $pois = $db->getPoisByCity($cityId);
   } else {
       $pois = await syncFromAirtable();
   }
   ```

3. **Валидируйте business_id:**
   ```php
   // ✅ ПРАВИЛЬНО
   if (validateBusinessId($businessId, 'poi')) {
       // Обрабатываем данные
   }
   ```

---

## 📋 **Чек-лист для разработчиков**

### **При создании API:**
- [ ] Используете `business_id` для фильтрации?
- [ ] Валидируете формат ID?
- [ ] Проверяете связность иерархии?
- [ ] Возвращаете понятные ошибки?

### **При синхронизации:**
- [ ] Ищете по `business_id`, а не по Airtable ID?
- [ ] Сохраняете `business_id` в БД?
- [ ] Обновляете связанные записи?

### **При фильтрации:**
- [ ] Используете правильные паттерны?
- [ ] Проверяете существование родительских записей?
- [ ] Обрабатываете ошибки валидации?

---

## 🎉 **Результат**

**Система использует жестко зафиксированные параметры фильтрации:**

- ✅ **Регионы**: `REG-XXXX` (9 записей)
- ✅ **Города**: `CTY-XXXX` / `LOC-XXXX` (32 записи)
- ✅ **POI**: `POI-XXXXXX` (N записей)

**Никаких догадок, никаких изменений - только четкие правила!** 🎯

---

## 📚 **Связанные файлы**

- `api/filter-constants.php` - Константы и функции валидации
- `api/data-api.php` - API с жесткой валидацией
- `api/sync-airtable.php` - Синхронизация по business_id
- `api/save-poi.php` - Создание POI с валидацией
- `api/enforce-filtering.php` - Строгий контроль соблюдения правил
- `.github/workflows/check-filtering-compliance.yml` - Автоматическая проверка

## 🔒 **АВТОМАТИЧЕСКИЕ ПРОВЕРКИ**

- **enforce-filtering.php** - API endpoint для проверки соблюдения правил
- **GitHub Actions** - автоматическая проверка при каждом коммите
- **DataGuard** - проверяет доступность Airtable перед использованием локальных данных
- **check-filtering-violations.php** - диагностический скрипт для проверки нарушений

## ✅ **ТЕКУЩИЙ СТАТУС СИСТЕМЫ**

### **Навигация работает корректно:**
- ✅ Регионы загружаются без двойной загрузки
- ✅ Города загружаются без двойной загрузки  
- ✅ POI загружаются без двойной загрузки
- ✅ Синхронизация работает без сброса и перезагрузки
- ✅ Breadcrumbs работают правильно
- ✅ Состояние восстанавливается корректно после синхронизации

### **Исправленные проблемы:**
- **Двойная загрузка при синхронизации** - убраны дублирующие вызовы `renderCities()` и `renderPois()`
- **Неправильное восстановление состояния** - `selectedRegion` и `selectedCity` восстанавливаются ПЕРЕД загрузкой данных
- **Нарушение Filtering.md** - убраны все тестовые данные, система работает только с Airtable

### **Текущие задачи:**
- 🔄 Исправление пилюлей (счетчиков POI) - в процессе
- 🔄 Настройка рабочего токена Airtable для полной синхронизации

---

*Документ создан 1 октября 2025*  
*Версия 1.0 - Жесткая фиксация фильтрации*
