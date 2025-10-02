<?php
// api/create-test-data.php
// Создание тестовых данных для проверки пилюлей

require_once 'database.php';

try {
    $db = new Database();
    
    // Очищаем базу
    $db->clearAll();
    echo "🗑️ База данных очищена\n";
    
    // Создаем тестовые регионы
    $regions = [
        ['id' => 'recRegion001', 'business_id' => 'REG-0001', 'name_ru' => 'Кансай', 'name_en' => 'Kansai'],
        ['id' => 'recRegion002', 'business_id' => 'REG-0002', 'name_ru' => 'Канто', 'name_en' => 'Kanto'],
        ['id' => 'recRegion003', 'business_id' => 'REG-0003', 'name_ru' => 'Тюгоку', 'name_en' => 'Chugoku']
    ];
    
    foreach ($regions as $region) {
        $db->saveRegion($region);
        echo "✅ Регион: {$region['name_ru']} ({$region['business_id']})\n";
    }
    
    // Создаем тестовые города для Кансай
    $cities = [
        ['id' => 'recCity001', 'business_id' => 'CTY-0001', 'name_ru' => 'Киото', 'name_en' => 'Kyoto', 'region_id' => 'recRegion001'],
        ['id' => 'recCity002', 'business_id' => 'CTY-0002', 'name_ru' => 'Осака', 'name_en' => 'Osaka', 'region_id' => 'recRegion001'],
        ['id' => 'recCity003', 'business_id' => 'CTY-0003', 'name_ru' => 'Кобе', 'name_en' => 'Kobe', 'region_id' => 'recRegion001']
    ];
    
    foreach ($cities as $city) {
        $db->saveCity($city);
        echo "✅ Город: {$city['name_ru']} ({$city['business_id']})\n";
    }
    
    // Создаем тестовые POI для Киото
    $pois = [
        ['id' => 'recPoi001', 'business_id' => 'POI-000001', 'name_ru' => 'Кинкаку-дзи', 'name_en' => 'Kinkaku-ji', 'city_id' => 'recCity001', 'region_id' => 'recRegion001'],
        ['id' => 'recPoi002', 'business_id' => 'POI-000002', 'name_ru' => 'Фушими Инари', 'name_en' => 'Fushimi Inari', 'city_id' => 'recCity001', 'region_id' => 'recRegion001'],
        ['id' => 'recPoi003', 'business_id' => 'POI-000003', 'name_ru' => 'Киёмидзу-дэра', 'name_en' => 'Kiyomizu-dera', 'city_id' => 'recCity001', 'region_id' => 'recRegion001']
    ];
    
    foreach ($pois as $poi) {
        $db->savePoi($poi);
        echo "✅ POI: {$poi['name_ru']} ({$poi['business_id']})\n";
    }
    
    // Создаем тестовые POI для Осаки
    $osakaPois = [
        ['id' => 'recPoi004', 'business_id' => 'POI-000004', 'name_ru' => 'Осака-дзё', 'name_en' => 'Osaka Castle', 'city_id' => 'recCity002', 'region_id' => 'recRegion001'],
        ['id' => 'recPoi005', 'business_id' => 'POI-000005', 'name_ru' => 'Дотонбори', 'name_en' => 'Dotonbori', 'city_id' => 'recCity002', 'region_id' => 'recRegion001']
    ];
    
    foreach ($osakaPois as $poi) {
        $db->savePoi($poi);
        echo "✅ POI: {$poi['name_ru']} ({$poi['business_id']})\n";
    }
    
    echo "\n🎉 Тестовые данные созданы успешно!\n";
    echo "📊 Статистика:\n";
    
    $validData = $db->getAllValidData();
    echo "- Регионы: {$validData['stats']['regions']}\n";
    echo "- Города: {$validData['stats']['cities']}\n";
    echo "- POI: {$validData['stats']['pois']}\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>
