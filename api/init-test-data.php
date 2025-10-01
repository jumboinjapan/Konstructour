<?php
// Инициализация тестовых данных для базы
require_once 'database.php';
require_once 'filter-constants.php';

$db = new Database();

echo "Инициализация тестовых данных...\n";

// 1. Создаем регионы
$regions = [
    ['id' => 'recREG001', 'business_id' => 'REG-0001', 'name_ru' => 'Канто', 'name_en' => 'Kanto'],
    ['id' => 'recREG002', 'business_id' => 'REG-0002', 'name_ru' => 'Кансай', 'name_en' => 'Kansai'],
    ['id' => 'recREG003', 'business_id' => 'REG-0003', 'name_ru' => 'Тюбу', 'name_en' => 'Chubu'],
    ['id' => 'recREG004', 'business_id' => 'REG-0004', 'name_ru' => 'Тохоку', 'name_en' => 'Tohoku'],
    ['id' => 'recREG005', 'business_id' => 'REG-0005', 'name_ru' => 'Кюсю', 'name_en' => 'Kyushu'],
    ['id' => 'recREG006', 'business_id' => 'REG-0006', 'name_ru' => 'Сикоку', 'name_en' => 'Shikoku'],
    ['id' => 'recREG007', 'business_id' => 'REG-0007', 'name_ru' => 'Тюгоку', 'name_en' => 'Chugoku'],
    ['id' => 'recREG008', 'business_id' => 'REG-0008', 'name_ru' => 'Хоккайдо', 'name_en' => 'Hokkaido'],
    ['id' => 'recREG009', 'business_id' => 'REG-0009', 'name_ru' => 'Окинава', 'name_en' => 'Okinawa']
];

foreach ($regions as $region) {
    $db->saveRegion($region);
    echo "Создан регион: {$region['name_ru']} ({$region['business_id']})\n";
}

// 2. Создаем города
$cities = [
    // Канто (REG-0001)
    ['id' => 'recCTY001', 'business_id' => 'CTY-0001', 'name_ru' => 'Токио', 'name_en' => 'Tokyo', 'region_id' => 'recREG001'],
    ['id' => 'recCTY002', 'business_id' => 'CTY-0002', 'name_ru' => 'Иокогама', 'name_en' => 'Yokohama', 'region_id' => 'recREG001'],
    ['id' => 'recCTY003', 'business_id' => 'CTY-0003', 'name_ru' => 'Кавасаки', 'name_en' => 'Kawasaki', 'region_id' => 'recREG001'],
    
    // Кансай (REG-0002)
    ['id' => 'recCTY004', 'business_id' => 'CTY-0004', 'name_ru' => 'Осака', 'name_en' => 'Osaka', 'region_id' => 'recREG002'],
    ['id' => 'recCTY005', 'business_id' => 'CTY-0005', 'name_ru' => 'Киото', 'name_en' => 'Kyoto', 'region_id' => 'recREG002'],
    ['id' => 'recCTY006', 'business_id' => 'CTY-0006', 'name_ru' => 'Кобе', 'name_en' => 'Kobe', 'region_id' => 'recREG002'],
    
    // Тюбу (REG-0003)
    ['id' => 'recCTY007', 'business_id' => 'CTY-0007', 'name_ru' => 'Нагоя', 'name_en' => 'Nagoya', 'region_id' => 'recREG003'],
    ['id' => 'recCTY008', 'business_id' => 'CTY-0008', 'name_ru' => 'Ниигата', 'name_en' => 'Niigata', 'region_id' => 'recREG003'],
    
    // Локации
    ['id' => 'recLOC001', 'business_id' => 'LOC-0001', 'name_ru' => 'Гора Фудзи', 'name_en' => 'Mount Fuji', 'region_id' => 'recREG001'],
    ['id' => 'recLOC002', 'business_id' => 'LOC-0002', 'name_ru' => 'Озеро Бива', 'name_en' => 'Lake Biwa', 'region_id' => 'recREG002'],
    ['id' => 'recLOC003', 'business_id' => 'LOC-0003', 'name_ru' => 'Замок Мацумото', 'name_en' => 'Matsumoto Castle', 'region_id' => 'recREG003']
];

foreach ($cities as $city) {
    $db->saveCity($city);
    echo "Создан город: {$city['name_ru']} ({$city['business_id']})\n";
}

// 3. Создаем POI
$pois = [
    // Токио
    ['id' => 'recPOI001', 'business_id' => 'POI-000001', 'name_ru' => 'Сэнсо-дзи', 'name_en' => 'Senso-ji', 'category' => 'Buddhist Temple', 'city_id' => 'recCTY001', 'region_id' => 'recREG001'],
    ['id' => 'recPOI002', 'business_id' => 'POI-000002', 'name_ru' => 'Императорский дворец', 'name_en' => 'Imperial Palace', 'category' => 'Historic Site', 'city_id' => 'recCTY001', 'region_id' => 'recREG001'],
    
    // Киото
    ['id' => 'recPOI003', 'business_id' => 'POI-000003', 'name_ru' => 'Кинкаку-дзи', 'name_en' => 'Kinkaku-ji', 'category' => 'Buddhist Temple', 'city_id' => 'recCTY005', 'region_id' => 'recREG002'],
    ['id' => 'recPOI004', 'business_id' => 'POI-000004', 'name_ru' => 'Гинкаку-дзи', 'name_en' => 'Ginkaku-ji', 'category' => 'Buddhist Temple', 'city_id' => 'recCTY005', 'region_id' => 'recREG002'],
    ['id' => 'recPOI005', 'business_id' => 'POI-000005', 'name_ru' => 'Фусими Инари', 'name_en' => 'Fushimi Inari', 'category' => 'Shinto Shrine', 'city_id' => 'recCTY005', 'region_id' => 'recREG002'],
    
    // Осака
    ['id' => 'recPOI006', 'business_id' => 'POI-000006', 'name_ru' => 'Замок Осака', 'name_en' => 'Osaka Castle', 'category' => 'Historic Site', 'city_id' => 'recCTY004', 'region_id' => 'recREG002'],
    
    // Гора Фудзи
    ['id' => 'recPOI007', 'business_id' => 'POI-000007', 'name_ru' => 'Вершина Фудзи', 'name_en' => 'Fuji Summit', 'category' => 'Natural Landmark', 'city_id' => 'recLOC001', 'region_id' => 'recREG001']
];

foreach ($pois as $poi) {
    $db->savePoi($poi);
    echo "Создан POI: {$poi['name_ru']} ({$poi['business_id']})\n";
}

echo "\nТестовые данные созданы успешно!\n";
echo "Регионов: " . count($regions) . "\n";
echo "Городов: " . count($cities) . "\n";
echo "POI: " . count($pois) . "\n";
?>
