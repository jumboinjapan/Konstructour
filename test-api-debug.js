// Тест API для отладки Airtable статуса
console.log('🔍 Начинаем тест Airtable API...');

// Функция для очистки кэшей
function clearLocalCaches(){
  try{
    [
      'konstructour_regions_cache_v1',
      'konstructour_cities_cache_v2', 
      'konstructour_pois_cache_v2',
      'konstructour_last_refresh',
      'konstructour_last_sync'
    ].forEach(k=> localStorage.removeItem(k));
    console.log('✅ Локальные кэши очищены');
  }catch(_){ 
    console.warn('⚠️ Ошибка очистки кэшей');
  }
}

// Функция проверки PAT токена
function isValidPatToken(token) {
  return token && /^pat\.[A-Za-z0-9_\-]{20,}$/.test(token);
}

// Тест 1: Health endpoint
async function testHealth() {
  console.log('🔍 Тест 1: Health endpoint');
  try {
    const response = await fetch('api/health.php');
    const data = await response.json();
    console.log('Health response:', data);
    
    if (data.airtable) {
      console.log(`Airtable в health: ${data.airtable.ok ? '✅ OK' : '❌ Ошибка'}`);
      if (data.airtable.error) {
        console.log('Ошибка Airtable:', data.airtable.error);
      }
    }
    return data;
  } catch (error) {
    console.error('❌ Ошибка health:', error);
    return null;
  }
}

// Тест 2: Server keys
async function testServerKeys() {
  console.log('🔍 Тест 2: Server keys');
  try {
    const response = await fetch(`api/test-proxy.php?provider=server_keys&_=${Date.now()}`, {
      cache: 'no-store'
    });
    const data = await response.json();
    console.log('Server keys response:', data);
    
    const hasAirtableKey = !!(data && data.keys && data.keys.airtable);
    console.log(`Airtable ключ на сервере: ${hasAirtableKey ? '✅ Есть' : '❌ Нет'}`);
    
    return data;
  } catch (error) {
    console.error('❌ Ошибка server keys:', error);
    return null;
  }
}

// Тест 3: Airtable whoami
async function testAirtableWhoami() {
  console.log('🔍 Тест 3: Airtable whoami');
  try {
    const response = await fetch(`api/test-proxy.php?provider=airtable&_=${Date.now()}`, {
      method: 'POST',
      cache: 'no-store',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ whoami: true })
    });
    const data = await response.json();
    console.log('Airtable whoami response:', data);
    
    const authOk = !!(data && data.ok && data.auth !== false && data.status !== 401);
    console.log(`Airtable аутентификация: ${authOk ? '✅ OK' : '❌ Ошибка'}`);
    
    if (data.error) {
      console.log('Ошибка Airtable:', data.error);
    }
    
    return data;
  } catch (error) {
    console.error('❌ Ошибка Airtable whoami:', error);
    return null;
  }
}

// Тест 4: Проверка конфигурации
async function testConfig() {
  console.log('🔍 Тест 4: Конфигурация');
  try {
    const response = await fetch('api/config.php');
    const data = await response.json();
    console.log('Config response:', data);
    
    if (data.airtable_registry) {
      console.log('Airtable registry:', data.airtable_registry);
    }
    
    return data;
  } catch (error) {
    console.error('❌ Ошибка config:', error);
    return null;
  }
}

// Запуск всех тестов
async function runAllTests() {
  console.log('🚀 Запускаем все тесты...');
  
  // Очищаем кэши
  clearLocalCaches();
  
  // Запускаем тесты последовательно
  const health = await testHealth();
  const keys = await testServerKeys();
  const whoami = await testAirtableWhoami();
  const config = await testConfig();
  
  console.log('📊 Результаты тестов:');
  console.log('- Health endpoint:', health ? '✅' : '❌');
  console.log('- Server keys:', keys ? '✅' : '❌');
  console.log('- Airtable whoami:', whoami ? '✅' : '❌');
  console.log('- Config:', config ? '✅' : '❌');
  
  // Анализ результатов
  const hasKey = !!(keys && keys.keys && keys.keys.airtable);
  const authOk = !!(whoami && whoami.ok && whoami.auth !== false);
  
  console.log('\n🎯 Итоговый анализ:');
  console.log(`Ключ на сервере: ${hasKey ? '✅ Есть' : '❌ Нет'}`);
  console.log(`Аутентификация: ${authOk ? '✅ OK' : '❌ Ошибка'}`);
  
  if (!hasKey) {
    console.log('💡 Рекомендация: Добавьте реальный Airtable PAT через админ панель');
  } else if (!authOk) {
    console.log('💡 Рекомендация: Проверьте правильность PAT токена');
  } else {
    console.log('🎉 Все работает корректно!');
  }
}

// Экспорт функций для использования в консоли
window.testAirtable = {
  runAllTests,
  testHealth,
  testServerKeys,
  testAirtableWhoami,
  testConfig,
  clearLocalCaches,
  isValidPatToken
};

console.log('✅ Тестовые функции загружены. Используйте:');
console.log('- testAirtable.runAllTests() - запустить все тесты');
console.log('- testAirtable.testHealth() - тест health endpoint');
console.log('- testAirtable.testServerKeys() - тест server keys');
console.log('- testAirtable.testAirtableWhoami() - тест Airtable whoami');
console.log('- testAirtable.clearLocalCaches() - очистить кэши');
