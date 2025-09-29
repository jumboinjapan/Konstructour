<?php
// Простой Pull из Airtable - только загрузка карточек
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/_airtable-common.php';

function ok($p){ echo json_encode($p, JSON_UNESCAPED_UNICODE); exit; }
function fail($m,$e=[]){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>$m]+$e, JSON_UNESCAPED_UNICODE); exit; }

try {
  $cfg = air_cfg();
  $results = [];

  // === ЗАГРУЖАЕМ РЕГИОНЫ ===
  $allowedRegions = ['REG-0001','REG-0002','REG-0003','REG-0004','REG-0005','REG-0006','REG-0007','REG-0008','REG-0009'];
  
  $parts = [];
  foreach ($allowedRegions as $v) {
    $parts[] = "{fldwlHyd89p3lRsQe}='" . addslashes($v) . "'";
  }
  $formula = 'OR('.implode(',', $parts).')';
  
  $params = ['pageSize'=>100, 'filterByFormula'=>$formula];
  [$code,$out,$err,$url] = air_call('GET','', null, $params);
  if ($code>=400) fail("Airtable regions $code", ['url'=>$url]);
  
  $j = json_decode($out,true);
  $regions = [];
  foreach (($j['records']??[]) as $rec){
    $f = $rec['fields'] ?? [];
    $regions[] = [
      'id' => $f['fldwlHyd89p3lRsQe'] ?? '',
      'name_ru' => $f['Name (RU)'] ?? '',
      'name_en' => $f['Name (EN)'] ?? '',
      'airtable_id' => $rec['id']
    ];
  }
  $results['regions'] = $regions;

  // === ЗАГРУЖАЕМ ГОРОДА ===
  $params = ['pageSize'=>100];
  [$code,$out,$err,$url] = air_call('GET','tblbSajWkzI8X7M4U', null, $params);
  if ($code>=400) fail("Airtable cities $code", ['url'=>$url]);
  
  $j = json_decode($out,true);
  $cities = [];
  foreach (($j['records']??[]) as $rec){
    $f = $rec['fields'] ?? [];
    $cities[] = [
      'business_id' => $f['fldkJevgUbtAbM4vr'] ?? '',
      'name_ru' => $f['Name (RU)'] ?? '',
      'name_en' => $f['Name (EN)'] ?? '',
      'region' => $f['Region'] ?? '',
      'lat' => isset($f['Latitude']) ? floatval($f['Latitude']) : null,
      'lng' => isset($f['Longitude']) ? floatval($f['Longitude']) : null,
      'place_id' => $f['Place ID'] ?? '',
      'airtable_id' => $rec['id']
    ];
  }
  $results['cities'] = $cities;

  // === ЗАГРУЖАЕМ POI ===
  $params = ['pageSize'=>100];
  [$code,$out,$err,$url] = air_call('GET','tblXXXXXX', null, $params); // Замените на правильный ID таблицы POI
  if ($code>=400) {
    // Если таблица POI не найдена, просто пропускаем
    $results['pois'] = [];
  } else {
    $j = json_decode($out,true);
    $pois = [];
    foreach (($j['records']??[]) as $rec){
      $f = $rec['fields'] ?? [];
      $pois[] = [
        'business_id' => $f['fldXXXXXX'] ?? '', // Замените на правильный ID поля
        'name_ru' => $f['Name (RU)'] ?? '',
        'name_en' => $f['Name (EN)'] ?? '',
        'city' => $f['City'] ?? '',
        'lat' => isset($f['Latitude']) ? floatval($f['Latitude']) : null,
        'lng' => isset($f['Longitude']) ? floatval($f['Longitude']) : null,
        'airtable_id' => $rec['id']
      ];
    }
    $results['pois'] = $pois;
  }

  ok([
    'ok' => true,
    'message' => 'Данные успешно загружены из Airtable',
    'data' => $results,
    'summary' => [
      'regions_count' => count($regions),
      'cities_count' => count($cities),
      'pois_count' => count($results['pois']),
      'loaded_at' => gmdate('c')
    ]
  ]);

} catch (Throwable $e) {
  fail($e->getMessage());
}
?>
