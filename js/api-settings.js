// API Settings page logic (client-side storage)
(function(){
  function init(){
    // Do not rely on adminAuth here; attach handlers unconditionally on this page

    const fields = [
      'airtable_api_key','airtable_base_id','airtable_table',
      'openai_api_key','openai_model',
      'gsheets_api_key','gsheets_spreadsheet_id',
      'gmaps_api_key','gmaps_region',
      'recaptcha_site_key','recaptcha_secret',
      'brilliantdb_api_key','brilliantdb_base_url','brilliantdb_collection'
    ];

    const STORAGE_KEY = 'konstructour_api_settings';
    const STATUS_KEY = 'konstructour_api_status';
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

    function eraseProvider(prefix){
      const ids = fields.filter(id => id.startsWith(prefix + '_'));
      const raw = localStorage.getItem(STORAGE_KEY);
      const data = raw ? (JSON.parse(raw)||{}) : {};
      ids.forEach(id => { delete data[id]; const el = document.getElementById(id); if (el) el.value=''; });
      localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
      setStatus(prefix.toUpperCase()+': очищено');
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

      // Persist status for dashboard consumption
      const ID_TO_PROVIDER = {
        statusAirtable: 'airtable',
        statusOpenAI: 'openai',
        statusGSheets: 'gsheets',
        statusGMaps: 'gmaps',
        statusRecaptcha: 'recaptcha',
        statusBrilliant: 'brilliantdirectory'
      };
      const provider = ID_TO_PROVIDER[id];
      if (provider){
        let statusMap = {};
        try { statusMap = JSON.parse(localStorage.getItem(STATUS_KEY)||'{}') || {}; } catch(_) {}
        statusMap[provider] = { state: state, text: text || '', ts: Date.now() };
        localStorage.setItem(STATUS_KEY, JSON.stringify(statusMap));
      }
    }

    document.getElementById('btnSaveAirtable')?.addEventListener('click', save);
    document.getElementById('btnSaveOpenAI')?.addEventListener('click', save);
    document.getElementById('btnSaveGSheets')?.addEventListener('click', save);
    document.getElementById('btnSaveGMaps')?.addEventListener('click', save);
    document.getElementById('btnSaveRecaptcha')?.addEventListener('click', save);
    document.getElementById('btnSaveBrilliant')?.addEventListener('click', save);
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
    document.getElementById('btnTestAirtable')?.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation();
      const key = document.getElementById('airtable_api_key')?.value.trim();
      const base = document.getElementById('airtable_base_id')?.value.trim();
      const table = document.getElementById('airtable_table')?.value.trim();
      cardStatus('statusAirtable','loading','Проверка...');
      // Собираем параметры из доступных полей. Если есть только key — отработает whoami на сервере.
      const params = new URLSearchParams({ provider:'airtable' });
      if (key) params.append('api_key', key);
      if (base) params.append('base_id', base);
      if (table) params.append('table', table);
      const url = `/api/test-proxy.php?${params.toString()}`;
      fetch(url, { method:'GET', mode:'cors', credentials:'same-origin', headers:{ 'Accept':'application/json' }})
      .then(async r=>{ let j; try{ j=await r.json(); }catch(e){ j={ok:false,status:r.status,error:'Invalid JSON'} } return j; })
      .then(j=>{
        cardStatus('statusAirtable', j.ok?'ok':'err', j.ok? 'OK' : (j.error||('HTTP '+j.status)) );
      })
      .catch((err)=>{ console.error('Airtable test error:', err); cardStatus('statusAirtable','err','Network error'); setStatus('Airtable: сеть/сервер недоступны'); });
    });

    const testOpenAI = function(){
      const key = document.getElementById('openai_api_key')?.value.trim();
      const model = document.getElementById('openai_model')?.value.trim();
      cardStatus('statusOpenAI','loading','Проверка...');
      const url = key
        ? `/api/test-proxy.php?provider=openai&api_key=${encodeURIComponent(key)}&model=${encodeURIComponent(model||'gpt-4o-mini')}`
        : `/api/test-proxy.php?provider=openai`;
      fetch(url, { method:'GET', mode:'cors', credentials:'same-origin', headers:{ 'Accept':'application/json' }})
      .then(async r=>{ let j; try{ j=await r.json(); }catch(e){ j={ok:false,status:r.status,error:'Invalid JSON'} } return j; })
      .then(j=>{
        cardStatus('statusOpenAI', j.ok?'ok':'err', j.ok? 'OK' : (j.error||('HTTP '+j.status)) );
      })
      .catch((err)=>{ console.error('OpenAI test error:', err); cardStatus('statusOpenAI','err','Network error'); setStatus('OpenAI: сеть/сервер недоступны'); });
    };
    document.getElementById('btnTestOpenAI')?.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); testOpenAI(); });
    // Enter key on fields triggers Test
    document.getElementById('openai_api_key')?.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); testOpenAI(); }});
    document.getElementById('openai_model')?.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); testOpenAI(); }});

    document.getElementById('btnTestGSheets')?.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation();
      const key = document.getElementById('gsheets_api_key')?.value.trim();
      const sheet = document.getElementById('gsheets_spreadsheet_id')?.value.trim();
      if (!key || !sheet) return setStatus('Google Sheets: заполните API Key и Spreadsheet ID');
      cardStatus('statusGSheets','loading','Проверка...');
      fetch(`/api/test-proxy.php?provider=gsheets&api_key=${encodeURIComponent(key)}&spreadsheet_id=${encodeURIComponent(sheet)}`)
      .then(async r=>{ let j; try{ j=await r.json(); }catch(e){ j={ok:false,status:r.status,error:'Invalid JSON'} } return j; }).then(j=>{
        cardStatus('statusGSheets', j.ok?'ok':'err', j.ok? 'OK' : (j.error||('HTTP '+j.status)) );
      }).catch(()=>{ cardStatus('statusGSheets','err','Network error'); setStatus('Sheets: сеть/сервер недоступны'); });
    });

    document.getElementById('btnTestGMaps')?.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation();
      const key = document.getElementById('gmaps_api_key')?.value.trim();
      if (!key) return setStatus('Google Maps: заполните API Key');
      cardStatus('statusGMaps','loading','Проверка...');
      fetch(`/api/test-proxy.php?provider=gmaps&api_key=${encodeURIComponent(key)}`)
      .then(async r=>{ let j; try{ j=await r.json(); }catch(e){ j={ok:false,status:r.status,error:'Invalid JSON'} } return j; }).then(j=>{
        cardStatus('statusGMaps', j.ok?'ok':'err', j.ok? 'OK' : (j.error||('HTTP '+j.status)) );
      }).catch(()=>{ cardStatus('statusGMaps','err','Network error'); setStatus('Maps: сеть/сервер недоступны'); });
    });

    document.getElementById('btnTestRecaptcha')?.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation();
      const siteKey = document.getElementById('recaptcha_site_key')?.value.trim();
      const secret = document.getElementById('recaptcha_secret')?.value.trim();
      if (!siteKey || !secret) return setStatus('reCAPTCHA: заполните Site Key и Secret');
      cardStatus('statusRecaptcha','loading','Проверка...');
      fetch(`/api/test-proxy.php?provider=recaptcha&site_key=${encodeURIComponent(siteKey)}&secret=${encodeURIComponent(secret)}`)
      .then(async r=>{ let j; try{ j=await r.json(); }catch(e){ j={ok:false,status:r.status,error:'Invalid JSON'} } return j; }).then(j=>{
        cardStatus('statusRecaptcha', j.ok?'ok':'err', j.ok? 'OK' : (j.error||('HTTP '+j.status)) );
      }).catch(()=>{ cardStatus('statusRecaptcha','err','Network error'); setStatus('reCAPTCHA: сеть/сервер недоступны'); });
    });

    const testBrilliant = function(){
      const key = document.getElementById('brilliantdb_api_key')?.value.trim();
      const base = document.getElementById('brilliantdb_base_url')?.value.trim();
      const collection = document.getElementById('brilliantdb_collection')?.value.trim();
      cardStatus('statusBrilliant','loading','Проверка...');
      // Use Brilliant Directories spec if base seems like /api/v2/
      const useBD = base && /\/api\/v2\/?$/.test(base);
      const params = new URLSearchParams({ provider: useBD ? 'brilliantdirectory' : 'brilliantdb' });
      if (key) params.append('api_key', key);
      if (base) params.append('base_url', base);
      if (collection) params.append('collection', collection);
      const url = `/api/test-proxy.php?${params.toString()}`;
      fetch(url, { method:'GET', mode:'cors', credentials:'same-origin', headers:{ 'Accept':'application/json' }})
      .then(async r=>{ let j; try{ j=await r.json(); }catch(e){ j={ok:false,status:r.status,error:'Invalid JSON'} } return j; })
      .then(j=>{
        cardStatus('statusBrilliant', j.ok?'ok':'err', j.ok? 'OK' : (j.error||('HTTP '+j.status)) );
      })
      .catch(()=>{ cardStatus('statusBrilliant','err','Network error'); setStatus('Brilliant Directory: сеть/сервер недоступны'); });
    };
    document.getElementById('btnTestBrilliant')?.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); testBrilliant(); });

    load();
    // Убрали прежний capture-блокировщик кликов внутри .api-actions,
    // чтобы не гасить обработчики кнопок (Test/Save)
    // Global capture guard: блокируем только переходы по ссылкам внутри .api-actions,
    // не останавливаем другие клики (чтобы не мешать нашим обработчикам кнопок)
    document.addEventListener('click', function(e){
      const anchor = e.target && e.target.closest && e.target.closest('.api-actions a[href]');
      if (anchor) { e.preventDefault(); e.stopPropagation(); }
    }, true);

    // Robust delegated handlers (если кнопки перерисуются)
    document.addEventListener('click', function(e){
      const el = e.target.closest ? e.target.closest('button') : null;
      if (!el) return;
      // Только после того как убедились, что это кнопка, гасим навигацию и всплытие
      e.preventDefault(); e.stopPropagation();
      if (el.id === 'btnTestOpenAI') { testOpenAI(); }
      if (el.id === 'btnSaveOpenAI') { e.preventDefault(); save(); }
      if (el.id === 'btnEraseOpenAI') { eraseProvider('openai'); }
      if (el.id === 'btnTestAirtable') { e.preventDefault(); document.getElementById('statusAirtable') && cardStatus('statusAirtable','loading','Проверка...'); /* оставим текущий обработчик ниже */ }
      if (el.id === 'btnSaveAirtable') { e.preventDefault(); save(); }
      if (el.id === 'btnEraseAirtable') { eraseProvider('airtable'); }
      if (el.id === 'btnTestGSheets') { e.preventDefault(); document.getElementById('statusGSheets') && cardStatus('statusGSheets','loading','Проверка...'); }
      if (el.id === 'btnSaveGSheets') { e.preventDefault(); save(); }
      if (el.id === 'btnEraseGSheets') { eraseProvider('gsheets'); }
      if (el.id === 'btnTestGMaps') { e.preventDefault(); document.getElementById('statusGMaps') && cardStatus('statusGMaps','loading','Проверка...'); }
      if (el.id === 'btnSaveGMaps') { e.preventDefault(); save(); }
      if (el.id === 'btnEraseGMaps') { eraseProvider('gmaps'); }
      if (el.id === 'btnTestRecaptcha') { e.preventDefault(); document.getElementById('statusRecaptcha') && cardStatus('statusRecaptcha','loading','Проверка...'); }
      if (el.id === 'btnSaveRecaptcha') { e.preventDefault(); save(); }
      if (el.id === 'btnEraseRecaptcha') { eraseProvider('recaptcha'); }
      if (el.id === 'btnTestBrilliant') { testBrilliant(); }
      if (el.id === 'btnSaveBrilliant') { e.preventDefault(); save(); }
      if (el.id === 'btnEraseBrilliant') { eraseProvider('brilliantdb'); }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();


