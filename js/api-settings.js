// API Settings page logic (client-side storage)
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    if (!window.adminAuth || !window.adminAuth.validateSession()) return;

    const fields = [
      'airtable_api_key','airtable_base_id','airtable_table',
      'openai_api_key','openai_model',
      'gsheets_api_key','gsheets_spreadsheet_id',
      'gmaps_api_key','gmaps_region',
      'recaptcha_site_key','recaptcha_secret',
      'brilliantdb_api_key','brilliantdb_base_url','brilliantdb_collection'
    ];

    const STORAGE_KEY = 'konstructour_api_settings';
    const statusText = document.getElementById('statusText');

    function load(){
      try{
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return;
        const data = JSON.parse(raw);
        fields.forEach(id => {
          const el = document.getElementById(id);
          if (el && data[id] !== undefined) el.value = data[id];
        });
        setStatus('Настройки загружены');
      }catch(e){ setStatus('Ошибка загрузки настроек'); }
    }

    function save(){
      const data = {};
      fields.forEach(id => {
        const el = document.getElementById(id);
        if (el) data[id] = el.value;
      });
      localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
      setStatus('Настройки сохранены');
    }

    function clearAll(){
      localStorage.removeItem(STORAGE_KEY);
      fields.forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
      setStatus('Очищено');
    }

    function setStatus(msg){ if(statusText){ statusText.textContent = msg; setTimeout(()=>statusText.textContent='', 3000);} }

    document.getElementById('btnSaveAll')?.addEventListener('click', save);
    document.getElementById('btnSaveAirtable')?.addEventListener('click', save);
    document.getElementById('btnSaveOpenAI')?.addEventListener('click', save);
    document.getElementById('btnSaveGSheets')?.addEventListener('click', save);
    document.getElementById('btnSaveGMaps')?.addEventListener('click', save);
    document.getElementById('btnSaveRecaptcha')?.addEventListener('click', save);
    document.getElementById('btnSaveBrilliant')?.addEventListener('click', save);
    document.getElementById('btnClear')?.addEventListener('click', clearAll);
    document.getElementById('btnTestAll')?.addEventListener('click', function(){
      // simple local validation
      const missing = fields.filter(id => {
        const el = document.getElementById(id);
        return el && el.value.trim() === '' && /api_key|secret|site_key/.test(id);
      });
      if (missing.length){
        setStatus('Заполните ключи: ' + missing.map(m=>m.replace(/_/g,' ')).join(', '));
      } else {
        setStatus('Конфигурация выглядит валидной (локальная проверка)');
      }
    });

    // Provider-specific test buttons (client-side heuristics only)
    document.getElementById('btnTestAirtable')?.addEventListener('click', function(){
      const key = document.getElementById('airtable_api_key')?.value.trim();
      const base = document.getElementById('airtable_base_id')?.value.trim();
      const table = document.getElementById('airtable_table')?.value.trim();
      if (!key || !base || !table) return setStatus('Airtable: заполните API Key, Base ID и Table');
      setStatus('Airtable: ключи сохранены локально. Для реальной проверки нужен серверный прокси.');
    });

    document.getElementById('btnTestOpenAI')?.addEventListener('click', function(){
      const key = document.getElementById('openai_api_key')?.value.trim();
      const model = document.getElementById('openai_model')?.value.trim();
      if (!key || !model) return setStatus('OpenAI: заполните API Key и модель');
      setStatus('OpenAI: ключ сохранён локально. Рекомендуется серверный вызов через прокси.');
    });

    document.getElementById('btnTestGSheets')?.addEventListener('click', function(){
      const key = document.getElementById('gsheets_api_key')?.value.trim();
      const sheet = document.getElementById('gsheets_spreadsheet_id')?.value.trim();
      if (!key || !sheet) return setStatus('Google Sheets: заполните API Key и Spreadsheet ID');
      setStatus('Google Sheets: базовая проверка пройдена локально. Для записи нужен сервисный аккаунт.');
    });

    document.getElementById('btnTestGMaps')?.addEventListener('click', function(){
      const key = document.getElementById('gmaps_api_key')?.value.trim();
      if (!key) return setStatus('Google Maps: заполните API Key');
      setStatus('Google Maps: ключ сохранён. Ограничьте ключ по доменам.');
    });

    document.getElementById('btnTestRecaptcha')?.addEventListener('click', function(){
      const siteKey = document.getElementById('recaptcha_site_key')?.value.trim();
      const secret = document.getElementById('recaptcha_secret')?.value.trim();
      if (!siteKey || !secret) return setStatus('reCAPTCHA: заполните Site Key и Secret');
      setStatus('reCAPTCHA: ключи сохранены. Сервер должен валидировать токен.');
    });

    document.getElementById('btnTestBrilliant')?.addEventListener('click', function(){
      const key = document.getElementById('brilliantdb_api_key')?.value.trim();
      const base = document.getElementById('brilliantdb_base_url')?.value.trim();
      if (!key || !base) return setStatus('Brilliant DB: заполните API Key и Endpoint Base');
      setStatus('Brilliant DB: ключи сохранены локально. Для реального теста добавим серверный эндпоинт.');
    });

    load();
  });
})();


