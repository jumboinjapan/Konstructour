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

    function cardStatus(id, state, text){
      const wrap = document.getElementById(id);
      if (!wrap) return;
      const dot = wrap.querySelector('.api-dot');
      const label = wrap.querySelector('span:last-child');
      dot.classList.remove('loading','ok','err');
      if (state==='loading') dot.classList.add('loading');
      if (state==='ok') dot.classList.add('ok');
      if (state==='err') dot.classList.add('err');
      if (label) label.textContent = text || '';
    }

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
      cardStatus('statusAirtable','loading','Проверка...');
      fetch('api/test-proxy.php?provider=airtable',{
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({api_key:key, base_id:base, table})
      }).then(r=>r.json()).then(j=>{
        cardStatus('statusAirtable', j.ok?'ok':'err', j.ok? 'OK' : (j.error||('HTTP '+j.status)) );
      }).catch(()=>setStatus('Airtable: сеть/сервер недоступны'));
    });

    document.getElementById('btnTestOpenAI')?.addEventListener('click', function(){
      const key = document.getElementById('openai_api_key')?.value.trim();
      const model = document.getElementById('openai_model')?.value.trim();
      if (!key || !model) return setStatus('OpenAI: заполните API Key и модель');
      cardStatus('statusOpenAI','loading','Проверка...');
      fetch('api/test-proxy.php?provider=openai',{
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({api_key:key, model})
      }).then(r=>r.json()).then(j=>{
        cardStatus('statusOpenAI', j.ok?'ok':'err', j.ok? 'OK' : (j.error||('HTTP '+j.status)) );
      }).catch(()=>setStatus('OpenAI: сеть/сервер недоступны'));
    });

    document.getElementById('btnTestGSheets')?.addEventListener('click', function(){
      const key = document.getElementById('gsheets_api_key')?.value.trim();
      const sheet = document.getElementById('gsheets_spreadsheet_id')?.value.trim();
      if (!key || !sheet) return setStatus('Google Sheets: заполните API Key и Spreadsheet ID');
      cardStatus('statusGSheets','loading','Проверка...');
      fetch('api/test-proxy.php?provider=gsheets',{
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({api_key:key, spreadsheet_id:sheet})
      }).then(r=>r.json()).then(j=>{
        cardStatus('statusGSheets', j.ok?'ok':'err', j.ok? 'OK' : (j.error||('HTTP '+j.status)) );
      }).catch(()=>setStatus('Sheets: сеть/сервер недоступны'));
    });

    document.getElementById('btnTestGMaps')?.addEventListener('click', function(){
      const key = document.getElementById('gmaps_api_key')?.value.trim();
      if (!key) return setStatus('Google Maps: заполните API Key');
      cardStatus('statusGMaps','loading','Проверка...');
      fetch('api/test-proxy.php?provider=gmaps',{
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({api_key:key})
      }).then(r=>r.json()).then(j=>{
        cardStatus('statusGMaps', j.ok?'ok':'err', j.ok? 'OK' : (j.error||('HTTP '+j.status)) );
      }).catch(()=>setStatus('Maps: сеть/сервер недоступны'));
    });

    document.getElementById('btnTestRecaptcha')?.addEventListener('click', function(){
      const siteKey = document.getElementById('recaptcha_site_key')?.value.trim();
      const secret = document.getElementById('recaptcha_secret')?.value.trim();
      if (!siteKey || !secret) return setStatus('reCAPTCHA: заполните Site Key и Secret');
      cardStatus('statusRecaptcha','loading','Проверка...');
      fetch('api/test-proxy.php?provider=recaptcha',{
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({site_key:siteKey, secret})
      }).then(r=>r.json()).then(j=>{
        cardStatus('statusRecaptcha', j.ok?'ok':'err', j.ok? 'OK' : (j.error||('HTTP '+j.status)) );
      }).catch(()=>setStatus('reCAPTCHA: сеть/сервер недоступны'));
    });

    document.getElementById('btnTestBrilliant')?.addEventListener('click', function(){
      const key = document.getElementById('brilliantdb_api_key')?.value.trim();
      const base = document.getElementById('brilliantdb_base_url')?.value.trim();
      if (!key || !base) return setStatus('Brilliant DB: заполните API Key и Endpoint Base');
      cardStatus('statusBrilliant','loading','Проверка...');
      fetch('api/test-proxy.php?provider=brilliantdb',{
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({api_key:key, base_url:base, collection})
      }).then(r=>r.json()).then(j=>{
        cardStatus('statusBrilliant', j.ok?'ok':'err', j.ok? 'OK' : (j.error||('HTTP '+j.status)) );
      }).catch(()=>setStatus('Brilliant DB: сеть/сервер недоступны'));
    });

    load();
  });
})();


