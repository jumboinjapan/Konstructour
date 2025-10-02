<?php
// api/local-dev-fallback.php
// Fallback для локальной разработки без токена Airtable

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('respond')) {
    function respond($ok, $data = [], $code = 200) {
        http_response_code($code);
        echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// Проверяем, есть ли токен Airtable
$hasToken = false;
try {
    $airtable = new AirtableDataSource();
    $hasToken = true;
} catch (Exception $e) {
    $hasToken = false;
}

if (!$hasToken) {
    // Возвращаем демо-данные для локальной разработки
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'regions':
            respond(true, [
                'items' => [
                    [
                        'id' => 'demo-region-1',
                        'business_id' => 'REG-0001',
                        'name_ru' => 'Кансай (демо)',
                        'name_en' => 'Kansai (demo)'
                    ],
                    [
                        'id' => 'demo-region-2', 
                        'business_id' => 'REG-0002',
                        'name_ru' => 'Канто (демо)',
                        'name_en' => 'Kanto (demo)'
                    ]
                ]
            ]);
            break;
            
        case 'cities':
            $regionId = $_GET['region_id'] ?? '';
            if ($regionId === 'REG-0001') {
                respond(true, [
                    'items' => [
                        [
                            'id' => 'demo-city-1',
                            'business_id' => 'CTY-0001',
                            'name_ru' => 'Киото (демо)',
                            'name_en' => 'Kyoto (demo)',
                            'type' => 'city',
                            'region_id' => 'REG-0001'
                        ],
                        [
                            'id' => 'demo-city-2',
                            'business_id' => 'CTY-0002', 
                            'name_ru' => 'Осака (демо)',
                            'name_en' => 'Osaka (demo)',
                            'type' => 'city',
                            'region_id' => 'REG-0001'
                        ]
                    ]
                ]);
            } else {
                respond(true, ['items' => []]);
            }
            break;
            
        case 'pois':
            $cityId = $_GET['city_id'] ?? '';
            if ($cityId === 'CTY-0001') {
                respond(true, [
                    'items' => [
                        [
                            'id' => 'demo-poi-1',
                            'business_id' => 'POI-000001',
                            'name_ru' => 'Кинкаку-дзи (демо)',
                            'name_en' => 'Kinkaku-ji (demo)',
                            'category' => 'Temple',
                            'city_id' => 'CTY-0001',
                            'region_id' => 'REG-0001'
                        ],
                        [
                            'id' => 'demo-poi-2',
                            'business_id' => 'POI-000002',
                            'name_ru' => 'Фушими Инари (демо)',
                            'name_en' => 'Fushimi Inari (demo)',
                            'category' => 'Shrine',
                            'city_id' => 'CTY-0001',
                            'region_id' => 'REG-0001'
                        ]
                    ]
                ]);
            } else {
                respond(true, ['items' => []]);
            }
            break;
            
        case 'city-stats':
            $regionId = $_GET['region_id'] ?? '';
            if ($regionId === 'REG-0001') {
                respond(true, [
                    'stats' => [
                        'demo-city-1' => 2, // Киото: 2 POI
                        'demo-city-2' => 0  // Осака: 0 POI
                    ]
                ]);
            } else {
                respond(true, ['stats' => []]);
            }
            break;
            
        case 'stats':
            respond(true, [
                'stats' => [
                    'regions' => 2,
                    'cities' => 2,
                    'pois' => 2,
                    'last_sync' => null,
                    'cities_by_region' => ['REG-0001' => 2]
                ]
            ]);
            break;
            
        default:
            respond(false, ['error' => 'Action not supported in demo mode'], 400);
    }
} else {
    // Если токен есть, перенаправляем на основной API
    require_once 'data-api.php';
}
?>
