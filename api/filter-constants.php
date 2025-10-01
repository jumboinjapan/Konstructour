<?php
/**
 * Константы для жесткой фиксации фильтрации по уровням данных
 * Основано на структуре: Регионы -> Города -> POI
 */

// Паттерны для валидации business_id
define('REGION_ID_PATTERN', '/^REG-\d+$/');
define('CITY_ID_PATTERN', '/^(CTY|LOC)-\d+$/');
define('POI_ID_PATTERN', '/^POI-\d+$/');

// Типы ID для городов
define('CITY_TYPE_CTY', 'CTY');
define('CITY_TYPE_LOC', 'LOC');

// Максимальные номера для генерации ID
define('MAX_REGION_NUMBER', 9);
define('MAX_CITY_NUMBER', 32);
define('MAX_POI_NUMBER', 999999);

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
?>
