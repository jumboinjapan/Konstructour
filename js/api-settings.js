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

    load();
  });
})();


